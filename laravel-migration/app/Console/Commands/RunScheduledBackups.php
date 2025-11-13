<?php

namespace App\Console\Commands;

use App\Models\BackupSchedule;
use App\Services\BackupService;
use Illuminate\Console\Command;

class RunScheduledBackups extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'virpanel:backup {--schedule-id= : Run specific schedule by ID}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Run scheduled backups for VirPanel';

    protected $backupService;

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct(BackupService $backupService)
    {
        parent::__construct();
        $this->backupService = $backupService;
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $this->info('VirPanel Backup System - Running scheduled backups...');

        if ($scheduleId = $this->option('schedule-id')) {
            // Run specific schedule
            $schedule = BackupSchedule::find($scheduleId);

            if (!$schedule) {
                $this->error("Schedule #{$scheduleId} not found");
                return 1;
            }

            if (!$schedule->is_active) {
                $this->error("Schedule #{$scheduleId} is not active");
                return 1;
            }

            $this->runSchedule($schedule);
            return 0;
        }

        // Run all due schedules
        $schedules = BackupSchedule::where('is_active', true)
            ->where(function ($query) {
                $query->whereNull('next_run_at')
                    ->orWhere('next_run_at', '<=', now());
            })
            ->with(['account', 'encryption', 'rotation'])
            ->get();

        if ($schedules->isEmpty()) {
            $this->info('No schedules are due to run at this time');
            return 0;
        }

        $this->info("Found {$schedules->count()} schedule(s) to run");

        $successful = 0;
        $failed = 0;

        foreach ($schedules as $schedule) {
            $result = $this->runSchedule($schedule);

            if ($result) {
                $successful++;
            } else {
                $failed++;
            }
        }

        $this->newLine();
        $this->info("Backup run complete:");
        $this->info("  Successful: {$successful}");

        if ($failed > 0) {
            $this->error("  Failed: {$failed}");
        }

        return 0;
    }

    /**
     * Run a specific backup schedule
     */
    protected function runSchedule(BackupSchedule $schedule): bool
    {
        $this->newLine();
        $this->line("Running schedule: {$schedule->name} (ID: {$schedule->id})");

        // Determine account
        if ($schedule->is_system_wide) {
            $this->info('  Scope: System-wide');
            // For system-wide backups, we could backup all accounts or system files
            // For now, we'll skip system-wide and focus on account-specific
            $this->warn('  Skipping: System-wide backups not yet implemented');
            return false;
        } else {
            $account = $schedule->account;

            if (!$account) {
                $this->error('  Error: Account not found');
                $schedule->markAsRun(false);
                return false;
            }

            $this->info("  Account: {$account->username} ({$account->domain})");
        }

        $this->info("  Type: " . ucfirst($schedule->backup_type));

        try {
            // Create backup
            $startTime = microtime(true);

            if ($schedule->backup_type === 'full') {
                $backup = $this->backupService->createFullBackup($account, $schedule);
            } elseif ($schedule->backup_type === 'incremental') {
                $backup = $this->backupService->createIncrementalBackup($account, $schedule);
            } else {
                // For files or databases only
                $backup = $this->backupService->createFullBackup($account, $schedule);
            }

            $duration = round(microtime(true) - $startTime, 2);

            $this->info("  Backup created: {$backup->filename}");
            $this->info("  Size: {$backup->human_size}");
            $this->info("  Duration: {$duration}s");

            if ($backup->is_encrypted) {
                $this->info("  Encryption: {$backup->encryption_method}");
            }

            if ($backup->is_remote) {
                $this->info("  Uploaded to " . count($backup->remote_locations) . " remote destination(s)");
            }

            // Mark schedule as successfully run
            $schedule->markAsRun(true);

            $this->info('  Status: Success');

            return true;
        } catch (\Exception $e) {
            $this->error('  Status: Failed');
            $this->error('  Error: ' . $e->getMessage());

            // Mark schedule as failed
            $schedule->markAsRun(false);

            return false;
        }
    }
}
