<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\HetznerConfig;
use App\Models\HetznerServer;
use App\Models\HetznerVolume;
use App\Models\HetznerSnapshot;
use App\Models\HetznerBilling;
use App\Models\HetznerFloatingIp;
use App\Services\HetznerService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class HetznerController extends Controller
{
    private HetznerService $hetznerService;

    public function __construct()
    {
        $this->middleware(['panel.detector', 'auth.admin']);
        $this->hetznerService = new HetznerService();
    }

    /**
     * Display Hetzner Cloud dashboard
     */
    public function index()
    {
        $config = HetznerConfig::getInstance();

        $stats = [
            'total_servers' => HetznerServer::active()->count(),
            'running_servers' => HetznerServer::running()->count(),
            'total_volumes' => HetznerVolume::count(),
            'attached_volumes' => HetznerVolume::attached()->count(),
            'total_snapshots' => HetznerSnapshot::available()->count(),
            'floating_ips' => HetznerFloatingIp::count(),
            'monthly_cost' => HetznerBilling::getTotalCost(now()->year, now()->month),
        ];

        $servers = HetznerServer::active()
            ->with(['user', 'account'])
            ->orderByDesc('created_at')
            ->limit(10)
            ->get();

        $recentBilling = HetznerBilling::currentMonth()
            ->orderByDesc('created_at')
            ->limit(10)
            ->get();

        return view('admin.hetzner.index', compact('config', 'stats', 'servers', 'recentBilling'));
    }

    /**
     * Show configuration page
     */
    public function config()
    {
        $config = HetznerConfig::getInstance();
        return view('admin.hetzner.config', compact('config'));
    }

    /**
     * Update Hetzner configuration
     */
    public function updateConfig(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'api_token' => 'nullable|string',
            'is_enabled' => 'required|boolean',
            'default_datacenter' => 'required|string',
            'default_server_type' => 'required|string',
            'default_image' => 'required|string',
            'auto_backups' => 'required|boolean',
            'monthly_budget_alert' => 'nullable|numeric|min:0',
            'notification_email' => 'nullable|email',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $config = HetznerConfig::getInstance();
            $updateData = $request->only([
                'is_enabled',
                'default_datacenter',
                'default_server_type',
                'default_image',
                'auto_backups',
                'monthly_budget_alert',
                'notification_email',
            ]);

            if ($request->filled('api_token')) {
                $updateData['api_token'] = $request->api_token;
            }

            $config->update($updateData);

            return response()->json([
                'success' => true,
                'message' => 'Hetzner configuration updated successfully',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update configuration: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * List all servers
     */
    public function servers()
    {
        $servers = HetznerServer::with(['user', 'account', 'volumes', 'snapshots'])
            ->orderByDesc('created_at')
            ->paginate(20);

        return view('admin.hetzner.servers', compact('servers'));
    }

    /**
     * Show server provisioning form
     */
    public function provisionForm()
    {
        $config = HetznerConfig::getInstance();

        // Get available options from Hetzner
        $serverTypes = $this->hetznerService->getServerTypes();
        $locations = $this->hetznerService->getLocations();
        $images = $this->hetznerService->getImages('system');
        $sshKeys = $this->hetznerService->getSshKeys();

        return view('admin.hetzner.provision', compact('config', 'serverTypes', 'locations', 'images', 'sshKeys'));
    }

    /**
     * Provision a new server
     */
    public function provision(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'server_type' => 'required|string',
            'location' => 'required|string',
            'image' => 'required|string',
            'ssh_keys' => 'nullable|array',
            'start_after_create' => 'boolean',
            'user_id' => 'nullable|exists:vp_users,id',
            'account_id' => 'nullable|exists:vp_accounts,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $createData = [
                'name' => $request->name,
                'server_type' => $request->server_type,
                'location' => $request->location,
                'image' => $request->image,
                'start_after_create' => $request->start_after_create ?? true,
            ];

            if ($request->filled('ssh_keys')) {
                $createData['ssh_keys'] = $request->ssh_keys;
            }

            $result = $this->hetznerService->createServer($createData);

            if (!$result || !isset($result['server'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to create server at Hetzner',
                ], 500);
            }

            $serverData = $result['server'];

            // Create server record in database
            $server = HetznerServer::create([
                'hetzner_server_id' => $serverData['id'],
                'user_id' => $request->user_id,
                'account_id' => $request->account_id,
                'name' => $serverData['name'],
                'server_type' => $serverData['server_type']['name'],
                'datacenter' => $serverData['datacenter']['name'] ?? '',
                'location' => $serverData['datacenter']['location']['name'] ?? '',
                'image' => $serverData['image']['name'] ?? '',
                'status' => $serverData['status'],
                'public_ipv4' => $serverData['public_net']['ipv4']['ip'] ?? null,
                'public_ipv6' => $serverData['public_net']['ipv6']['ip'] ?? null,
                'disk_size' => $serverData['server_type']['disk'] ?? 0,
                'vcpus' => $serverData['server_type']['cores'] ?? 0,
                'memory' => ($serverData['server_type']['memory'] ?? 0) * 1024,
                'root_password' => $result['root_password'] ?? null,
                'created_at_hetzner' => $serverData['created'],
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Server provisioned successfully',
                'server' => $server,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to provision server: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Delete a server
     */
    public function deleteServer($id)
    {
        try {
            $server = HetznerServer::findOrFail($id);

            // Delete from Hetzner
            $deleted = $this->hetznerService->deleteServer($server->hetzner_server_id);

            if (!$deleted) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to delete server from Hetzner',
                ], 500);
            }

            // Soft delete from database
            $server->delete();

            return response()->json([
                'success' => true,
                'message' => 'Server deleted successfully',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete server: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Power on a server
     */
    public function powerOnServer($id)
    {
        try {
            $server = HetznerServer::findOrFail($id);

            if (!$server->canStart()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Server cannot be started in current state',
                ], 400);
            }

            $result = $this->hetznerService->powerOnServer($server->hetzner_server_id);

            if (!$result) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to power on server',
                ], 500);
            }

            $server->update(['status' => 'starting']);

            return response()->json([
                'success' => true,
                'message' => 'Server is starting',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to power on server: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Power off a server
     */
    public function powerOffServer($id)
    {
        try {
            $server = HetznerServer::findOrFail($id);

            if (!$server->canStop()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Server cannot be stopped in current state',
                ], 400);
            }

            $result = $this->hetznerService->powerOffServer($server->hetzner_server_id);

            if (!$result) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to power off server',
                ], 500);
            }

            $server->update(['status' => 'stopping']);

            return response()->json([
                'success' => true,
                'message' => 'Server is stopping',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to power off server: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Reboot a server
     */
    public function rebootServer($id)
    {
        try {
            $server = HetznerServer::findOrFail($id);

            if (!$server->canReboot()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Server cannot be rebooted in current state',
                ], 400);
            }

            $result = $this->hetznerService->rebootServer($server->hetzner_server_id);

            if (!$result) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to reboot server',
                ], 500);
            }

            return response()->json([
                'success' => true,
                'message' => 'Server is rebooting',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to reboot server: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * List volumes
     */
    public function volumes()
    {
        $volumes = HetznerVolume::with('server')->orderByDesc('created_at')->paginate(20);
        return view('admin.hetzner.volumes', compact('volumes'));
    }

    /**
     * Create volume
     */
    public function createVolume(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'size' => 'required|integer|min:10|max:10000',
            'location' => 'required|string',
            'format' => 'nullable|in:ext4,xfs',
            'server_id' => 'nullable|exists:vp_hetzner_servers,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $createData = [
                'name' => $request->name,
                'size' => $request->size,
                'location' => $request->location,
            ];

            if ($request->filled('format')) {
                $createData['format'] = $request->format;
            }

            $result = $this->hetznerService->createVolume($createData);

            if (!$result || !isset($result['volume'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to create volume at Hetzner',
                ], 500);
            }

            $volumeData = $result['volume'];

            $volume = HetznerVolume::create([
                'hetzner_volume_id' => $volumeData['id'],
                'name' => $volumeData['name'],
                'size' => $volumeData['size'],
                'location' => $volumeData['location']['name'] ?? '',
                'format' => $volumeData['format'] ?? null,
                'status' => $volumeData['status'],
            ]);

            // Attach to server if specified
            if ($request->filled('server_id')) {
                $server = HetznerServer::findOrFail($request->server_id);
                $this->hetznerService->attachVolume($volume->hetzner_volume_id, $server->hetzner_server_id);
            }

            return response()->json([
                'success' => true,
                'message' => 'Volume created successfully',
                'volume' => $volume,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create volume: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Attach volume to server
     */
    public function attachVolume(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'server_id' => 'required|exists:vp_hetzner_servers,id',
            'automount' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $volume = HetznerVolume::findOrFail($id);
            $server = HetznerServer::findOrFail($request->server_id);

            if (!$volume->canAttach()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Volume cannot be attached in current state',
                ], 400);
            }

            $result = $this->hetznerService->attachVolume(
                $volume->hetzner_volume_id,
                $server->hetzner_server_id,
                $request->automount ?? false
            );

            if (!$result) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to attach volume',
                ], 500);
            }

            $volume->update([
                'server_id' => $server->id,
                'status' => 'attaching',
                'attached_at' => now(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Volume is being attached',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to attach volume: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Detach volume from server
     */
    public function detachVolume($id)
    {
        try {
            $volume = HetznerVolume::findOrFail($id);

            if (!$volume->canDetach()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Volume cannot be detached in current state',
                ], 400);
            }

            $result = $this->hetznerService->detachVolume($volume->hetzner_volume_id);

            if (!$result) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to detach volume',
                ], 500);
            }

            $volume->update([
                'server_id' => null,
                'status' => 'detaching',
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Volume is being detached',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to detach volume: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Sync servers from Hetzner
     */
    public function syncServers()
    {
        try {
            $count = $this->hetznerService->syncServers();

            return response()->json([
                'success' => true,
                'message' => "Synced {$count} servers from Hetzner",
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to sync servers: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Sync volumes from Hetzner
     */
    public function syncVolumes()
    {
        try {
            $count = $this->hetznerService->syncVolumes();

            return response()->json([
                'success' => true,
                'message' => "Synced {$count} volumes from Hetzner",
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to sync volumes: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * View billing and cost tracking
     */
    public function billing()
    {
        $currentYear = now()->year;
        $currentMonth = now()->month;

        $monthlyCosts = HetznerBilling::getMonthlyCosts($currentYear);
        $costBreakdown = HetznerBilling::getCostBreakdown($currentYear, $currentMonth);
        $topExpensive = HetznerBilling::getTopExpensive($currentYear, $currentMonth, 10);
        $totalCost = HetznerBilling::getTotalCost($currentYear, $currentMonth);

        return view('admin.hetzner.billing', compact(
            'currentYear',
            'currentMonth',
            'monthlyCosts',
            'costBreakdown',
            'topExpensive',
            'totalCost'
        ));
    }
}
