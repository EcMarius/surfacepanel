<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PHPVersion;
use App\Models\DomainPHPSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class MultiPHPController extends Controller
{
    public function __construct()
    {
        $this->middleware(['panel.detector', 'auth.admin']);
    }

    /**
     * Display PHP version management
     */
    public function index()
    {
        $phpVersions = PHPVersion::withCount('domainSettings')->get();
        $defaultVersion = PHPVersion::where('is_default', true)->first();

        // Get usage statistics
        $usageStats = DomainPHPSetting::selectRaw('php_version_id, COUNT(*) as count')
            ->groupBy('php_version_id')
            ->get()
            ->pluck('count', 'php_version_id')
            ->toArray();

        return view('admin.multiphp.index', compact('phpVersions', 'defaultVersion', 'usageStats'));
    }

    /**
     * Detect and register system PHP versions
     */
    public function detectVersions(Request $request)
    {
        try {
            $detected = PHPVersion::detectSystemVersions();
            $registered = 0;

            foreach ($detected as $versionData) {
                $existing = PHPVersion::where('version', $versionData['version'])->first();

                if (!$existing) {
                    // Detect extensions
                    $extensions = $this->detectPHPExtensions($versionData['binary_path']);

                    PHPVersion::create([
                        'version' => $versionData['version'],
                        'binary_path' => $versionData['binary_path'],
                        'fpm_pool_dir' => $versionData['fpm_pool_dir'],
                        'php_ini_path' => $versionData['php_ini_path'],
                        'is_active' => true,
                        'is_default' => false,
                        'extensions' => $extensions,
                    ]);

                    $registered++;
                }
            }

            return response()->json([
                'success' => true,
                'message' => "Detected and registered {$registered} new PHP version(s)",
                'detected' => count($detected),
                'registered' => $registered,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to detect PHP versions: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Detect PHP extensions for a given binary
     */
    private function detectPHPExtensions(string $binaryPath): array
    {
        $extensions = [];

        exec("$binaryPath -m 2>&1", $output);

        foreach ($output as $line) {
            $line = trim($line);
            if (!empty($line) && $line !== '[PHP Modules]' && $line !== '[Zend Modules]') {
                $extensions[] = strtolower($line);
            }
        }

        return array_unique($extensions);
    }

    /**
     * Add a PHP version manually
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'version' => 'required|string|unique:vp_php_versions,version',
            'binary_path' => 'required|string',
            'fpm_pool_dir' => 'required|string',
            'php_ini_path' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            // Verify binary exists
            if (!file_exists($request->binary_path)) {
                return response()->json([
                    'success' => false,
                    'message' => 'PHP binary not found at specified path',
                ], 422);
            }

            // Detect extensions
            $extensions = $this->detectPHPExtensions($request->binary_path);

            $phpVersion = PHPVersion::create([
                'version' => $request->version,
                'binary_path' => $request->binary_path,
                'fpm_pool_dir' => $request->fpm_pool_dir,
                'php_ini_path' => $request->php_ini_path,
                'is_active' => true,
                'is_default' => false,
                'extensions' => $extensions,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'PHP version added successfully',
                'phpVersion' => $phpVersion,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to add PHP version: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Set default PHP version
     */
    public function setDefault(Request $request, $id)
    {
        try {
            $phpVersion = PHPVersion::findOrFail($id);

            if (!$phpVersion->is_active) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot set inactive PHP version as default',
                ], 422);
            }

            // Remove default flag from all versions
            PHPVersion::where('is_default', true)->update(['is_default' => false]);

            // Set new default
            $phpVersion->is_default = true;
            $phpVersion->save();

            return response()->json([
                'success' => true,
                'message' => "PHP {$phpVersion->version} set as default",
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to set default version: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Toggle PHP version active status
     */
    public function toggleActive(Request $request, $id)
    {
        try {
            $phpVersion = PHPVersion::findOrFail($id);

            // Don't allow disabling the default version
            if ($phpVersion->is_default && $phpVersion->is_active) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot disable the default PHP version',
                ], 422);
            }

            $phpVersion->is_active = !$phpVersion->is_active;
            $phpVersion->save();

            $status = $phpVersion->is_active ? 'enabled' : 'disabled';

            return response()->json([
                'success' => true,
                'message' => "PHP {$phpVersion->version} {$status}",
                'is_active' => $phpVersion->is_active,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to toggle status: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Delete PHP version
     */
    public function destroy($id)
    {
        try {
            $phpVersion = PHPVersion::findOrFail($id);

            // Don't allow deleting if it's the default
            if ($phpVersion->is_default) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot delete the default PHP version',
                ], 422);
            }

            // Check if any domains are using this version
            $usageCount = $phpVersion->domainSettings()->count();
            if ($usageCount > 0) {
                return response()->json([
                    'success' => false,
                    'message' => "Cannot delete: {$usageCount} domain(s) are using this PHP version",
                ], 422);
            }

            $phpVersion->delete();

            return response()->json([
                'success' => true,
                'message' => 'PHP version deleted successfully',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete PHP version: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get PHP version details including extensions
     */
    public function show($id)
    {
        try {
            $phpVersion = PHPVersion::with('domainSettings.account')->findOrFail($id);

            return response()->json([
                'success' => true,
                'phpVersion' => $phpVersion,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'PHP version not found',
            ], 404);
        }
    }

    /**
     * Update PHP version extensions
     */
    public function refreshExtensions(Request $request, $id)
    {
        try {
            $phpVersion = PHPVersion::findOrFail($id);

            $extensions = $this->detectPHPExtensions($phpVersion->binary_path);
            $phpVersion->extensions = $extensions;
            $phpVersion->save();

            return response()->json([
                'success' => true,
                'message' => 'Extensions refreshed successfully',
                'extensions' => $extensions,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to refresh extensions: ' . $e->getMessage(),
            ], 500);
        }
    }
}
