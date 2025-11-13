<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\Backup;
use App\Models\BackupSchedule;
use App\Models\BackupDestination;
use App\Models\BackupRotation;
use App\Models\BackupEncryption;
use App\Services\BackupService;
use App\Services\RestoreService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class BackupController extends Controller
{
    protected $backupService;
    protected $restoreService;

    public function __construct(BackupService $backupService, RestoreService $restoreService)
    {
        $this->middleware(['panel.detector', 'auth.admin']);
        $this->backupService = $backupService;
        $this->restoreService = $restoreService;
    }

    /**
     * Display backup schedules management
     */
    public function schedules()
    {
        $schedules = BackupSchedule::with(['account', 'encryption', 'rotation'])
            ->where('is_system_wide', true)
            ->orWhereNull('account_id')
            ->paginate(20);

        $accounts = Account::where('status', 'active')->get();
        $encryptionKeys = BackupEncryption::where('is_system_wide', true)->get();
        $rotationPolicies = BackupRotation::where('is_system_wide', true)->get();

        return view('admin.backups.schedules', compact('schedules', 'accounts', 'encryptionKeys', 'rotationPolicies'));
    }

    /**
     * Store new backup schedule
     */
    public function storeSchedule(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'frequency' => 'required|in:daily,weekly,monthly,custom',
            'cron_expression' => 'required_if:frequency,custom',
            'backup_type' => 'required|in:full,incremental,files,databases',
            'backup_time' => 'required|date_format:H:i',
            'account_id' => 'nullable|exists:vp_accounts,id',
            'include_files' => 'boolean',
            'include_databases' => 'boolean',
            'include_emails' => 'boolean',
            'is_encrypted' => 'boolean',
            'encryption_id' => 'required_if:is_encrypted,true|exists:vp_backup_encryption,id',
            'destination_ids' => 'nullable|array',
            'rotation_id' => 'nullable|exists:vp_backup_rotations,id',
            'is_active' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $schedule = BackupSchedule::create(array_merge($request->all(), [
                'is_system_wide' => $request->account_id ? false : true,
            ]));

            $schedule->calculateNextRun();

            return response()->json([
                'success' => true,
                'message' => 'Backup schedule created successfully',
                'schedule' => $schedule->load(['encryption', 'rotation']),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create schedule: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update backup schedule
     */
    public function updateSchedule(Request $request, $id)
    {
        $schedule = BackupSchedule::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'frequency' => 'required|in:daily,weekly,monthly,custom',
            'backup_type' => 'required|in:full,incremental,files,databases',
            'backup_time' => 'required|date_format:H:i',
            'is_active' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $schedule->update($request->all());
            $schedule->calculateNextRun();

            return response()->json([
                'success' => true,
                'message' => 'Schedule updated successfully',
                'schedule' => $schedule,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update schedule: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Delete backup schedule
     */
    public function deleteSchedule($id)
    {
        try {
            $schedule = BackupSchedule::findOrFail($id);
            $schedule->delete();

            return response()->json([
                'success' => true,
                'message' => 'Schedule deleted successfully',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete schedule: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Display remote destinations
     */
    public function destinations()
    {
        $destinations = BackupDestination::where('is_system_wide', true)
            ->orWhereNull('account_id')
            ->paginate(20);

        return view('admin.backups.destinations', compact('destinations'));
    }

    /**
     * Store new destination
     */
    public function storeDestination(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'type' => 'required|in:s3,ftp,sftp,ssh,local',
            'hostname' => 'required_unless:type,local',
            'port' => 'nullable|integer',
            'username' => 'required_unless:type,local',
            'password' => 'required_if:type,ftp',
            'path' => 'required',
            'access_key' => 'required_if:type,s3',
            'secret_key' => 'required_if:type,s3',
            'region' => 'nullable|string',
            'private_key' => 'nullable|string',
            'is_active' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $destination = BackupDestination::create(array_merge($request->all(), [
                'is_system_wide' => true,
            ]));

            // Test connection
            $destination->testConnection();

            return response()->json([
                'success' => true,
                'message' => 'Destination added successfully',
                'destination' => $destination,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to add destination: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Test destination connection
     */
    public function testDestination($id)
    {
        try {
            $destination = BackupDestination::findOrFail($id);
            $success = $destination->testConnection();

            return response()->json([
                'success' => $success,
                'message' => $success ? 'Connection successful' : 'Connection failed',
                'status' => $destination->connection_status,
                'error' => $destination->connection_error,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Test failed: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Delete destination
     */
    public function deleteDestination($id)
    {
        try {
            $destination = BackupDestination::findOrFail($id);

            // Check if destination is used by any schedules
            $usageCount = BackupSchedule::whereJsonContains('destination_ids', $id)->count();

            if ($usageCount > 0) {
                return response()->json([
                    'success' => false,
                    'message' => "Cannot delete: {$usageCount} schedule(s) are using this destination",
                ], 422);
            }

            $destination->delete();

            return response()->json([
                'success' => true,
                'message' => 'Destination deleted successfully',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete destination: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Display rotation policies
     */
    public function rotations()
    {
        $rotations = BackupRotation::where('is_system_wide', true)
            ->orWhereNull('account_id')
            ->paginate(20);

        return view('admin.backups.rotations', compact('rotations'));
    }

    /**
     * Store rotation policy
     */
    public function storeRotation(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'keep_daily' => 'required|integer|min:0',
            'keep_weekly' => 'required|integer|min:0',
            'keep_monthly' => 'required|integer|min:0',
            'keep_yearly' => 'required|integer|min:0',
            'max_backups' => 'nullable|integer|min:1',
            'max_storage_mb' => 'nullable|integer|min:1',
            'delete_from_remote' => 'boolean',
            'is_active' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $rotation = BackupRotation::create(array_merge($request->all(), [
                'is_system_wide' => true,
            ]));

            return response()->json([
                'success' => true,
                'message' => 'Rotation policy created successfully',
                'rotation' => $rotation,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create rotation policy: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Display encryption keys
     */
    public function encryption()
    {
        $encryptionKeys = BackupEncryption::where('is_system_wide', true)
            ->orWhereNull('account_id')
            ->paginate(20);

        return view('admin.backups.encryption', compact('encryptionKeys'));
    }

    /**
     * Store encryption key
     */
    public function storeEncryption(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'method' => 'required|in:aes-256-cbc,aes-128-cbc,aes-256-gcm',
            'is_active' => 'boolean',
            'is_default' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            // Generate key and IV
            $key = BackupEncryption::generateKey($request->method);
            $iv = BackupEncryption::generateIV($request->method);

            $encryption = BackupEncryption::create([
                'name' => $request->name,
                'method' => $request->method,
                'encryption_key' => $key,
                'iv' => $iv,
                'is_active' => $request->is_active ?? true,
                'is_default' => $request->is_default ?? false,
                'is_system_wide' => true,
            ]);

            if ($request->is_default) {
                $encryption->setAsDefault();
            }

            return response()->json([
                'success' => true,
                'message' => 'Encryption key created successfully',
                'encryption' => $encryption,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create encryption key: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * List all backups
     */
    public function index(Request $request)
    {
        $query = Backup::with(['account', 'schedule']);

        if ($request->account_id) {
            $query->where('account_id', $request->account_id);
        }

        if ($request->status) {
            $query->where('status', $request->status);
        }

        if ($request->type) {
            $query->where('type', $request->type);
        }

        $backups = $query->orderBy('created_at', 'desc')->paginate(20);

        return view('admin.backups.index', compact('backups'));
    }

    /**
     * Create manual backup
     */
    public function create(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'account_id' => 'required|exists:vp_accounts,id',
            'type' => 'required|in:full,incremental',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $account = Account::findOrFail($request->account_id);

            if ($request->type === 'full') {
                $backup = $this->backupService->createFullBackup($account);
            } else {
                $backup = $this->backupService->createIncrementalBackup($account);
            }

            return response()->json([
                'success' => true,
                'message' => 'Backup created successfully',
                'backup' => $backup,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Backup failed: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Delete backup
     */
    public function delete($id)
    {
        try {
            $backup = Backup::findOrFail($id);

            // Delete file
            $backup->deleteFile();

            // Delete record
            $backup->delete();

            return response()->json([
                'success' => true,
                'message' => 'Backup deleted successfully',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete backup: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Download backup
     */
    public function download($id)
    {
        try {
            $backup = Backup::findOrFail($id);

            if (!file_exists($backup->path)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Backup file not found',
                ], 404);
            }

            return response()->download($backup->path, $backup->filename);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Download failed: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get backup statistics
     */
    public function statistics()
    {
        $stats = [
            'total_backups' => Backup::count(),
            'completed_backups' => Backup::where('status', 'completed')->count(),
            'failed_backups' => Backup::where('status', 'failed')->count(),
            'total_size' => Backup::where('status', 'completed')->sum('size'),
            'active_schedules' => BackupSchedule::where('is_active', true)->count(),
            'recent_backups' => Backup::with('account')
                ->orderBy('created_at', 'desc')
                ->limit(10)
                ->get(),
        ];

        return response()->json([
            'success' => true,
            'statistics' => $stats,
        ]);
    }
}
