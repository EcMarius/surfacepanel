<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\OVHServer;
use App\Models\OVHBilling;
use App\Models\OVHCloudInstance;
use App\Services\OVHService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Exception;

class OVHController extends Controller
{
    protected $ovhService;

    public function __construct(OVHService $ovhService)
    {
        $this->middleware(['panel.detector', 'auth']);
        $this->ovhService = $ovhService;
    }

    /**
     * Display user's OVH resources
     */
    public function index()
    {
        $user = Auth::user();
        $account = $user->accounts()->first();

        if (!$account) {
            return view('user.ovh.index')->with('error', 'No account found');
        }

        // Get user's OVH servers
        $servers = OVHServer::where('account_id', $account->id)
            ->orderByDesc('created_at')
            ->get();

        // Get cloud instances
        $cloudInstances = OVHCloudInstance::where('account_id', $account->id)
            ->orderByDesc('created_at')
            ->get();

        // Get billing summary
        $currentMonthBilling = OVHBilling::whereHas('server', function ($query) use ($account) {
            $query->where('account_id', $account->id);
        })->currentMonth()->sum('amount');

        $totalServers = $servers->count();
        $activeServers = $servers->where('status', 'active')->count();
        $totalInstances = $cloudInstances->count();
        $activeInstances = $cloudInstances->where('status', 'active')->count();

        return view('user.ovh.index', compact(
            'servers',
            'cloudInstances',
            'currentMonthBilling',
            'totalServers',
            'activeServers',
            'totalInstances',
            'activeInstances'
        ));
    }

    /**
     * View server details
     */
    public function serverDetails($id)
    {
        $user = Auth::user();
        $account = $user->accounts()->first();

        $server = OVHServer::where('account_id', $account->id)
            ->findOrFail($id);

        $billingHistory = OVHBilling::where('server_id', $server->id)
            ->orderByDesc('billing_date')
            ->limit(12)
            ->get();

        return view('user.ovh.server-details', compact('server', 'billingHistory'));
    }

    /**
     * Reboot server (if allowed)
     */
    public function rebootServer($id)
    {
        try {
            $user = Auth::user();
            $account = $user->accounts()->first();

            $server = OVHServer::where('account_id', $account->id)
                ->where('status', 'active')
                ->findOrFail($id);

            $this->ovhService->rebootServer($server->service_name);

            return response()->json([
                'success' => true,
                'message' => 'Server reboot initiated successfully',
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to reboot server: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * View cloud instance details
     */
    public function instanceDetails($id)
    {
        $user = Auth::user();
        $account = $user->accounts()->first();

        $instance = OVHCloudInstance::where('account_id', $account->id)
            ->findOrFail($id);

        return view('user.ovh.instance-details', compact('instance'));
    }

    /**
     * Control cloud instance
     */
    public function controlInstance(Request $request, $id)
    {
        $request->validate([
            'action' => 'required|in:start,stop',
        ]);

        try {
            $user = Auth::user();
            $account = $user->accounts()->first();

            $instance = OVHCloudInstance::where('account_id', $account->id)
                ->findOrFail($id);

            if ($request->action === 'start') {
                $this->ovhService->startCloudInstance($instance->project_id, $instance->instance_id);
                $message = 'Instance started successfully';
            } else {
                $this->ovhService->stopCloudInstance($instance->project_id, $instance->instance_id);
                $message = 'Instance stopped successfully';
            }

            return response()->json([
                'success' => true,
                'message' => $message,
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to control instance: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * View billing history
     */
    public function billing()
    {
        $user = Auth::user();
        $account = $user->accounts()->first();

        $billingRecords = OVHBilling::whereHas('server', function ($query) use ($account) {
            $query->where('account_id', $account->id);
        })
        ->with('server')
        ->orderByDesc('billing_date')
        ->paginate(50);

        $currentMonthTotal = OVHBilling::whereHas('server', function ($query) use ($account) {
            $query->where('account_id', $account->id);
        })->currentMonth()->sum('amount');

        $unpaidTotal = OVHBilling::whereHas('server', function ($query) use ($account) {
            $query->where('account_id', $account->id);
        })->where('status', '!=', 'paid')->sum('amount');

        return view('user.ovh.billing', compact(
            'billingRecords',
            'currentMonthTotal',
            'unpaidTotal'
        ));
    }

    /**
     * Get server usage statistics
     */
    public function serverStats($id)
    {
        try {
            $user = Auth::user();
            $account = $user->accounts()->first();

            $server = OVHServer::where('account_id', $account->id)
                ->findOrFail($id);

            $stats = $this->ovhService->getTrafficStats($server->service_name);

            return response()->json([
                'success' => true,
                'stats' => $stats,
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get statistics: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * View usage monitoring
     */
    public function monitoring()
    {
        $user = Auth::user();
        $account = $user->accounts()->first();

        $servers = OVHServer::where('account_id', $account->id)
            ->where('status', 'active')
            ->get();

        $instances = OVHCloudInstance::where('account_id', $account->id)
            ->where('status', 'active')
            ->get();

        return view('user.ovh.monitoring', compact('servers', 'instances'));
    }
}
