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
        // Add 2FA columns to users table
        Schema::table('vp_users', function (Blueprint $table) {
            $table->boolean('two_factor_enabled')->default(false)->after('remember_token');
            $table->text('two_factor_secret')->nullable()->after('two_factor_enabled');
            $table->timestamp('two_factor_confirmed_at')->nullable()->after('two_factor_secret');

            $table->index('two_factor_enabled');
        });

        // Two-Factor Authentication Settings
        Schema::create('vp_two_factor_auth', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('vp_users')->onDelete('cascade');
            $table->string('type')->default('totp'); // totp, sms, email
            $table->boolean('is_enabled')->default(false);
            $table->text('secret')->nullable(); // Encrypted TOTP secret
            $table->timestamp('enabled_at')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->string('last_used_ip')->nullable();
            $table->timestamps();

            $table->index('user_id');
            $table->index('is_enabled');
        });

        // Two-Factor Backup Codes
        Schema::create('vp_backup_codes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('vp_users')->onDelete('cascade');
            $table->string('code', 64); // Hashed backup code
            $table->boolean('is_used')->default(false);
            $table->timestamp('used_at')->nullable();
            $table->string('used_ip')->nullable();
            $table->timestamps();

            $table->index('user_id');
            $table->index('is_used');
        });

        // Two-Factor Recovery Requests
        Schema::create('vp_two_factor_recovery', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('vp_users')->onDelete('cascade');
            $table->string('token', 64)->unique();
            $table->string('ip_address');
            $table->text('user_agent')->nullable();
            $table->enum('status', ['pending', 'approved', 'denied', 'expired'])->default('pending');
            $table->timestamp('expires_at');
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('vp_users')->onDelete('set null');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('token');
            $table->index('user_id');
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vp_two_factor_recovery');
        Schema::dropIfExists('vp_backup_codes');
        Schema::dropIfExists('vp_two_factor_auth');

        Schema::table('vp_users', function (Blueprint $table) {
            $table->dropIndex(['two_factor_enabled']);
            $table->dropColumn(['two_factor_enabled', 'two_factor_secret', 'two_factor_confirmed_at']);
        });
    }
};
