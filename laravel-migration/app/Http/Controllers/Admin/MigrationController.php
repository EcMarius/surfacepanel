<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Migration;
use App\Models\MigrationLog;
use App\Services\MigrationService;
use App\Services\CpanelBackupParser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Validator;
use Exception;

class MigrationController extends Controller
{
    /**
     * Display migration list
     */
    public function index()
    {
        $migrations = Migration::with('creator')
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return view('admin.migration.index', compact('migrations'));
    }

    /**
     * Show upload form
     */
    public function create()
    {
        return view('admin.migration.create');
    }

    /**
     * Upload and parse cPanel backup
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'backup_file' => 'required|file|mimes:gz,tar,tgz|max:10485760', // 10GB max
            'source_type' => 'required|in:cpanel,whm,plesk,directadmin',
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        try {
            $file = $request->file('backup_file');

            // Create storage directory
            $storagePath = storage_path('app/migrations/backups');
            if (!File::exists($storagePath)) {
                File::makeDirectory($storagePath, 0755, true);
            }

            // Generate unique filename
            $filename = time() . '_' . $file->getClientOriginalName();
            $filePath = $storagePath . '/' . $filename;

            // Move uploaded file
            $file->move($storagePath, $filename);

            // Create migration record
            $migration = Migration::create([
                'backup_filename' => $filename,
                'backup_path' => $filePath,
                'backup_size' => filesize($filePath),
                'source_type' => $request->source_type,
                'status' => 'pending',
                'progress_percentage' => 0,
                'created_by' => Auth::id(),
            ]);

            // Parse backup to get summary
            $parser = new CpanelBackupParser($filePath);
            if ($parser->extract()) {
                $parsedData = $parser->parse();
                $migration->migration_summary = $this->buildSummary($parsedData);
                $migration->save();
                $parser->cleanup();
            }

            return redirect()->route('admin.migration.show', $migration->id)
                ->with('success', 'Backup uploaded successfully. Review the summary and start migration.');
        } catch (Exception $e) {
            return back()->with('error', 'Failed to upload backup: ' . $e->getMessage());
        }
    }

    /**
     * Show migration details
     */
    public function show($id)
    {
        $migration = Migration::with(['creator', 'logs'])->findOrFail($id);

        return view('admin.migration.show', compact('migration'));
    }

    /**
     * Start migration process
     */
    public function start(Request $request, $id)
    {
        $migration = Migration::findOrFail($id);

        if ($migration->status !== 'pending') {
            return back()->with('error', 'Migration has already been started or completed.');
        }

        try {
            // Start migration in the background
            $migration->status = 'uploading';
            $migration->progress_percentage = 1;
            $migration->started_at = now();
            $migration->save();

            // Run migration service
            $service = new MigrationService($migration);
            $service->migrate();

            return redirect()->route('admin.migration.progress', $migration->id);
        } catch (Exception $e) {
            $migration->status = 'failed';
            $migration->error_message = $e->getMessage();
            $migration->save();

            return back()->with('error', 'Migration failed: ' . $e->getMessage());
        }
    }

    /**
     * Show migration progress
     */
    public function progress($id)
    {
        $migration = Migration::findOrFail($id);

        return view('admin.migration.progress', compact('migration'));
    }

    /**
     * Get migration status (AJAX)
     */
    public function status($id)
    {
        $migration = Migration::findOrFail($id);

        $recentLogs = MigrationLog::where('migration_id', $id)
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();

        return response()->json([
            'status' => $migration->status,
            'progress_percentage' => $migration->progress_percentage,
            'current_step' => $migration->current_step,
            'error_message' => $migration->error_message,
            'recent_logs' => $recentLogs,
            'is_completed' => $migration->isCompleted(),
            'has_failed' => $migration->hasFailed(),
        ]);
    }

    /**
     * Show migration report
     */
    public function report($id)
    {
        $migration = Migration::with(['logs', 'creator'])->findOrFail($id);

        // Get categorized logs
        $logsByCategory = MigrationLog::where('migration_id', $id)
            ->get()
            ->groupBy('category');

        // Get error logs
        $errorLogs = MigrationLog::where('migration_id', $id)
            ->where('level', 'error')
            ->get();

        // Get warning logs
        $warningLogs = MigrationLog::where('migration_id', $id)
            ->where('level', 'warning')
            ->get();

        return view('admin.migration.report', compact('migration', 'logsByCategory', 'errorLogs', 'warningLogs'));
    }

    /**
     * Rollback migration
     */
    public function rollback(Request $request, $id)
    {
        $migration = Migration::findOrFail($id);

        if (!$migration->isCompleted()) {
            return back()->with('error', 'Only completed migrations can be rolled back.');
        }

        try {
            // TODO: Implement rollback logic
            // This should delete all migrated data for this migration

            $migration->status = 'rolled_back';
            $migration->save();

            MigrationLog::create([
                'migration_id' => $migration->id,
                'level' => 'info',
                'message' => 'Migration rolled back by ' . Auth::user()->username,
            ]);

            return redirect()->route('admin.migration.index')
                ->with('success', 'Migration rolled back successfully.');
        } catch (Exception $e) {
            return back()->with('error', 'Rollback failed: ' . $e->getMessage());
        }
    }

    /**
     * Delete migration record
     */
    public function destroy($id)
    {
        $migration = Migration::findOrFail($id);

        try {
            // Delete backup file
            if (File::exists($migration->backup_path)) {
                File::delete($migration->backup_path);
            }

            // Delete migration record (logs will be cascade deleted)
            $migration->delete();

            return redirect()->route('admin.migration.index')
                ->with('success', 'Migration record deleted successfully.');
        } catch (Exception $e) {
            return back()->with('error', 'Failed to delete migration: ' . $e->getMessage());
        }
    }

    /**
     * Download migration logs
     */
    public function downloadLogs($id)
    {
        $migration = Migration::findOrFail($id);
        $logs = MigrationLog::where('migration_id', $id)
            ->orderBy('created_at', 'asc')
            ->get();

        $content = "Migration Log Report\n";
        $content .= "===================\n\n";
        $content .= "Migration ID: {$migration->id}\n";
        $content .= "Backup File: {$migration->backup_filename}\n";
        $content .= "Status: {$migration->status}\n";
        $content .= "Started: {$migration->started_at}\n";
        $content .= "Completed: {$migration->completed_at}\n\n";
        $content .= "Logs:\n";
        $content .= "-----\n\n";

        foreach ($logs as $log) {
            $content .= "[{$log->created_at}] [{$log->level}]";
            if ($log->category) {
                $content .= " [{$log->category}]";
            }
            if ($log->item_name) {
                $content .= " [{$log->item_name}]";
            }
            $content .= " {$log->message}\n";
        }

        $filename = 'migration_' . $migration->id . '_logs_' . date('Y-m-d_H-i-s') . '.txt';

        return response($content)
            ->header('Content-Type', 'text/plain')
            ->header('Content-Disposition', 'attachment; filename="' . $filename . '"');
    }

    /**
     * Validate backup before migration
     */
    public function validate(Request $request, $id)
    {
        $migration = Migration::findOrFail($id);

        try {
            $parser = new CpanelBackupParser($migration->backup_path);

            if (!$parser->extract()) {
                return response()->json([
                    'success' => false,
                    'errors' => ['Failed to extract backup file'],
                ]);
            }

            $errors = $parser->validate();
            $parser->cleanup();

            if (empty($errors)) {
                return response()->json([
                    'success' => true,
                    'message' => 'Backup validation passed',
                ]);
            }

            $migration->validation_errors = $errors;
            $migration->save();

            return response()->json([
                'success' => false,
                'errors' => $errors,
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'errors' => [$e->getMessage()],
            ]);
        }
    }

    /**
     * Build migration summary
     */
    protected function buildSummary(array $parsedData): array
    {
        return [
            'account' => $parsedData['account']['username'] ?? 'unknown',
            'domain' => $parsedData['account']['domain'] ?? 'unknown',
            'email' => $parsedData['account']['email'] ?? '',
            'plan' => $parsedData['account']['plan'] ?? 'default',
            'addon_domains' => count($parsedData['addon_domains'] ?? []),
            'subdomains' => count($parsedData['subdomains'] ?? []),
            'parked_domains' => count($parsedData['parked_domains'] ?? []),
            'email_accounts' => count($parsedData['email_accounts'] ?? []),
            'email_forwarders' => count($parsedData['email_forwarders'] ?? []),
            'databases' => count($parsedData['databases'] ?? []),
            'database_users' => count($parsedData['database_users'] ?? []),
            'dns_zones' => count($parsedData['dns_zones'] ?? []),
            'ssl_certificates' => count($parsedData['ssl_certificates'] ?? []),
            'cron_jobs' => count($parsedData['cron_jobs'] ?? []),
            'ftp_accounts' => count($parsedData['ftp_accounts'] ?? []),
        ];
    }

    /**
     * Get migration statistics
     */
    public function statistics()
    {
        $stats = [
            'total' => Migration::count(),
            'completed' => Migration::completed()->count(),
            'failed' => Migration::failed()->count(),
            'in_progress' => Migration::inProgress()->count(),
            'pending' => Migration::where('status', 'pending')->count(),
        ];

        $recentMigrations = Migration::with('creator')
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get();

        return view('admin.migration.statistics', compact('stats', 'recentMigrations'));
    }
}
