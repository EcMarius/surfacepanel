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
        // GPU Devices (Detected NVIDIA GPUs)
        Schema::create('vp_gpu_devices', function (Blueprint $table) {
            $table->id();
            $table->string('uuid')->unique(); // GPU UUID from nvidia-smi
            $table->string('name'); // GPU model name (e.g., NVIDIA Tesla V100)
            $table->integer('index'); // GPU index (0, 1, 2, etc.)
            $table->integer('memory_total')->default(0); // Total memory in MB
            $table->integer('memory_free')->default(0); // Free memory in MB
            $table->integer('memory_used')->default(0); // Used memory in MB
            $table->string('driver_version')->nullable(); // NVIDIA driver version
            $table->string('cuda_version')->nullable(); // CUDA version
            $table->integer('temperature')->default(0); // GPU temperature in Celsius
            $table->integer('power_draw')->default(0); // Power draw in watts
            $table->integer('power_limit')->default(0); // Power limit in watts
            $table->integer('utilization')->default(0); // GPU utilization percentage
            $table->enum('status', ['available', 'allocated', 'maintenance', 'offline'])->default('available');
            $table->text('pci_bus_id')->nullable(); // PCI bus ID
            $table->timestamp('last_detected_at')->nullable();
            $table->timestamps();

            $table->index('uuid');
            $table->index('status');
            $table->index('index');
        });

        // GPU Allocations (Per-account GPU allocation)
        Schema::create('vp_gpu_allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained('vp_accounts')->onDelete('cascade');
            $table->foreignId('gpu_device_id')->constrained('vp_gpu_devices')->onDelete('cascade');
            $table->integer('memory_allocated')->default(0); // Allocated memory in MB
            $table->decimal('compute_percentage', 5, 2)->default(100.00); // Percentage of GPU compute allocated
            $table->enum('allocation_mode', ['exclusive', 'shared', 'mig'])->default('exclusive');
            // MIG = Multi-Instance GPU (for A100/H100)
            $table->string('mig_profile')->nullable(); // e.g., '1g.5gb', '2g.10gb', '3g.20gb'
            $table->enum('status', ['active', 'suspended', 'terminated'])->default('active');
            $table->timestamp('allocated_at');
            $table->timestamp('expires_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('account_id');
            $table->index('gpu_device_id');
            $table->index('status');
        });

        // GPU Usage Tracking
        Schema::create('vp_gpu_usage', function (Blueprint $table) {
            $table->id();
            $table->foreignId('allocation_id')->constrained('vp_gpu_allocations')->onDelete('cascade');
            $table->foreignId('account_id')->constrained('vp_accounts')->onDelete('cascade');
            $table->integer('memory_used')->default(0); // Memory used in MB
            $table->integer('utilization')->default(0); // GPU utilization percentage
            $table->integer('temperature')->default(0); // Temperature in Celsius
            $table->integer('power_draw')->default(0); // Power draw in watts
            $table->integer('processes_count')->default(0); // Number of processes running
            $table->json('processes_info')->nullable(); // Detailed process information
            $table->timestamp('recorded_at');
            $table->timestamps();

            $table->index('allocation_id');
            $table->index('account_id');
            $table->index('recorded_at');
        });

        // ML Frameworks (Installed ML/AI frameworks)
        Schema::create('vp_ml_frameworks', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // e.g., 'PyTorch', 'TensorFlow', 'JAX'
            $table->string('version'); // Framework version
            $table->string('cuda_version')->nullable(); // Compatible CUDA version
            $table->string('python_version'); // Python version requirement
            $table->text('description')->nullable();
            $table->string('docker_image')->nullable(); // Docker image if containerized
            $table->json('dependencies')->nullable(); // Additional dependencies
            $table->enum('status', ['available', 'installing', 'failed', 'deprecated'])->default('available');
            $table->boolean('is_default')->default(false);
            $table->timestamp('installed_at')->nullable();
            $table->timestamps();

            $table->unique(['name', 'version']);
            $table->index('name');
            $table->index('status');
        });

        // GPU Containers (Docker containers with GPU access)
        Schema::create('vp_gpu_containers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained('vp_accounts')->onDelete('cascade');
            $table->foreignId('allocation_id')->constrained('vp_gpu_allocations')->onDelete('cascade');
            $table->foreignId('framework_id')->nullable()->constrained('vp_ml_frameworks')->onDelete('set null');
            $table->string('container_name')->unique();
            $table->string('container_id')->nullable(); // Docker container ID
            $table->string('image'); // Docker image
            $table->integer('port')->nullable(); // Exposed port for Jupyter/services
            $table->string('token')->nullable(); // Access token for Jupyter
            $table->enum('type', ['jupyter', 'jupyterlab', 'vscode', 'custom'])->default('jupyter');
            $table->enum('status', ['running', 'stopped', 'created', 'failed'])->default('created');
            $table->json('environment_vars')->nullable(); // Environment variables
            $table->json('volumes')->nullable(); // Volume mounts
            $table->text('startup_command')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('stopped_at')->nullable();
            $table->timestamps();

            $table->index('account_id');
            $table->index('allocation_id');
            $table->index('status');
        });

        // CUDA Toolkit Installations
        Schema::create('vp_cuda_installations', function (Blueprint $table) {
            $table->id();
            $table->string('version'); // CUDA version (e.g., '12.2', '11.8')
            $table->string('install_path'); // Installation path
            $table->string('driver_version'); // Compatible driver version
            $table->boolean('has_cudnn')->default(false); // cuDNN installed
            $table->string('cudnn_version')->nullable(); // cuDNN version
            $table->boolean('has_nccl')->default(false); // NCCL installed (multi-GPU)
            $table->string('nccl_version')->nullable();
            $table->boolean('has_tensorrt')->default(false); // TensorRT installed
            $table->string('tensorrt_version')->nullable();
            $table->boolean('is_default')->default(false);
            $table->enum('status', ['active', 'installing', 'failed'])->default('active');
            $table->timestamp('installed_at')->nullable();
            $table->timestamps();

            $table->unique('version');
            $table->index('is_default');
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vp_cuda_installations');
        Schema::dropIfExists('vp_gpu_containers');
        Schema::dropIfExists('vp_ml_frameworks');
        Schema::dropIfExists('vp_gpu_usage');
        Schema::dropIfExists('vp_gpu_allocations');
        Schema::dropIfExists('vp_gpu_devices');
    }
};
