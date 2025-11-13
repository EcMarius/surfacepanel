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
        // Modal.com Serverless Platform Integration
        // Modal Configuration (API credentials and workspace settings)
        Schema::create('vp_modal_config', function (Blueprint $table) {
            $table->id();
            $table->string('api_key')->nullable();
            $table->string('api_secret')->nullable();
            $table->string('workspace_name')->nullable();
            $table->string('workspace_id')->nullable();
            $table->boolean('is_enabled')->default(false);
            $table->json('default_settings')->nullable(); // Default environment settings
            $table->timestamps();
        });

        // Modal Functions (Deployed serverless functions)
        Schema::create('vp_modal_functions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained('vp_accounts')->onDelete('cascade');
            $table->string('function_name');
            $table->string('function_id')->nullable(); // Modal function ID
            $table->text('description')->nullable();
            $table->enum('runtime', ['python3.9', 'python3.10', 'python3.11', 'python3.12'])->default('python3.11');
            $table->text('code')->nullable(); // Function code
            $table->string('entrypoint')->default('main'); // Entry function name
            $table->json('requirements')->nullable(); // Python dependencies
            $table->json('environment_variables')->nullable();
            $table->json('secrets')->nullable(); // Encrypted secrets
            $table->boolean('gpu_enabled')->default(false);
            $table->string('gpu_type')->nullable(); // e.g., 'A100', 'T4', 'A10G'
            $table->integer('gpu_count')->default(0);
            $table->integer('cpu_count')->default(1);
            $table->integer('memory_mb')->default(1024);
            $table->integer('timeout')->default(300); // seconds
            $table->string('endpoint_url')->nullable(); // Public endpoint if exposed
            $table->enum('status', ['draft', 'deploying', 'active', 'failed', 'stopped'])->default('draft');
            $table->text('deployment_error')->nullable();
            $table->timestamp('last_deployed_at')->nullable();
            $table->string('version')->default('1.0.0');
            $table->timestamps();

            $table->index('account_id');
            $table->index('status');
            $table->unique(['account_id', 'function_name']);
        });

        // Modal Jobs (Scheduled serverless jobs - cron-like)
        Schema::create('vp_modal_jobs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained('vp_accounts')->onDelete('cascade');
            $table->foreignId('function_id')->nullable()->constrained('vp_modal_functions')->onDelete('cascade');
            $table->string('job_name');
            $table->string('job_id')->nullable(); // Modal job ID
            $table->text('description')->nullable();
            $table->string('schedule'); // Cron syntax
            $table->json('parameters')->nullable(); // Job parameters
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_run_at')->nullable();
            $table->timestamp('next_run_at')->nullable();
            $table->enum('last_status', ['success', 'failed', 'timeout', 'pending'])->nullable();
            $table->text('last_error')->nullable();
            $table->integer('success_count')->default(0);
            $table->integer('failure_count')->default(0);
            $table->timestamps();

            $table->index('account_id');
            $table->index('function_id');
            $table->index('is_active');
            $table->index('next_run_at');
        });

        // Modal Usage Tracking (Billing and resource usage)
        Schema::create('vp_modal_usage', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained('vp_accounts')->onDelete('cascade');
            $table->foreignId('function_id')->nullable()->constrained('vp_modal_functions')->onDelete('set null');
            $table->date('usage_date');
            $table->integer('total_invocations')->default(0);
            $table->integer('successful_invocations')->default(0);
            $table->integer('failed_invocations')->default(0);
            $table->bigInteger('total_execution_time_ms')->default(0); // Total execution time in milliseconds
            $table->bigInteger('total_cpu_time_ms')->default(0);
            $table->bigInteger('total_gpu_time_ms')->default(0);
            $table->bigInteger('total_memory_mb_seconds')->default(0); // Memory usage over time
            $table->decimal('estimated_cost', 10, 4)->default(0); // USD
            $table->json('detailed_metrics')->nullable(); // Additional metrics
            $table->timestamps();

            $table->unique(['account_id', 'function_id', 'usage_date']);
            $table->index('account_id');
            $table->index('function_id');
            $table->index('usage_date');
        });

        // Modal Function Versions (Version history and rollback)
        Schema::create('vp_modal_function_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('function_id')->constrained('vp_modal_functions')->onDelete('cascade');
            $table->string('version');
            $table->text('code');
            $table->json('requirements')->nullable();
            $table->json('environment_variables')->nullable();
            $table->json('configuration')->nullable(); // GPU, CPU, memory settings
            $table->text('changelog')->nullable();
            $table->boolean('is_active')->default(false);
            $table->timestamp('deployed_at')->nullable();
            $table->timestamps();

            $table->index('function_id');
            $table->index('is_active');
        });

        // Modal Execution Logs (Function execution history)
        Schema::create('vp_modal_execution_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained('vp_accounts')->onDelete('cascade');
            $table->foreignId('function_id')->constrained('vp_modal_functions')->onDelete('cascade');
            $table->string('execution_id')->nullable(); // Modal execution ID
            $table->timestamp('started_at');
            $table->timestamp('completed_at')->nullable();
            $table->integer('duration_ms')->default(0);
            $table->enum('status', ['running', 'success', 'failed', 'timeout'])->default('running');
            $table->text('input')->nullable();
            $table->text('output')->nullable();
            $table->text('error_message')->nullable();
            $table->text('logs')->nullable(); // stdout/stderr logs
            $table->integer('memory_used_mb')->default(0);
            $table->timestamps();

            $table->index('account_id');
            $table->index('function_id');
            $table->index('status');
            $table->index('started_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vp_modal_execution_logs');
        Schema::dropIfExists('vp_modal_function_versions');
        Schema::dropIfExists('vp_modal_usage');
        Schema::dropIfExists('vp_modal_jobs');
        Schema::dropIfExists('vp_modal_functions');
        Schema::dropIfExists('vp_modal_config');
    }
};
