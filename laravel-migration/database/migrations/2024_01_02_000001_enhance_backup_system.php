<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Backup Encryption (Encryption key management)
        Schema::create('vp_backup_encryption', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->nullable()->constrained('vp_accounts')->onDelete('cascade');
            $table->string('name');
            $table->enum('method', ['aes-256-cbc', 'aes-128-cbc', 'aes-256-gcm'])->default('aes-256-cbc');
            $table->text('encryption_key'); // Encrypted storage of the key
            $table->text('iv')->nullable(); // Initialization vector
            $table->boolean('is_active')->default(true);
            $table->boolean('is_default')->default(false);
            $table->boolean('is_system_wide')->default(false);
            $table->timestamps();

            $table->index('account_id');
            $table->index('is_active');
            $table->index('is_default');
            $table->index('is_system_wide');
        });

        // Backup Destinations (Remote storage configurations)
        Schema::create('vp_backup_destinations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->nullable()->constrained('vp_accounts')->onDelete('cascade');
            $table->string('name');
            $table->enum('type', ['s3', 'ftp', 'sftp', 'ssh', 'local'])->default('local');
            $table->text('hostname')->nullable();
            $table->integer('port')->nullable();
            $table->string('username')->nullable();
            $table->text('password')->nullable(); // Encrypted
            $table->text('path')->nullable(); // Remote path or S3 bucket
            $table->text('access_key')->nullable(); // For S3
            $table->text('secret_key')->nullable(); // For S3
            $table->string('region')->nullable(); // For S3
            $table->text('private_key')->nullable(); // For SSH/SFTP
            $table->boolean('is_active')->default(true);
            $table->boolean('is_system_wide')->default(false); // Available to all accounts
            $table->timestamp('last_tested_at')->nullable();
            $table->enum('connection_status', ['untested', 'success', 'failed'])->default('untested');
            $table->text('connection_error')->nullable();
            $table->timestamps();

            $table->index('account_id');
            $table->index('type');
            $table->index('is_active');
            $table->index('is_system_wide');
        });

        // Backup Rotations (Retention policies)
        Schema::create('vp_backup_rotations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->nullable()->constrained('vp_accounts')->onDelete('cascade');
            $table->string('name');
            $table->integer('keep_daily')->default(7); // Keep daily backups for X days
            $table->integer('keep_weekly')->default(4); // Keep weekly backups for X weeks
            $table->integer('keep_monthly')->default(6); // Keep monthly backups for X months
            $table->integer('keep_yearly')->default(1); // Keep yearly backups for X years
            $table->integer('max_backups')->nullable(); // Maximum total backups to keep
            $table->bigInteger('max_storage_mb')->nullable(); // Maximum storage in MB
            $table->boolean('delete_from_remote')->default(true); // Delete from remote when rotating
            $table->boolean('is_active')->default(true);
            $table->boolean('is_system_wide')->default(false);
            $table->timestamps();

            $table->index('account_id');
            $table->index('is_active');
            $table->index('is_system_wide');
        });

        // Backup Schedules (Automated backup configurations)
        Schema::create('vp_backup_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->nullable()->constrained('vp_accounts')->onDelete('cascade');
            $table->string('name');
            $table->enum('frequency', ['daily', 'weekly', 'monthly', 'custom'])->default('daily');
            $table->string('cron_expression')->nullable(); // For custom schedules
            $table->enum('backup_type', ['full', 'incremental', 'files', 'databases'])->default('full');
            $table->time('backup_time')->default('02:00'); // Preferred time to run
            $table->boolean('include_files')->default(true);
            $table->boolean('include_databases')->default(true);
            $table->boolean('include_emails')->default(false);
            $table->boolean('is_encrypted')->default(false);
            $table->foreignId('encryption_id')->nullable()->constrained('vp_backup_encryption')->onDelete('set null');
            $table->json('destination_ids')->nullable(); // Array of destination IDs
            $table->foreignId('rotation_id')->nullable()->constrained('vp_backup_rotations')->onDelete('set null');
            $table->boolean('is_active')->default(true);
            $table->boolean('is_system_wide')->default(false); // For admin-created system backups
            $table->timestamp('last_run_at')->nullable();
            $table->timestamp('next_run_at')->nullable();
            $table->integer('successful_runs')->default(0);
            $table->integer('failed_runs')->default(0);
            $table->timestamps();

            $table->index('account_id');
            $table->index('is_active');
            $table->index('next_run_at');
            $table->index('is_system_wide');
        });

        // Enhance existing vp_backups table with new columns
        Schema::table('vp_backups', function (Blueprint $table) {
            $table->foreignId('schedule_id')->nullable()->after('account_id')->constrained('vp_backup_schedules')->onDelete('set null');
            $table->string('path')->nullable()->after('filename'); // Local path
            $table->boolean('is_encrypted')->default(false)->after('error_message');
            $table->string('encryption_method')->nullable()->after('is_encrypted'); // e.g., 'AES-256'
            $table->boolean('is_remote')->default(false)->after('encryption_method');
            $table->json('remote_locations')->nullable()->after('is_remote'); // Array of destination IDs where uploaded
            $table->string('checksum')->nullable()->after('remote_locations'); // MD5/SHA256 hash for verification
            $table->timestamp('started_at')->nullable()->after('checksum');
            $table->timestamp('completed_at')->nullable()->after('started_at');
            $table->timestamp('expires_at')->nullable()->after('completed_at'); // For rotation

            // Modify existing columns
            $table->foreignId('account_id')->nullable()->change();
            $table->dropColumn('type');
            $table->dropColumn('status');
        });

        // Re-add type and status columns with updated enums
        Schema::table('vp_backups', function (Blueprint $table) {
            $table->enum('type', ['full', 'incremental', 'files', 'databases', 'email'])->default('full')->after('path');
            $table->enum('status', ['pending', 'processing', 'completed', 'failed', 'uploaded', 'verified'])->default('pending')->after('size');

            $table->index('status');
            $table->index('type');
            $table->index('created_at');
            $table->index('expires_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Remove new columns from vp_backups
        Schema::table('vp_backups', function (Blueprint $table) {
            $table->dropForeign(['schedule_id']);
            $table->dropColumn([
                'schedule_id',
                'path',
                'is_encrypted',
                'encryption_method',
                'is_remote',
                'remote_locations',
                'checksum',
                'started_at',
                'completed_at',
                'expires_at'
            ]);
            $table->dropIndex(['status']);
            $table->dropIndex(['type']);
            $table->dropIndex(['created_at']);
            $table->dropIndex(['expires_at']);
        });

        // Drop new tables
        Schema::dropIfExists('vp_backup_schedules');
        Schema::dropIfExists('vp_backup_rotations');
        Schema::dropIfExists('vp_backup_destinations');
        Schema::dropIfExists('vp_backup_encryption');
    }
};
