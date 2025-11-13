<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\GPUAllocation;
use App\Models\GPUContainer;
use App\Models\GPUUsage;
use App\Models\MLFramework;
use App\Services\JupyterService;
use App\Services\GPUDetectionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class GPUController extends Controller
{
    protected $jupyterService;
    protected $gpuDetection;

    public function __construct(
        JupyterService $jupyterService,
        GPUDetectionService $gpuDetection
    ) {
        $this->middleware(['panel.detector', 'auth.user']);
        $this->jupyterService = $jupyterService;
        $this->gpuDetection = $gpuDetection;
    }

    /**
     * Display user GPU dashboard
     */
    public function index()
    {
        $user = Auth::user();
        $account = $user->accounts()->first();

        if (!$account) {
            return redirect()->back()->with('error', 'No account found');
        }

        $allocations = GPUAllocation::where('account_id', $account->id)
            ->where('status', 'active')
            ->with(['gpuDevice'])
            ->get();

        $containers = GPUContainer::where('account_id', $account->id)
            ->with(['allocation.gpuDevice', 'framework'])
            ->get();

        $frameworks = MLFramework::where('status', 'available')->get();

        // Get recent usage stats
        $recentUsage = GPUUsage::where('account_id', $account->id)
            ->orderBy('recorded_at', 'desc')
            ->take(100)
            ->get();

        return view('user.gpu.index', compact(
            'allocations',
            'containers',
            'frameworks',
            'recentUsage'
        ));
    }

    /**
     * View allocated GPUs details
     */
    public function viewAllocation($id)
    {
        $user = Auth::user();
        $account = $user->accounts()->first();

        $allocation = GPUAllocation::where('account_id', $account->id)
            ->where('id', $id)
            ->with(['gpuDevice'])
            ->firstOrFail();

        // Get usage history
        $usageHistory = GPUUsage::where('allocation_id', $allocation->id)
            ->orderBy('recorded_at', 'desc')
            ->take(100)
            ->get();

        return view('user.gpu.allocation', compact('allocation', 'usageHistory'));
    }

    /**
     * Get GPU utilization monitoring data
     */
    public function getUtilization(Request $request, $id)
    {
        try {
            $user = Auth::user();
            $account = $user->accounts()->first();

            $allocation = GPUAllocation::where('account_id', $account->id)
                ->where('id', $id)
                ->with(['gpuDevice'])
                ->firstOrFail();

            $gpu = $allocation->gpuDevice;
            $stats = $this->gpuDetection->getGPUStats($gpu->uuid);

            if (!$stats) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to get GPU statistics',
                ], 400);
            }

            // Record usage
            GPUUsage::create([
                'allocation_id' => $allocation->id,
                'account_id' => $account->id,
                'memory_used' => $stats['memory_used'],
                'utilization' => $stats['utilization'],
                'temperature' => $stats['temperature'],
                'power_draw' => $stats['power_draw'],
                'processes_count' => 0,
                'recorded_at' => now(),
            ]);

            return response()->json([
                'success' => true,
                'stats' => $stats,
                'allocation' => [
                    'memory_allocated' => $allocation->memory_allocated,
                    'compute_percentage' => $allocation->compute_percentage,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get utilization: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Display Jupyter Notebook/Lab management
     */
    public function jupyter()
    {
        $user = Auth::user();
        $account = $user->accounts()->first();

        $allocations = GPUAllocation::where('account_id', $account->id)
            ->where('status', 'active')
            ->with(['gpuDevice'])
            ->get();

        $containers = GPUContainer::where('account_id', $account->id)
            ->whereIn('type', ['jupyter', 'jupyterlab'])
            ->with(['allocation.gpuDevice', 'framework'])
            ->get();

        $frameworks = MLFramework::where('status', 'available')->get();

        return view('user.gpu.jupyter', compact('allocations', 'containers', 'frameworks'));
    }

    /**
     * Launch Jupyter Notebook/Lab
     */
    public function launchJupyter(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'allocation_id' => 'required|exists:vp_gpu_allocations,id',
            'type' => 'required|in:jupyter,jupyterlab',
            'framework_id' => 'nullable|exists:vp_ml_frameworks,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $user = Auth::user();
            $account = $user->accounts()->first();

            $allocation = GPUAllocation::where('account_id', $account->id)
                ->where('id', $request->allocation_id)
                ->where('status', 'active')
                ->firstOrFail();

            $framework = null;
            if ($request->framework_id) {
                $framework = MLFramework::findOrFail($request->framework_id);
            }

            $container = $this->jupyterService->launchJupyter(
                $allocation,
                $request->type,
                $framework
            );

            return response()->json([
                'success' => true,
                'message' => 'Jupyter launched successfully',
                'container' => $container,
                'access_url' => $container->getJupyterUrl(),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to launch Jupyter: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Stop Jupyter container
     */
    public function stopContainer(Request $request, $id)
    {
        try {
            $user = Auth::user();
            $account = $user->accounts()->first();

            $container = GPUContainer::where('account_id', $account->id)
                ->where('id', $id)
                ->firstOrFail();

            $result = $this->jupyterService->stopContainer($container);

            if ($result) {
                return response()->json([
                    'success' => true,
                    'message' => 'Container stopped successfully',
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => 'Failed to stop container',
            ], 400);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to stop container: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Start stopped container
     */
    public function startContainer(Request $request, $id)
    {
        try {
            $user = Auth::user();
            $account = $user->accounts()->first();

            $container = GPUContainer::where('account_id', $account->id)
                ->where('id', $id)
                ->firstOrFail();

            $result = $this->jupyterService->startContainer($container);

            if ($result) {
                return response()->json([
                    'success' => true,
                    'message' => 'Container started successfully',
                    'access_url' => $container->getJupyterUrl(),
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => 'Failed to start container',
            ], 400);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to start container: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Remove container
     */
    public function removeContainer(Request $request, $id)
    {
        try {
            $user = Auth::user();
            $account = $user->accounts()->first();

            $container = GPUContainer::where('account_id', $account->id)
                ->where('id', $id)
                ->firstOrFail();

            $result = $this->jupyterService->removeContainer($container);

            if ($result) {
                return response()->json([
                    'success' => true,
                    'message' => 'Container removed successfully',
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => 'Failed to remove container',
            ], 400);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to remove container: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get container logs
     */
    public function getContainerLogs(Request $request, $id)
    {
        try {
            $user = Auth::user();
            $account = $user->accounts()->first();

            $container = GPUContainer::where('account_id', $account->id)
                ->where('id', $id)
                ->firstOrFail();

            $lines = $request->input('lines', 100);
            $logs = $this->jupyterService->getContainerLogs($container, $lines);

            return response()->json([
                'success' => true,
                'logs' => $logs,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get logs: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get container stats
     */
    public function getContainerStats(Request $request, $id)
    {
        try {
            $user = Auth::user();
            $account = $user->accounts()->first();

            $container = GPUContainer::where('account_id', $account->id)
                ->where('id', $id)
                ->firstOrFail();

            $stats = $this->jupyterService->getContainerStats($container);

            return response()->json([
                'success' => true,
                'stats' => $stats,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get stats: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Deploy custom container with GPU
     */
    public function deployContainer(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'allocation_id' => 'required|exists:vp_gpu_allocations,id',
            'image' => 'required|string',
            'type' => 'required|in:jupyter,jupyterlab,vscode,custom',
            'framework_id' => 'nullable|exists:vp_ml_frameworks,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $user = Auth::user();
            $account = $user->accounts()->first();

            $allocation = GPUAllocation::where('account_id', $account->id)
                ->where('id', $request->allocation_id)
                ->where('status', 'active')
                ->firstOrFail();

            $framework = null;
            if ($request->framework_id) {
                $framework = MLFramework::findOrFail($request->framework_id);
            }

            $container = $this->jupyterService->launchJupyter(
                $allocation,
                $request->type,
                $framework
            );

            return response()->json([
                'success' => true,
                'message' => 'Container deployed successfully',
                'container' => $container,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to deploy container: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Environment management
     */
    public function environments()
    {
        $user = Auth::user();
        $account = $user->accounts()->first();

        $containers = GPUContainer::where('account_id', $account->id)
            ->with(['allocation.gpuDevice', 'framework'])
            ->get();

        return view('user.gpu.environments', compact('containers'));
    }
}
