<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\OVHConfig;
use App\Models\OVHServer;
use App\Models\OVHBilling;
use App\Models\OVHCloudInstance;
use App\Models\OVHFailoverIp;
use App\Services\OVHService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Exception;

class OVHController extends Controller
{
    protected $ovhService;

    public function __construct(OVHService $ovhService)
    {
        $this->middleware(['panel.detector', 'auth.admin']);
        $this->ovhService = $ovhService;
    }

    /**
     * Display OVH dashboard
     */
    public function index()
    {
        $config = OVHConfig::getInstance();

        $stats = [
            'total_servers' => OVHServer::count(),
            'active_servers' => OVHServer::where('status', 'active')->count(),
            'cloud_instances' => OVHCloudInstance::count(),
            'active_instances' => OVHCloudInstance::where('status', 'active')->count(),
            'monthly_cost' => OVHBilling::currentMonth()->sum('amount'),
        ];

        $recentServers = OVHServer::with('account')
            ->orderByDesc('created_at')
            ->limit(10)
            ->get();

        $recentBilling = OVHBilling::with('server')
            ->orderByDesc('billing_date')
            ->limit(10)
            ->get();

        return view('admin.ovh.index', compact('config', 'stats', 'recentServers', 'recentBilling'));
    }

    /**
     * Show OVH API configuration page
     */
    public function showConfig()
    {
        $config = OVHConfig::getInstance();
        return view('admin.ovh.config', compact('config'));
    }

    /**
     * Update OVH API configuration
     */
    public function updateConfig(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'application_key' => 'required|string',
            'application_secret' => 'required|string',
            'consumer_key' => 'nullable|string',
            'endpoint' => 'required|in:ovh-eu,ovh-ca,ovh-us,kimsufi-eu,kimsufi-ca,soyoustart-eu,soyoustart-ca',
            'webhook_url' => 'nullable|url',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $config = OVHConfig::getInstance();
            $config->update($request->only([
                'application_key',
                'application_secret',
                'consumer_key',
                'endpoint',
                'webhook_url',
            ]));

            return response()->json([
                'success' => true,
                'message' => 'OVH API configuration updated successfully',
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update configuration: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Request consumer key from OVH API
     */
    public function requestConsumerKey(Request $request)
    {
        try {
            $accessRules = [
                ['method' => 'GET', 'path' => '/*'],
                ['method' => 'POST', 'path' => '/*'],
                ['method' => 'PUT', 'path' => '/*'],
                ['method' => 'DELETE', 'path' => '/*'],
            ];

            $result = $this->ovhService->requestConsumerKey($accessRules);

            return response()->json([
                'success' => true,
                'validation_url' => $result['validation_url'],
                'consumer_key' => $result['consumer_key'],
                'message' => 'Please visit the validation URL to authorize the application',
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to request consumer key: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Test OVH API connection
     */
    public function testConnection()
    {
        try {
            $this->ovhService->testConnection();

            return response()->json([
                'success' => true,
                'message' => 'OVH API connection successful',
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Connection failed: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Toggle OVH integration active status
     */
    public function toggleActive()
    {
        try {
            $config = OVHConfig::getInstance();

            if (!$config->isConfigured()) {
                return response()->json([
                    'success' => false,
                    'message' => 'OVH API is not configured. Please configure API credentials first.',
                ], 400);
            }

            $config->update(['is_active' => !$config->is_active]);

            return response()->json([
                'success' => true,
                'is_active' => $config->is_active,
                'message' => $config->is_active ? 'OVH integration activated' : 'OVH integration deactivated',
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to toggle status: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * List all OVH servers
     */
    public function servers()
    {
        $servers = OVHServer::with('account', 'billingRecords')
            ->orderByDesc('created_at')
            ->paginate(50);

        $serverTypes = OVHServer::select('server_type')
            ->groupBy('server_type')
            ->pluck('server_type');

        return view('admin.ovh.servers', compact('servers', 'serverTypes'));
    }

    /**
     * Sync servers from OVH API
     */
    public function syncServers()
    {
        try {
            $result = $this->ovhService->syncServers();

            return response()->json([
                'success' => true,
                'message' => "Successfully synced {$result['synced_count']} servers from OVH",
                'data' => $result,
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to sync servers: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Show server provisioning page
     */
    public function showProvision()
    {
        $accounts = \App\Models\Account::where('status', 'active')->get();
        return view('admin.ovh.provision', compact('accounts'));
    }

    /**
     * Provision a new server
     */
    public function provisionServer(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'service_name' => 'required|string|unique:vp_ovh_servers,service_name',
            'server_type' => 'required|in:dedicated,vps,public_cloud,private_cloud',
            'account_id' => 'nullable|exists:vp_accounts,id',
            'display_name' => 'nullable|string',
            'ip_address' => 'nullable|ip',
            'datacenter' => 'nullable|string',
            'notes' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $server = OVHServer::create([
                'service_name' => $request->service_name,
                'server_type' => $request->server_type,
                'account_id' => $request->account_id,
                'display_name' => $request->display_name,
                'ip_address' => $request->ip_address,
                'datacenter' => $request->datacenter,
                'notes' => $request->notes,
                'status' => 'active',
                'provisioned_at' => now(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Server provisioned successfully',
                'server' => $server,
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to provision server: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get server details
     */
    public function getServerDetails($id)
    {
        try {
            $server = OVHServer::with('account', 'billingRecords', 'failoverIps')->findOrFail($id);

            // Get live data from OVH API
            $liveData = $this->ovhService->getServerDetails($server->service_name);

            return response()->json([
                'success' => true,
                'server' => $server,
                'live_data' => $liveData,
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get server details: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Reboot server
     */
    public function rebootServer($id)
    {
        try {
            $server = OVHServer::findOrFail($id);
            $this->ovhService->rebootServer($server->service_name);

            return response()->json([
                'success' => true,
                'message' => "Server {$server->service_name} reboot initiated",
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to reboot server: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Reinstall server
     */
    public function reinstallServer(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'template' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $server = OVHServer::findOrFail($id);
            $result = $this->ovhService->reinstallServer($server->service_name, [
                'template' => $request->template,
            ]);

            return response()->json([
                'success' => true,
                'message' => "Server {$server->service_name} reinstallation started",
                'result' => $result,
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to reinstall server: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Manage failover IPs
     */
    public function failoverIps()
    {
        $failoverIps = OVHFailoverIp::with('server')->paginate(50);
        $servers = OVHServer::where('status', 'active')->get();

        return view('admin.ovh.failover-ips', compact('failoverIps', 'servers'));
    }

    /**
     * Route failover IP
     */
    public function routeFailoverIp(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'ip_address' => 'required|ip',
            'target_service' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $this->ovhService->routeFailoverIp(
                $request->ip_address,
                $request->target_service
            );

            return response()->json([
                'success' => true,
                'message' => "Failover IP {$request->ip_address} routed to {$request->target_service}",
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to route failover IP: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update reverse DNS
     */
    public function updateReverseDns(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'ip_address' => 'required|ip',
            'reverse_dns' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $this->ovhService->updateReverseDns(
                $request->ip_address,
                $request->reverse_dns
            );

            return response()->json([
                'success' => true,
                'message' => "Reverse DNS updated for {$request->ip_address}",
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update reverse DNS: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Show billing page
     */
    public function billing()
    {
        $billingRecords = OVHBilling::with('server')
            ->orderByDesc('billing_date')
            ->paginate(50);

        $stats = OVHBilling::getStatistics(6);

        $currentMonthTotal = OVHBilling::currentMonth()->sum('amount');
        $currentMonthPaid = OVHBilling::currentMonth()->paid()->sum('amount');
        $currentMonthPending = OVHBilling::currentMonth()->pending()->sum('amount');
        $overdueTotal = OVHBilling::overdue()->sum('amount');

        return view('admin.ovh.billing', compact(
            'billingRecords',
            'stats',
            'currentMonthTotal',
            'currentMonthPaid',
            'currentMonthPending',
            'overdueTotal'
        ));
    }

    /**
     * Sync billing from OVH API
     */
    public function syncBilling()
    {
        try {
            $result = $this->ovhService->syncBilling();

            return response()->json([
                'success' => true,
                'message' => 'Billing data synced successfully',
                'data' => $result,
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to sync billing: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Cloud instances management
     */
    public function cloudInstances()
    {
        $instances = OVHCloudInstance::with('account')
            ->orderByDesc('created_at')
            ->paginate(50);

        return view('admin.ovh.cloud-instances', compact('instances'));
    }

    /**
     * Create cloud instance
     */
    public function createCloudInstance(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'project_id' => 'required|string',
            'name' => 'required|string',
            'flavor_id' => 'required|string',
            'image_id' => 'required|string',
            'region' => 'required|string',
            'account_id' => 'nullable|exists:vp_accounts,id',
            'monthly_billing' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $result = $this->ovhService->createCloudInstance(
                $request->project_id,
                $request->all()
            );

            $instance = OVHCloudInstance::create([
                'account_id' => $request->account_id,
                'instance_id' => $result['instance_id'],
                'project_id' => $request->project_id,
                'name' => $request->name,
                'flavor_id' => $request->flavor_id,
                'image_id' => $request->image_id,
                'region' => $request->region,
                'monthly_billing' => $request->monthly_billing ?? false,
                'status' => 'building',
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Cloud instance creation started',
                'instance' => $instance,
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create cloud instance: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Control cloud instance (start/stop/delete)
     */
    public function controlCloudInstance(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'action' => 'required|in:start,stop,delete',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $instance = OVHCloudInstance::findOrFail($id);

            switch ($request->action) {
                case 'start':
                    $this->ovhService->startCloudInstance($instance->project_id, $instance->instance_id);
                    $message = 'Instance started successfully';
                    break;
                case 'stop':
                    $this->ovhService->stopCloudInstance($instance->project_id, $instance->instance_id);
                    $message = 'Instance stopped successfully';
                    break;
                case 'delete':
                    $this->ovhService->deleteCloudInstance($instance->project_id, $instance->instance_id);
                    $message = 'Instance deletion initiated';
                    break;
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
     * Get network statistics
     */
    public function networkStats($id)
    {
        try {
            $server = OVHServer::findOrFail($id);
            $stats = $this->ovhService->getTrafficStats($server->service_name);

            return response()->json([
                'success' => true,
                'stats' => $stats,
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get network stats: ' . $e->getMessage(),
            ], 500);
        }
    }
}
