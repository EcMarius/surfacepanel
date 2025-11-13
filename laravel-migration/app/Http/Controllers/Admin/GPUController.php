<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\GPUDevice;
use App\Models\GPUAllocation;
use App\Models\MLFramework;
use App\Models\CUDAInstallation;
use App\Models\Account;
use App\Services\GPUDetectionService;
use App\Services\CUDAInstallerService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class GPUController extends Controller
{
    protected $gpuDetection;
    protected $cudaInstaller;

    public function __construct(
        GPUDetectionService $gpuDetection,
        CUDAInstallerService $cudaInstaller
    ) {
        $this->middleware(['panel.detector', 'auth.admin']);
        $this->gpuDetection = $gpuDetection;
        $this->cudaInstaller = $cudaInstaller;
    }

    /**
     * Display GPU management dashboard
     */
    public function index()
    {
        $gpus = GPUDevice::with(['allocations.account'])->get();
        $totalGPUs = $gpus->count();
        $availableGPUs = $gpus->where('status', 'available')->count();
        $allocatedGPUs = $gpus->where('status', 'allocated')->count();

        $allocations = GPUAllocation::with(['account', 'gpuDevice'])
            ->where('status', 'active')
            ->get();

        $driverInfo = $this->gpuDetection->getDriverInfo();
        $cudaInstallations = CUDAInstallation::where('status', 'active')->get();

        return view('admin.gpu.index', compact(
            'gpus',
            'totalGPUs',
            'availableGPUs',
            'allocatedGPUs',
            'allocations',
            'driverInfo',
            'cudaInstallations'
        ));
    }

    /**
     * Detect NVIDIA GPUs
     */
    public function detectGPUs(Request $request)
    {
        try {
            if (!$this->gpuDetection->isNvidiaSmiAvailable()) {
                return response()->json([
                    'success' => false,
                    'message' => 'nvidia-smi is not available. Please install NVIDIA drivers first.',
                ], 400);
            }

            $synced = $this->gpuDetection->syncGPUsToDatabase();

            return response()->json([
                'success' => true,
                'message' => "Detected and synced {$synced} GPU(s)",
                'gpus' => GPUDevice::all(),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to detect GPUs: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Refresh GPU statistics
     */
    public function refreshStats(Request $request, $id)
    {
        try {
            $gpu = GPUDevice::findOrFail($id);
            $stats = $this->gpuDetection->getGPUStats($gpu->uuid);

            if (!$stats) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to get GPU statistics',
                ], 400);
            }

            $gpu->update([
                'memory_total' => $stats['memory_total'],
                'memory_free' => $stats['memory_free'],
                'memory_used' => $stats['memory_used'],
                'temperature' => $stats['temperature'],
                'power_draw' => $stats['power_draw'],
                'utilization' => $stats['utilization'],
                'last_detected_at' => now(),
            ]);

            return response()->json([
                'success' => true,
                'gpu' => $gpu->fresh(),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to refresh GPU stats: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Display GPU allocations management
     */
    public function allocations()
    {
        $allocations = GPUAllocation::with(['account', 'gpuDevice'])->get();
        $availableGPUs = GPUDevice::where('status', 'available')->get();
        $accounts = Account::where('status', 'active')->get();

        return view('admin.gpu.allocations', compact('allocations', 'availableGPUs', 'accounts'));
    }

    /**
     * Create GPU allocation for account
     */
    public function allocateGPU(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'account_id' => 'required|exists:vp_accounts,id',
            'gpu_device_id' => 'required|exists:vp_gpu_devices,id',
            'memory_allocated' => 'required|integer|min:0',
            'compute_percentage' => 'required|numeric|min:0|max:100',
            'allocation_mode' => 'required|in:exclusive,shared,mig',
            'mig_profile' => 'nullable|string',
            'expires_at' => 'nullable|date',
            'notes' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $gpu = GPUDevice::findOrFail($request->gpu_device_id);

            // Check if GPU is available
            if ($request->allocation_mode === 'exclusive' && $gpu->status !== 'available') {
                return response()->json([
                    'success' => false,
                    'message' => 'GPU is not available for exclusive allocation',
                ], 400);
            }

            $allocation = GPUAllocation::create([
                'account_id' => $request->account_id,
                'gpu_device_id' => $request->gpu_device_id,
                'memory_allocated' => $request->memory_allocated,
                'compute_percentage' => $request->compute_percentage,
                'allocation_mode' => $request->allocation_mode,
                'mig_profile' => $request->mig_profile,
                'status' => 'active',
                'allocated_at' => now(),
                'expires_at' => $request->expires_at,
                'notes' => $request->notes,
            ]);

            // Update GPU status
            if ($request->allocation_mode === 'exclusive') {
                $gpu->update(['status' => 'allocated']);
            }

            return response()->json([
                'success' => true,
                'message' => 'GPU allocated successfully',
                'allocation' => $allocation->load(['account', 'gpuDevice']),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to allocate GPU: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Deallocate GPU from account
     */
    public function deallocateGPU(Request $request, $id)
    {
        try {
            $allocation = GPUAllocation::findOrFail($id);

            // Update GPU status
            if ($allocation->allocation_mode === 'exclusive') {
                $allocation->gpuDevice->update(['status' => 'available']);
            }

            $allocation->update([
                'status' => 'terminated',
            ]);

            return response()->json([
                'success' => true,
                'message' => 'GPU deallocated successfully',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to deallocate GPU: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Install CUDA Toolkit
     */
    public function installCUDA(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'version' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            // Check if already installed
            $existing = CUDAInstallation::where('version', $request->version)->first();
            if ($existing && $existing->status === 'active') {
                return response()->json([
                    'success' => false,
                    'message' => 'CUDA ' . $request->version . ' is already installed',
                ], 400);
            }

            // Create installation record
            $installation = CUDAInstallation::create([
                'version' => $request->version,
                'install_path' => '/usr/local/cuda-' . $request->version,
                'driver_version' => $this->gpuDetection->getDriverInfo()['driver_version'],
                'status' => 'installing',
            ]);

            // Start installation in background
            $result = $this->cudaInstaller->installCUDA($request->version);

            // Update installation record
            $installation->update([
                'status' => 'active',
                'installed_at' => now(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'CUDA installed successfully',
                'installation' => $installation,
            ]);
        } catch (\Exception $e) {
            if (isset($installation)) {
                $installation->update(['status' => 'failed']);
            }

            return response()->json([
                'success' => false,
                'message' => 'Failed to install CUDA: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Install cuDNN
     */
    public function installCuDNN(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'cuda_version' => 'required|string',
            'cudnn_version' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $installation = CUDAInstallation::where('version', $request->cuda_version)->first();

            if (!$installation) {
                return response()->json([
                    'success' => false,
                    'message' => 'CUDA ' . $request->cuda_version . ' is not installed',
                ], 400);
            }

            $result = $this->cudaInstaller->installCuDNN(
                $request->cuda_version,
                $request->cudnn_version
            );

            $installation->update([
                'has_cudnn' => true,
                'cudnn_version' => $request->cudnn_version,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'cuDNN installation initiated',
                'result' => $result,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to install cuDNN: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Manage ML Frameworks
     */
    public function frameworks()
    {
        $frameworks = MLFramework::all();
        $cudaInstallations = CUDAInstallation::where('status', 'active')->get();

        return view('admin.gpu.frameworks', compact('frameworks', 'cudaInstallations'));
    }

    /**
     * Add ML Framework
     */
    public function addFramework(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string',
            'version' => 'required|string',
            'cuda_version' => 'nullable|string',
            'python_version' => 'required|string',
            'description' => 'nullable|string',
            'docker_image' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $framework = MLFramework::create([
                'name' => $request->name,
                'version' => $request->version,
                'cuda_version' => $request->cuda_version,
                'python_version' => $request->python_version,
                'description' => $request->description,
                'docker_image' => $request->docker_image,
                'status' => 'available',
                'installed_at' => now(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Framework added successfully',
                'framework' => $framework,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to add framework: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * GPU monitoring dashboard
     */
    public function monitoring()
    {
        $gpus = GPUDevice::with(['allocations' => function ($query) {
            $query->where('status', 'active');
        }])->get();

        return view('admin.gpu.monitoring', compact('gpus'));
    }

    /**
     * Get GPU monitoring data (for AJAX)
     */
    public function getMonitoringData(Request $request)
    {
        try {
            $gpus = GPUDevice::all();
            $data = [];

            foreach ($gpus as $gpu) {
                $stats = $this->gpuDetection->getGPUStats($gpu->uuid);

                if ($stats) {
                    $data[] = [
                        'id' => $gpu->id,
                        'name' => $gpu->name,
                        'index' => $gpu->index,
                        'memory_used' => $stats['memory_used'],
                        'memory_total' => $stats['memory_total'],
                        'memory_percentage' => ($stats['memory_used'] / $stats['memory_total']) * 100,
                        'temperature' => $stats['temperature'],
                        'power_draw' => $stats['power_draw'],
                        'utilization' => $stats['utilization'],
                    ];
                }
            }

            return response()->json([
                'success' => true,
                'data' => $data,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get monitoring data: ' . $e->getMessage(),
            ], 500);
        }
    }
}
