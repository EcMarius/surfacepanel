<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ModalConfig;
use App\Models\ModalFunction;
use App\Models\ModalJob;
use App\Models\ModalUsage;
use App\Services\ModalService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;

class ModalController extends Controller
{
    protected $modalService;

    public function __construct(ModalService $modalService)
    {
        $this->middleware(['panel.detector', 'auth.admin']);
        $this->modalService = $modalService;
    }

    /**
     * Display Modal.com configuration dashboard
     */
    public function index()
    {
        $config = ModalConfig::getInstance();

        // Get system-wide statistics
        $totalFunctions = ModalFunction::count();
        $activeFunctions = ModalFunction::active()->count();
        $totalJobs = ModalJob::count();
        $activeJobs = ModalJob::active()->count();

        // Get usage statistics for current month
        $currentMonthUsage = ModalUsage::currentMonth()->get();
        $totalCost = $currentMonthUsage->sum('estimated_cost');
        $totalInvocations = $currentMonthUsage->sum('total_invocations');

        // Get recent functions
        $recentFunctions = ModalFunction::orderByDesc('created_at')
            ->with('account')
            ->limit(10)
            ->get();

        return view('admin.modal.index', compact(
            'config',
            'totalFunctions',
            'activeFunctions',
            'totalJobs',
            'activeJobs',
            'totalCost',
            'totalInvocations',
            'recentFunctions'
        ));
    }

    /**
     * Show configuration form
     */
    public function config()
    {
        $config = ModalConfig::getInstance();
        $runtimes = ModalConfig::getAvailableRuntimes();
        $gpuTypes = ModalConfig::getAvailableGPUTypes();

        return view('admin.modal.config', compact('config', 'runtimes', 'gpuTypes'));
    }

    /**
     * Update Modal.com configuration
     */
    public function updateConfig(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'api_key' => 'required|string',
            'api_secret' => 'required|string',
            'workspace_name' => 'nullable|string',
            'is_enabled' => 'required|boolean',
            'default_settings' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $config = ModalConfig::getInstance();
            $config->update($request->only([
                'api_key',
                'api_secret',
                'workspace_name',
                'is_enabled',
                'default_settings',
            ]));

            // Test connection
            $testResult = $this->modalService->testConnection();

            if ($testResult['success']) {
                // Update workspace info if available
                if (isset($testResult['workspace'])) {
                    $config->update([
                        'workspace_id' => $testResult['workspace']['id'] ?? null,
                        'workspace_name' => $testResult['workspace']['name'] ?? $config->workspace_name,
                    ]);
                }

                return response()->json([
                    'success' => true,
                    'message' => 'Modal.com configuration updated and connection verified',
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => 'Configuration saved but connection failed: ' . ($testResult['message'] ?? 'Unknown error'),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update configuration: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Test Modal.com API connection
     */
    public function testConnection()
    {
        try {
            $result = $this->modalService->testConnection();

            return response()->json($result);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Connection test failed: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * View all functions (system-wide)
     */
    public function functions(Request $request)
    {
        $query = ModalFunction::with('account')->orderByDesc('created_at');

        // Filters
        if ($request->has('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        if ($request->has('runtime') && $request->runtime !== 'all') {
            $query->where('runtime', $request->runtime);
        }

        if ($request->has('gpu_enabled')) {
            $query->where('gpu_enabled', $request->gpu_enabled);
        }

        if ($request->has('account_id') && $request->account_id) {
            $query->where('account_id', $request->account_id);
        }

        $functions = $query->paginate(50);

        return view('admin.modal.functions', compact('functions'));
    }

    /**
     * View all jobs (system-wide)
     */
    public function jobs(Request $request)
    {
        $query = ModalJob::with(['account', 'function'])->orderByDesc('created_at');

        // Filters
        if ($request->has('is_active')) {
            $query->where('is_active', $request->is_active);
        }

        if ($request->has('last_status') && $request->last_status !== 'all') {
            $query->where('last_status', $request->last_status);
        }

        $jobs = $query->paginate(50);

        return view('admin.modal.jobs', compact('jobs'));
    }

    /**
     * View usage statistics (system-wide)
     */
    public function usage(Request $request)
    {
        $days = $request->input('days', 30);
        $startDate = Carbon::now()->subDays($days);

        $usage = ModalUsage::with(['account', 'function'])
            ->where('usage_date', '>=', $startDate)
            ->orderByDesc('usage_date')
            ->get();

        // Aggregate statistics
        $totalInvocations = $usage->sum('total_invocations');
        $totalCost = $usage->sum('estimated_cost');
        $avgSuccessRate = $usage->avg(function ($item) {
            return $item->getSuccessRate();
        });

        // Daily chart data
        $chartData = [
            'labels' => [],
            'invocations' => [],
            'costs' => [],
        ];

        $dailyData = $usage->groupBy('usage_date')->map(function ($items, $date) {
            return [
                'date' => $date,
                'invocations' => $items->sum('total_invocations'),
                'cost' => $items->sum('estimated_cost'),
            ];
        });

        foreach ($dailyData as $data) {
            $chartData['labels'][] = Carbon::parse($data['date'])->format('M d');
            $chartData['invocations'][] = $data['invocations'];
            $chartData['costs'][] = $data['cost'];
        }

        return view('admin.modal.usage', compact(
            'usage',
            'totalInvocations',
            'totalCost',
            'avgSuccessRate',
            'chartData',
            'days'
        ));
    }

    /**
     * Sync usage statistics from Modal.com
     */
    public function syncUsage(Request $request)
    {
        try {
            $days = $request->input('days', 7);
            $startDate = Carbon::now()->subDays($days)->format('Y-m-d');
            $endDate = Carbon::now()->format('Y-m-d');

            // Get all accounts with Modal functions
            $accounts = ModalFunction::select('account_id')
                ->distinct()
                ->pluck('account_id');

            $synced = 0;
            foreach ($accounts as $accountId) {
                $result = $this->modalService->syncUsageStatistics(
                    $accountId,
                    $startDate,
                    $endDate
                );

                if ($result['success']) {
                    $synced++;
                }
            }

            return response()->json([
                'success' => true,
                'message' => "Synced usage statistics for {$synced} accounts",
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Sync failed: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * View execution logs (system-wide)
     */
    public function logs(Request $request)
    {
        $query = ModalExecutionLog::with(['account', 'function'])
            ->orderByDesc('started_at');

        // Filters
        if ($request->has('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        if ($request->has('function_id') && $request->function_id) {
            $query->where('function_id', $request->function_id);
        }

        if ($request->has('account_id') && $request->account_id) {
            $query->where('account_id', $request->account_id);
        }

        $logs = $query->paginate(100);

        return view('admin.modal.logs', compact('logs'));
    }

    /**
     * Delete a function (admin can delete any function)
     */
    public function deleteFunction($id)
    {
        try {
            $function = ModalFunction::findOrFail($id);

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
     * Get Modal.com statistics API
     */
    public function statistics(Request $request)
    {
        try {
            $days = $request->input('days', 30);

            $stats = [
                'total_functions' => ModalFunction::count(),
                'active_functions' => ModalFunction::active()->count(),
                'gpu_functions' => ModalFunction::gpuEnabled()->count(),
                'total_jobs' => ModalJob::count(),
                'active_jobs' => ModalJob::active()->count(),
            ];

            // Usage statistics
            $usage = ModalUsage::lastDays($days)->get();
            $stats['total_invocations'] = $usage->sum('total_invocations');
            $stats['total_cost'] = $usage->sum('estimated_cost');
            $stats['avg_success_rate'] = $usage->avg(function ($item) {
                return $item->getSuccessRate();
            });

            return response()->json([
                'success' => true,
                'statistics' => $stats,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch statistics: ' . $e->getMessage(),
            ], 500);
        }
    }
}
