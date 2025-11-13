<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\HetznerServer;
use App\Models\HetznerVolume;
use App\Models\HetznerSnapshot;
use App\Models\HetznerBilling;
use App\Services\HetznerService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class HetznerController extends Controller
{
    private HetznerService $hetznerService;

    public function __construct()
    {
        $this->middleware(['panel.detector', 'auth.user']);
        $this->hetznerService = new HetznerService();
    }

    /**
     * Display user's Hetzner servers dashboard
     */
    public function index()
    {
        $user = Auth::user();

        $servers = HetznerServer::where('user_id', $user->id)
            ->active()
            ->with(['volumes', 'snapshots', 'floatingIps'])
            ->orderByDesc('created_at')
            ->get();

        $stats = [
            'total_servers' => $servers->count(),
            'running_servers' => $servers->where('status', 'running')->count(),
            'stopped_servers' => $servers->where('status', 'stopped')->count(),
            'total_volumes' => HetznerVolume::whereIn('server_id', $servers->pluck('id'))->count(),
            'total_snapshots' => HetznerSnapshot::whereIn('server_id', $servers->pluck('id'))->count(),
        ];

        return view('user.hetzner.index', compact('servers', 'stats'));
    }

    /**
     * View server details
     */
    public function showServer($id)
    {
        $user = Auth::user();

        $server = HetznerServer::where('user_id', $user->id)
            ->where('id', $id)
            ->with(['volumes', 'snapshots', 'floatingIps'])
            ->firstOrFail();

        return view('user.hetzner.server', compact('server'));
    }

    /**
     * Power on server
     */
    public function powerOn($id)
    {
        try {
            $user = Auth::user();

            $server = HetznerServer::where('user_id', $user->id)
                ->where('id', $id)
                ->firstOrFail();

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
     * Power off server
     */
    public function powerOff($id)
    {
        try {
            $user = Auth::user();

            $server = HetznerServer::where('user_id', $user->id)
                ->where('id', $id)
                ->firstOrFail();

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
     * Reboot server
     */
    public function reboot($id)
    {
        try {
            $user = Auth::user();

            $server = HetznerServer::where('user_id', $user->id)
                ->where('id', $id)
                ->firstOrFail();

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
     * List user's volumes
     */
    public function volumes()
    {
        $user = Auth::user();

        $servers = HetznerServer::where('user_id', $user->id)->active()->get();
        $serverIds = $servers->pluck('id');

        $volumes = HetznerVolume::whereIn('server_id', $serverIds)
            ->orWhereNull('server_id')
            ->with('server')
            ->orderByDesc('created_at')
            ->get();

        return view('user.hetzner.volumes', compact('volumes'));
    }

    /**
     * View volume details
     */
    public function showVolume($id)
    {
        $user = Auth::user();

        $servers = HetznerServer::where('user_id', $user->id)->active()->get();
        $serverIds = $servers->pluck('id');

        $volume = HetznerVolume::where(function($query) use ($serverIds) {
            $query->whereIn('server_id', $serverIds)
                ->orWhereNull('server_id');
        })
        ->where('id', $id)
        ->with('server')
        ->firstOrFail();

        return view('user.hetzner.volume', compact('volume'));
    }

    /**
     * List user's snapshots
     */
    public function snapshots()
    {
        $user = Auth::user();

        $servers = HetznerServer::where('user_id', $user->id)->active()->get();
        $serverIds = $servers->pluck('id');

        $snapshots = HetznerSnapshot::whereIn('server_id', $serverIds)
            ->with('server')
            ->orderByDesc('created_at')
            ->get();

        return view('user.hetzner.snapshots', compact('snapshots'));
    }

    /**
     * Create snapshot
     */
    public function createSnapshot(Request $request, $serverId)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $user = Auth::user();

            $server = HetznerServer::where('user_id', $user->id)
                ->where('id', $serverId)
                ->firstOrFail();

            $result = $this->hetznerService->createServerSnapshot(
                $server->hetzner_server_id,
                $request->description ?? ''
            );

            if (!$result || !isset($result['image'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to create snapshot',
                ], 500);
            }

            $imageData = $result['image'];

            $snapshot = HetznerSnapshot::create([
                'server_id' => $server->id,
                'hetzner_snapshot_id' => $imageData['id'],
                'type' => 'server',
                'source_id' => $server->hetzner_server_id,
                'name' => $request->name,
                'description' => $request->description,
                'size' => $imageData['image_size'] ?? 0,
                'status' => 'creating',
                'created_at_hetzner' => $imageData['created'],
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Snapshot is being created',
                'snapshot' => $snapshot,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create snapshot: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Delete snapshot
     */
    public function deleteSnapshot($id)
    {
        try {
            $user = Auth::user();

            $servers = HetznerServer::where('user_id', $user->id)->active()->get();
            $serverIds = $servers->pluck('id');

            $snapshot = HetznerSnapshot::whereIn('server_id', $serverIds)
                ->where('id', $id)
                ->firstOrFail();

            $deleted = $this->hetznerService->deleteSnapshot($snapshot->hetzner_snapshot_id);

            if (!$deleted) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to delete snapshot',
                ], 500);
            }

            $snapshot->delete();

            return response()->json([
                'success' => true,
                'message' => 'Snapshot deleted successfully',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete snapshot: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * View usage and billing
     */
    public function usage()
    {
        $user = Auth::user();

        $servers = HetznerServer::where('user_id', $user->id)->active()->get();
        $serverIds = $servers->pluck('id');

        $currentYear = now()->year;
        $currentMonth = now()->month;

        // Calculate current month costs for user's resources
        $serverCosts = HetznerBilling::where('resource_type', 'server')
            ->whereIn('resource_id', $servers->pluck('hetzner_server_id'))
            ->where('billing_year', $currentYear)
            ->where('billing_month', $currentMonth)
            ->sum('amount');

        $volumes = HetznerVolume::whereIn('server_id', $serverIds)->get();
        $volumeCosts = HetznerBilling::where('resource_type', 'volume')
            ->whereIn('resource_id', $volumes->pluck('hetzner_volume_id'))
            ->where('billing_year', $currentYear)
            ->where('billing_month', $currentMonth)
            ->sum('amount');

        $snapshots = HetznerSnapshot::whereIn('server_id', $serverIds)->get();
        $snapshotCosts = HetznerBilling::where('resource_type', 'snapshot')
            ->whereIn('resource_id', $snapshots->pluck('hetzner_snapshot_id'))
            ->where('billing_year', $currentYear)
            ->where('billing_month', $currentMonth)
            ->sum('amount');

        $totalCost = $serverCosts + $volumeCosts + $snapshotCosts;

        $costBreakdown = [
            'servers' => (float) $serverCosts,
            'volumes' => (float) $volumeCosts,
            'snapshots' => (float) $snapshotCosts,
        ];

        return view('user.hetzner.usage', compact('servers', 'totalCost', 'costBreakdown'));
    }

    /**
     * Get server metrics (for AJAX)
     */
    public function getMetrics($id)
    {
        try {
            $user = Auth::user();

            $server = HetznerServer::where('user_id', $user->id)
                ->where('id', $id)
                ->firstOrFail();

            // Get latest server info from Hetzner
            $serverData = $this->hetznerService->getServer($server->hetzner_server_id);

            if (!$serverData) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to fetch server metrics',
                ], 500);
            }

            // Update local server status
            $server->update([
                'status' => $serverData['status'],
            ]);

            return response()->json([
                'success' => true,
                'metrics' => [
                    'status' => $serverData['status'],
                    'public_ipv4' => $serverData['public_net']['ipv4']['ip'] ?? null,
                    'public_ipv6' => $serverData['public_net']['ipv6']['ip'] ?? null,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get metrics: ' . $e->getMessage(),
            ], 500);
        }
    }
}
