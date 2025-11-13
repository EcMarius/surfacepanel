<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\Backup;
use App\Models\BackupSchedule;
use App\Models\BackupDestination;
use App\Models\BackupRotation;
use App\Models\BackupEncryption;
use App\Services\BackupService;
use App\Services\RestoreService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class BackupController extends Controller
{
    protected $backupService;
    protected $restoreService;

    public function __construct(BackupService $backupService, RestoreService $restoreService)
    {
        $this->middleware(['panel.detector', 'auth.user']);
        $this->backupService = $backupService;
        $this->restoreService = $restoreService;
    }

    /**
     * Display user's backups
     */
    public function index()
    {
        $user = Auth::user();
        $account = $user->accounts()->first();

        if (!$account) {
            return view('user.backups.index', [
                'backups' => collect([]),
                'schedules' => collect([]),
                'account' => null,
            ]);
        }

        $backups = Backup::where('account_id', $account->id)
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        $schedules = BackupSchedule::where('account_id', $account->id)
            ->orWhere('is_system_wide', true)
            ->get();

        $destinations = BackupDestination::where('account_id', $account->id)
            ->orWhere('is_system_wide', true)
            ->get();

        $encryptionKeys = BackupEncryption::where('account_id', $account->id)
            ->orWhere('is_system_wide', true)
            ->get();

        $rotationPolicies = BackupRotation::where('account_id', $account->id)
            ->orWhere('is_system_wide', true)
            ->get();

        return view('user.backups.index', compact(
            'backups',
            'schedules',
            'destinations',
            'encryptionKeys',
            'rotationPolicies',
            'account'
        ));
    }

    /**
     * Create manual backup
     */
    public function create(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'type' => 'required|in:full,incremental,files,databases',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $user = Auth::user();
            $account = $user->accounts()->firstOrFail();

            if ($request->type === 'full') {
                $backup = $this->backupService->createFullBackup($account);
            } elseif ($request->type === 'incremental') {
                $backup = $this->backupService->createIncrementalBackup($account);
            } else {
                // Create partial backup
                $backup = $this->backupService->createFullBackup($account);
            }

            return response()->json([
                'success' => true,
                'message' => 'Backup started successfully',
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
     * Download backup
     */
    public function download($id)
    {
        try {
            $user = Auth::user();
            $account = $user->accounts()->firstOrFail();

            $backup = Backup::where('account_id', $account->id)
                ->where('id', $id)
                ->firstOrFail();

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
     * Delete backup
     */
    public function delete($id)
    {
        try {
            $user = Auth::user();
            $account = $user->accounts()->firstOrFail();

            $backup = Backup::where('account_id', $account->id)
                ->where('id', $id)
                ->firstOrFail();

            $backup->deleteFile();
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
     * Display restore page
     */
    public function restore($id)
    {
        $user = Auth::user();
        $account = $user->accounts()->firstOrFail();

        $backup = Backup::where('account_id', $account->id)
            ->where('id', $id)
            ->where('status', 'completed')
            ->firstOrFail();

        // Get preview
        try {
            $preview = $this->restoreService->previewBackup($backup);

            return view('user.backups.restore', [
                'backup' => $backup,
                'preview' => $preview,
                'account' => $account,
            ]);
        } catch (\Exception $e) {
            return back()->with('error', 'Failed to preview backup: ' . $e->getMessage());
        }
    }

    /**
     * Process restore
     */
    public function processRestore(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'restore_files' => 'boolean',
            'restore_databases' => 'boolean',
            'restore_emails' => 'boolean',
            'restore_config' => 'boolean',
            'specific_files' => 'nullable|array',
            'specific_databases' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $user = Auth::user();
            $account = $user->accounts()->firstOrFail();

            $backup = Backup::where('account_id', $account->id)
                ->where('id', $id)
                ->where('status', 'completed')
                ->firstOrFail();

            // Check if specific files or databases are requested
            if ($request->specific_files && count($request->specific_files) > 0) {
                $this->restoreService->restoreSpecificFiles($backup, $account, $request->specific_files);
                $message = 'Selected files restored successfully';
            } elseif ($request->specific_databases && count($request->specific_databases) > 0) {
                $this->restoreService->restoreSpecificDatabases($backup, $account, $request->specific_databases);
                $message = 'Selected databases restored successfully';
            } else {
                // Full restore
                $options = [
                    'restore_files' => $request->restore_files ?? true,
                    'restore_databases' => $request->restore_databases ?? true,
                    'restore_emails' => $request->restore_emails ?? true,
                    'restore_config' => $request->restore_config ?? true,
                ];

                $this->restoreService->restoreFullBackup($backup, $account, $options);
                $message = 'Backup restored successfully';
            }

            return response()->json([
                'success' => true,
                'message' => $message,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Restore failed: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Preview backup contents
     */
    public function preview($id)
    {
        try {
            $user = Auth::user();
            $account = $user->accounts()->firstOrFail();

            $backup = Backup::where('account_id', $account->id)
                ->where('id', $id)
                ->firstOrFail();

            $preview = $this->restoreService->previewBackup($backup);

            return response()->json([
                'success' => true,
                'preview' => $preview,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to preview backup: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Verify backup integrity
     */
    public function verify($id)
    {
        try {
            $user = Auth::user();
            $account = $user->accounts()->firstOrFail();

            $backup = Backup::where('account_id', $account->id)
                ->where('id', $id)
                ->firstOrFail();

            $verified = $this->backupService->verifyBackup($backup);

            return response()->json([
                'success' => $verified,
                'message' => $verified ? 'Backup verified successfully' : 'Backup verification failed',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Verification failed: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Manage backup schedules
     */
    public function schedules()
    {
        $user = Auth::user();
        $account = $user->accounts()->firstOrFail();

        $schedules = BackupSchedule::where('account_id', $account->id)
            ->with(['encryption', 'rotation'])
            ->get();

        $destinations = BackupDestination::where('account_id', $account->id)
            ->orWhere('is_system_wide', true)
            ->get();

        $encryptionKeys = BackupEncryption::where('account_id', $account->id)
            ->orWhere('is_system_wide', true)
            ->get();

        $rotationPolicies = BackupRotation::where('account_id', $account->id)
            ->orWhere('is_system_wide', true)
            ->get();

        return view('user.backups.schedules', compact(
            'schedules',
            'destinations',
            'encryptionKeys',
            'rotationPolicies',
            'account'
        ));
    }

    /**
     * Store new schedule
     */
    public function storeSchedule(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'frequency' => 'required|in:daily,weekly,monthly',
            'backup_type' => 'required|in:full,incremental',
            'backup_time' => 'required|date_format:H:i',
            'include_files' => 'boolean',
            'include_databases' => 'boolean',
            'include_emails' => 'boolean',
            'is_encrypted' => 'boolean',
            'encryption_id' => 'required_if:is_encrypted,true|exists:vp_backup_encryption,id',
            'destination_ids' => 'nullable|array',
            'rotation_id' => 'nullable|exists:vp_backup_rotations,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $user = Auth::user();
            $account = $user->accounts()->firstOrFail();

            $schedule = BackupSchedule::create(array_merge($request->all(), [
                'account_id' => $account->id,
                'is_system_wide' => false,
                'is_active' => true,
            ]));

            $schedule->calculateNextRun();

            return response()->json([
                'success' => true,
                'message' => 'Backup schedule created successfully',
                'schedule' => $schedule,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create schedule: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update schedule
     */
    public function updateSchedule(Request $request, $id)
    {
        $user = Auth::user();
        $account = $user->accounts()->firstOrFail();

        $schedule = BackupSchedule::where('account_id', $account->id)
            ->where('id', $id)
            ->firstOrFail();

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'frequency' => 'required|in:daily,weekly,monthly',
            'backup_type' => 'required|in:full,incremental',
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
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update schedule: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Delete schedule
     */
    public function deleteSchedule($id)
    {
        try {
            $user = Auth::user();
            $account = $user->accounts()->firstOrFail();

            $schedule = BackupSchedule::where('account_id', $account->id)
                ->where('id', $id)
                ->firstOrFail();

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
     * Manage remote destinations
     */
    public function destinations()
    {
        $user = Auth::user();
        $account = $user->accounts()->firstOrFail();

        $destinations = BackupDestination::where('account_id', $account->id)
            ->get();

        return view('user.backups.destinations', compact('destinations', 'account'));
    }

    /**
     * Store new destination
     */
    public function storeDestination(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'type' => 'required|in:ftp,sftp,ssh,local',
            'hostname' => 'required_unless:type,local',
            'port' => 'nullable|integer',
            'username' => 'required_unless:type,local',
            'password' => 'required_if:type,ftp',
            'path' => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $user = Auth::user();
            $account = $user->accounts()->firstOrFail();

            $destination = BackupDestination::create(array_merge($request->all(), [
                'account_id' => $account->id,
                'is_system_wide' => false,
            ]));

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
            $user = Auth::user();
            $account = $user->accounts()->firstOrFail();

            $destination = BackupDestination::where('account_id', $account->id)
                ->where('id', $id)
                ->firstOrFail();

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
            $user = Auth::user();
            $account = $user->accounts()->firstOrFail();

            $destination = BackupDestination::where('account_id', $account->id)
                ->where('id', $id)
                ->firstOrFail();

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
}
