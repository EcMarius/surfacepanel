@extends('admin.layout')

@section('title', 'Hetzner Cloud Servers')

@section('content')
<div class="container-fluid">
    <div class="row mb-4">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h1 class="h3 mb-0">Hetzner Cloud Servers</h1>
                    <p class="text-muted">Manage Hetzner Cloud virtual machines</p>
                </div>
                <div>
                    <button class="btn btn-success" onclick="window.location='{{ route('admin.hetzner.provision') }}'">
                        <i class="fas fa-plus"></i> Provision Server
                    </button>
                    <button class="btn btn-primary" onclick="syncServers()">
                        <i class="fas fa-sync"></i> Sync from Hetzner
                    </button>
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Type</th>
                            <th>Location</th>
                            <th>Status</th>
                            <th>IP Address</th>
                            <th>User</th>
                            <th>Monthly Cost</th>
                            <th>Created</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($servers as $server)
                        <tr>
                            <td>
                                <strong>{{ $server->name }}</strong>
                                <br>
                                <small class="text-muted">ID: {{ $server->hetzner_server_id }}</small>
                            </td>
                            <td>
                                {{ strtoupper($server->server_type) }}
                                <br>
                                <small class="text-muted">{{ $server->vcpus }} vCPU, {{ $server->memory }}MB RAM</small>
                            </td>
                            <td>{{ $server->location }}</td>
                            <td>
                                @if($server->status === 'running')
                                    <span class="badge bg-success">Running</span>
                                @elseif($server->status === 'stopped')
                                    <span class="badge bg-secondary">Stopped</span>
                                @elseif($server->status === 'starting')
                                    <span class="badge bg-info">Starting</span>
                                @elseif($server->status === 'stopping')
                                    <span class="badge bg-warning">Stopping</span>
                                @else
                                    <span class="badge bg-secondary">{{ ucfirst($server->status) }}</span>
                                @endif
                            </td>
                            <td>
                                @if($server->public_ipv4)
                                    {{ $server->public_ipv4 }}
                                @else
                                    <span class="text-muted">N/A</span>
                                @endif
                            </td>
                            <td>
                                @if($server->user)
                                    {{ $server->user->username }}
                                @else
                                    <span class="text-muted">Unassigned</span>
                                @endif
                            </td>
                            <td>€{{ number_format($server->monthly_price, 2) }}</td>
                            <td>{{ $server->created_at->format('Y-m-d') }}</td>
                            <td>
                                <div class="btn-group btn-group-sm">
                                    @if($server->canStart())
                                        <button class="btn btn-success" onclick="powerOnServer({{ $server->id }})" title="Power On">
                                            <i class="fas fa-play"></i>
                                        </button>
                                    @endif
                                    @if($server->canStop())
                                        <button class="btn btn-warning" onclick="powerOffServer({{ $server->id }})" title="Power Off">
                                            <i class="fas fa-stop"></i>
                                        </button>
                                    @endif
                                    @if($server->canReboot())
                                        <button class="btn btn-info" onclick="rebootServer({{ $server->id }})" title="Reboot">
                                            <i class="fas fa-redo"></i>
                                        </button>
                                    @endif
                                    <button class="btn btn-danger" onclick="deleteServer({{ $server->id }})" title="Delete">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="9" class="text-center text-muted py-4">
                                No servers found. <a href="{{ route('admin.hetzner.provision') }}">Provision your first server</a>
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if($servers->hasPages())
                <div class="mt-3">
                    {{ $servers->links() }}
                </div>
            @endif
        </div>
    </div>
</div>

<script>
async function syncServers() {
    if (!confirm('Sync all servers from Hetzner Cloud?')) return;

    try {
        const response = await fetch('{{ route("admin.hetzner.servers.sync") }}', {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': '{{ csrf_token() }}'
            }
        });

        const result = await response.json();

        if (result.success) {
            alert(result.message);
            window.location.reload();
        } else {
            alert('Error: ' + result.message);
        }
    } catch (error) {
        alert('Error: ' + error.message);
    }
}

async function powerOnServer(id) {
    try {
        const response = await fetch(`/admin/hetzner/servers/${id}/power-on`, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': '{{ csrf_token() }}'
            }
        });

        const result = await response.json();

        if (result.success) {
            alert(result.message);
            window.location.reload();
        } else {
            alert('Error: ' + result.message);
        }
    } catch (error) {
        alert('Error: ' + error.message);
    }
}

async function powerOffServer(id) {
    if (!confirm('Are you sure you want to power off this server?')) return;

    try {
        const response = await fetch(`/admin/hetzner/servers/${id}/power-off`, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': '{{ csrf_token() }}'
            }
        });

        const result = await response.json();

        if (result.success) {
            alert(result.message);
            window.location.reload();
        } else {
            alert('Error: ' + result.message);
        }
    } catch (error) {
        alert('Error: ' + error.message);
    }
}

async function rebootServer(id) {
    if (!confirm('Are you sure you want to reboot this server?')) return;

    try {
        const response = await fetch(`/admin/hetzner/servers/${id}/reboot`, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': '{{ csrf_token() }}'
            }
        });

        const result = await response.json();

        if (result.success) {
            alert(result.message);
            window.location.reload();
        } else {
            alert('Error: ' + result.message);
        }
    } catch (error) {
        alert('Error: ' + error.message);
    }
}

async function deleteServer(id) {
    if (!confirm('Are you sure you want to delete this server? This action cannot be undone!')) return;

    try {
        const response = await fetch(`/admin/hetzner/servers/${id}`, {
            method: 'DELETE',
            headers: {
                'X-CSRF-TOKEN': '{{ csrf_token() }}'
            }
        });

        const result = await response.json();

        if (result.success) {
            alert(result.message);
            window.location.reload();
        } else {
            alert('Error: ' + result.message);
        }
    } catch (error) {
        alert('Error: ' + error.message);
    }
}
</script>
@endsection
