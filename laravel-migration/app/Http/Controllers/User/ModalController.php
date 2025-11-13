<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\ModalConfig;
use App\Models\ModalFunction;
use App\Models\ModalJob;
use App\Models\ModalUsage;
use App\Models\ModalExecutionLog;
use App\Services\ModalService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;

class ModalController extends Controller
{
    protected $modalService;

    public function __construct(ModalService $modalService)
    {
        $this->middleware(['panel.detector', 'auth.user']);
        $this->modalService = $modalService;
    }

    /**
     * Display Modal.com dashboard
     */
    public function index()
    {
        $user = Auth::guard('user')->user();
        $account = $user->account;

        if (!$account) {
            abort(404, 'Account not found');
        }

        $config = ModalConfig::getInstance();

        // Check if Modal is enabled
        if (!$config->isActive()) {
            return view('user.modal.disabled', compact('config'));
        }

        // Get user's statistics
        $totalFunctions = ModalFunction::forAccount($account->id)->count();
        $activeFunctions = ModalFunction::forAccount($account->id)->active()->count();
        $totalJobs = ModalJob::forAccount($account->id)->count();
        $activeJobs = ModalJob::forAccount($account->id)->active()->count();

        // Get current month usage
        $stats = ModalUsage::getAccountStatistics($account->id, 30);

        // Get recent functions
        $recentFunctions = ModalFunction::forAccount($account->id)
            ->orderByDesc('created_at')
            ->limit(5)
            ->get();

        // Get recent executions
        $recentExecutions = ModalExecutionLog::forAccount($account->id)
            ->orderByDesc('started_at')
            ->limit(10)
            ->with('function')
            ->get();

        return view('user.modal.index', compact(
            'config',
            'account',
            'totalFunctions',
            'activeFunctions',
            'totalJobs',
            'activeJobs',
            'stats',
            'recentFunctions',
            'recentExecutions'
        ));
    }

    /**
     * List user's functions
     */
    public function functions(Request $request)
    {
        $user = Auth::guard('user')->user();
        $account = $user->account;

        $query = ModalFunction::forAccount($account->id)->orderByDesc('created_at');

        // Filters
        if ($request->has('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        if ($request->has('gpu_enabled')) {
            $query->where('gpu_enabled', $request->gpu_enabled);
        }

        $functions = $query->get();

        $runtimes = ModalConfig::getAvailableRuntimes();
        $gpuTypes = ModalConfig::getAvailableGPUTypes();

        return view('user.modal.functions', compact('functions', 'runtimes', 'gpuTypes'));
    }

    /**
     * Show function creation/deployment form
     */
    public function createFunction()
    {
        $runtimes = ModalConfig::getAvailableRuntimes();
        $gpuTypes = ModalConfig::getAvailableGPUTypes();

        return view('user.modal.deploy', compact('runtimes', 'gpuTypes'));
    }

    /**
     * Store a new function
     */
    public function storeFunction(Request $request)
    {
        $user = Auth::guard('user')->user();
        $account = $user->account;

        $validator = Validator::make($request->all(), [
            'function_name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'runtime' => 'required|in:python3.9,python3.10,python3.11,python3.12',
            'code' => 'required|string',
            'entrypoint' => 'required|string|max:255',
            'requirements' => 'nullable|array',
            'environment_variables' => 'nullable|array',
            'secrets' => 'nullable|array',
            'gpu_enabled' => 'required|boolean',
            'gpu_type' => 'nullable|required_if:gpu_enabled,true|string',
            'gpu_count' => 'nullable|required_if:gpu_enabled,true|integer|min:1|max:8',
            'cpu_count' => 'required|integer|min:1|max:32',
            'memory_mb' => 'required|integer|min:128|max:65536',
            'timeout' => 'required|integer|min:1|max:3600',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            // Check if function name already exists for this account
            $exists = ModalFunction::forAccount($account->id)
                ->where('function_name', $request->function_name)
                ->exists();

            if ($exists) {
                return response()->json([
                    'success' => false,
                    'message' => 'A function with this name already exists',
                ], 422);
            }

            // Create function
            $function = ModalFunction::create([
                'account_id' => $account->id,
                'function_name' => $request->function_name,
                'description' => $request->description,
                'runtime' => $request->runtime,
                'code' => $request->code,
                'entrypoint' => $request->entrypoint,
                'requirements' => $request->requirements,
                'environment_variables' => $request->environment_variables,
                'secrets' => $request->secrets,
                'gpu_enabled' => $request->gpu_enabled,
                'gpu_type' => $request->gpu_enabled ? $request->gpu_type : null,
                'gpu_count' => $request->gpu_enabled ? $request->gpu_count : 0,
                'cpu_count' => $request->cpu_count,
                'memory_mb' => $request->memory_mb,
                'timeout' => $request->timeout,
                'status' => 'draft',
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Function created successfully',
                'function_id' => $function->id,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create function: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Deploy a function to Modal.com
     */
    public function deployFunction($id)
    {
        $user = Auth::guard('user')->user();
        $account = $user->account;

        try {
            $function = ModalFunction::forAccount($account->id)->findOrFail($id);

            $result = $this->modalService->deployFunction($function);

            return response()->json($result);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Deployment failed: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update a function
     */
    public function updateFunction(Request $request, $id)
    {
        $user = Auth::guard('user')->user();
        $account = $user->account;

        $validator = Validator::make($request->all(), [
            'description' => 'nullable|string',
            'code' => 'nullable|string',
            'entrypoint' => 'nullable|string|max:255',
            'requirements' => 'nullable|array',
            'environment_variables' => 'nullable|array',
            'secrets' => 'nullable|array',
            'cpu_count' => 'nullable|integer|min:1|max:32',
            'memory_mb' => 'nullable|integer|min:128|max:65536',
            'timeout' => 'nullable|integer|min:1|max:3600',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $function = ModalFunction::forAccount($account->id)->findOrFail($id);

            $function->update($request->only([
                'description',
                'code',
                'entrypoint',
                'requirements',
                'environment_variables',
                'secrets',
                'cpu_count',
                'memory_mb',
                'timeout',
            ]));

            return response()->json([
                'success' => true,
                'message' => 'Function updated successfully. Redeploy to apply changes.',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update function: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Delete a function
     */
    public function deleteFunction($id)
    {
        $user = Auth::guard('user')->user();
        $account = $user->account;

        try {
            $function = ModalFunction::forAccount($account->id)->findOrFail($id);

            // Delete from Modal.com if deployed
            if ($function->isDeployed()) {
                $result = $this->modalService->deleteFunction($function);

                if (!$result['success']) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Failed to delete from Modal.com: ' . ($result['message'] ?? 'Unknown error'),
                    ], 500);
                }
            }

            // Delete local record
            $function->delete();

            return response()->json([
                'success' => true,
                'message' => 'Function deleted successfully',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete function: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Invoke a function
     */
    public function invokeFunction(Request $request, $id)
    {
        $user = Auth::guard('user')->user();
        $account = $user->account;

        try {
            $function = ModalFunction::forAccount($account->id)->findOrFail($id);

            $input = $request->input('input', []);

            $result = $this->modalService->invokeFunction($function, $input);

            return response()->json($result);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Invocation failed: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * List scheduled jobs
     */
    public function jobs()
    {
        $user = Auth::guard('user')->user();
        $account = $user->account;

        $jobs = ModalJob::forAccount($account->id)
            ->with('function')
            ->orderByDesc('created_at')
            ->get();

        $functions = ModalFunction::forAccount($account->id)
            ->deployed()
            ->get();

        return view('user.modal.jobs', compact('jobs', 'functions'));
    }

    /**
     * Create a scheduled job
     */
    public function storeJob(Request $request)
    {
        $user = Auth::guard('user')->user();
        $account = $user->account;

        $validator = Validator::make($request->all(), [
            'job_name' => 'required|string|max:255',
            'function_id' => 'required|exists:vp_modal_functions,id',
            'description' => 'nullable|string',
            'schedule' => 'required|string', // Cron syntax
            'parameters' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            // Verify function belongs to account
            $function = ModalFunction::forAccount($account->id)->findOrFail($request->function_id);

            if (!$function->isDeployed()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Function must be deployed before creating a job',
                ], 422);
            }

            // Create job
            $job = ModalJob::create([
                'account_id' => $account->id,
                'function_id' => $function->id,
                'job_name' => $request->job_name,
                'description' => $request->description,
                'schedule' => $request->schedule,
                'parameters' => $request->parameters,
                'is_active' => true,
            ]);

            // Create job on Modal.com
            $result = $this->modalService->createJob($job);

            if ($result['success']) {
                return response()->json([
                    'success' => true,
                    'message' => 'Scheduled job created successfully',
                    'job_id' => $job->id,
                ]);
            }

            // If Modal creation failed, delete local record
            $job->delete();

            return response()->json($result, 500);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create job: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Toggle job active status
     */
    public function toggleJob($id)
    {
        $user = Auth::guard('user')->user();
        $account = $user->account;

        try {
            $job = ModalJob::forAccount($account->id)->findOrFail($id);

            $job->update([
                'is_active' => !$job->is_active,
            ]);

            return response()->json([
                'success' => true,
                'message' => $job->is_active ? 'Job activated' : 'Job deactivated',
                'is_active' => $job->is_active,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to toggle job: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Delete a scheduled job
     */
    public function deleteJob($id)
    {
        $user = Auth::guard('user')->user();
        $account = $user->account;

        try {
            $job = ModalJob::forAccount($account->id)->findOrFail($id);

            // Delete from Modal.com if deployed
            if ($job->isDeployed()) {
                $result = $this->modalService->deleteJob($job);

                if (!$result['success']) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Failed to delete from Modal.com: ' . ($result['message'] ?? 'Unknown error'),
                    ], 500);
                }
            }

            // Delete local record
            $job->delete();

            return response()->json([
                'success' => true,
                'message' => 'Job deleted successfully',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete job: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * View usage statistics and billing
     */
    public function usage(Request $request)
    {
        $user = Auth::guard('user')->user();
        $account = $user->account;

        $days = $request->input('days', 30);

        // Get statistics
        $stats = ModalUsage::getAccountStatistics($account->id, $days);

        // Get chart data
        $chartData = ModalUsage::getDailyChartData($account->id, $days);

        // Get function breakdown
        $functionUsage = ModalUsage::forAccount($account->id)
            ->lastDays($days)
            ->with('function')
            ->get()
            ->groupBy('function_id')
            ->map(function ($items) {
                return [
                    'function' => $items->first()->function,
                    'invocations' => $items->sum('total_invocations'),
                    'cost' => $items->sum('estimated_cost'),
                ];
            });

        return view('user.modal.usage', compact('stats', 'chartData', 'functionUsage', 'days'));
    }

    /**
     * View execution logs
     */
    public function logs(Request $request)
    {
        $user = Auth::guard('user')->user();
        $account = $user->account;

        $query = ModalExecutionLog::forAccount($account->id)
            ->with('function')
            ->orderByDesc('started_at');

        // Filters
        if ($request->has('function_id') && $request->function_id) {
            $query->where('function_id', $request->function_id);
        }

        if ($request->has('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        $logs = $query->paginate(50);

        $functions = ModalFunction::forAccount($account->id)->get();

        return view('user.modal.logs', compact('logs', 'functions'));
    }

    /**
     * View log details
     */
    public function logDetails($id)
    {
        $user = Auth::guard('user')->user();
        $account = $user->account;

        try {
            $log = ModalExecutionLog::forAccount($account->id)
                ->with('function')
                ->findOrFail($id);

            return view('user.modal.log-details', compact('log'));
        } catch (\Exception $e) {
            abort(404, 'Log entry not found');
        }
    }

    /**
     * Rollback to a previous version
     */
    public function rollbackVersion(Request $request, $id)
    {
        $user = Auth::guard('user')->user();
        $account = $user->account;

        try {
            $function = ModalFunction::forAccount($account->id)->findOrFail($id);

            $versionId = $request->input('version_id');
            $version = $function->versions()->findOrFail($versionId);

            // Update function with version data
            $function->update([
                'code' => $version->code,
                'requirements' => $version->requirements,
                'environment_variables' => $version->environment_variables,
            ]);

            // Redeploy
            $result = $this->modalService->deployFunction($function);

            return response()->json($result);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Rollback failed: ' . $e->getMessage(),
            ], 500);
        }
    }
}
