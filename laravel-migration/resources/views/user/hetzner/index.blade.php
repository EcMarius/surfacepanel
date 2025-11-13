@extends('user.layout')

@section('title', 'My Hetzner Cloud Servers')

@section('content')
<div class="container-fluid">
    <div class="row mb-4">
        <div class="col-12">
            <h1 class="h3 mb-0">My Hetzner Cloud Servers</h1>
            <p class="text-muted">Manage your virtual machines</p>
        </div>
    </div>

    <!-- Statistics Cards -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-muted mb-1">Total Servers</h6>
                            <h3 class="mb-0">{{ $stats['total_servers'] }}</h3>
                        </div>
                        <div class="text-primary">
                            <i class="fas fa-server fa-2x"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-3">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-muted mb-1">Running</h6>
                            <h3 class="mb-0">{{ $stats['running_servers'] }}</h3>
                        </div>
                        <div class="text-success">
                            <i class="fas fa-play-circle fa-2x"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-3">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-muted mb-1">Stopped</h6>
                            <h3 class="mb-0">{{ $stats['stopped_servers'] }}</h3>
                        </div>
                        <div class="text-secondary">
                            <i class="fas fa-stop-circle fa-2x"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-3">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-muted mb-1">Volumes</h6>
                            <h3 class="mb-0">{{ $stats['total_volumes'] }}</h3>
                        </div>
                        <div class="text-info">
                            <i class="fas fa-hdd fa-2x"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Servers List -->
    @if($servers->count() > 0)
        <div class="row">
            @foreach($servers as $server)
            <div class="col-lg-6 mb-4">
                <div class="card">
                    <div class="card-header">
                        <div class="d-flex justify-content-between align-items-center">
                            <h5 class="mb-0">{{ $server->name }}</h5>
                            @if($server->status === 'running')
                                <span class="badge bg-success">Running</span>
                            @elseif($server->status === 'stopped')
                                <span class="badge bg-secondary">Stopped</span>
                            @else
                                <span class="badge bg-info">{{ ucfirst($server->status) }}</span>
                            @endif
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="row mb-3">
                            <div class="col-6">
                                <small class="text-muted">Type:</small>
                                <div><strong>{{ strtoupper($server->server_type) }}</strong></div>
                            </div>
                            <div class="col-6">
                                <small class="text-muted">Location:</small>
                                <div><strong>{{ $server->location }}</strong></div>
                            </div>
                        </div>

                        <div class="row mb-3">
                            <div class="col-6">
                                <small class="text-muted">CPU:</small>
                                <div>{{ $server->vcpus }} vCPU</div>
                            </div>
                            <div class="col-6">
                                <small class="text-muted">Memory:</small>
                                <div>{{ $server->memory }}MB</div>
                            </div>
                        </div>

                        <div class="mb-3">
                            <small class="text-muted">IPv4 Address:</small>
                            <div>
                                @if($server->public_ipv4)
                                    <code>{{ $server->public_ipv4 }}</code>
                                    <button class="btn btn-sm btn-link" onclick="copyToClipboard('{{ $server->public_ipv4 }}')">
                                        <i class="fas fa-copy"></i>
                                    </button>
                                @else
                                    <span class="text-muted">N/A</span>
                                @endif
                            </div>
                        </div>

                        <div class="mb-3">
                            <small class="text-muted">Volumes:</small>
                            <div>{{ $server->volumes->count() }} attached</div>
                        </div>

                        <div class="mb-3">
                            <small class="text-muted">Snapshots:</small>
                            <div>{{ $server->snapshots->count() }} available</div>
                        </div>

                        <div class="d-flex gap-2">
                            @if($server->canStart())
                                <button class="btn btn-sm btn-success" onclick="powerOn({{ $server->id }})">
                                    <i class="fas fa-play"></i> Start
                                </button>
                            @endif

                            @if($server->canStop())
                                <button class="btn btn-sm btn-warning" onclick="powerOff({{ $server->id }})">
                                    <i class="fas fa-stop"></i> Stop
                                </button>
                            @endif

                            @if($server->canReboot())
                                <button class="btn btn-sm btn-info" onclick="reboot({{ $server->id }})">
                                    <i class="fas fa-redo"></i> Reboot
                                </button>
                            @endif

                            <button class="btn btn-sm btn-secondary" onclick="viewDetails({{ $server->id }})">
                                <i class="fas fa-info-circle"></i> Details
                            </button>
                        </div>
                    </div>
                    <div class="card-footer text-muted small">
                        <i class="fas fa-clock"></i> Created: {{ $server->created_at->format('M d, Y') }}
                        <span class="float-end">€{{ number_format($server->monthly_price, 2) }}/month</span>
                    </div>
                </div>
            </div>
            @endforeach
        </div>
    @else
        <div class="card">
            <div class="card-body text-center py-5">
                <i class="fas fa-server fa-3x text-muted mb-3"></i>
                <h5>No Servers Yet</h5>
                <p class="text-muted">You don't have any Hetzner Cloud servers yet. Contact your administrator to provision servers.</p>
            </div>
        </div>
    @endif
</div>

<script>
async function powerOn(id) {
    try {
        const response = await fetch(`/user/hetzner/servers/${id}/power-on`, {
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

async function powerOff(id) {
    if (!confirm('Are you sure you want to power off this server?')) return;

    try {
        const response = await fetch(`/user/hetzner/servers/${id}/power-off`, {
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

async function reboot(id) {
    if (!confirm('Are you sure you want to reboot this server?')) return;

    try {
        const response = await fetch(`/user/hetzner/servers/${id}/reboot`, {
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

function viewDetails(id) {
    window.location = `/user/hetzner/servers/${id}`;
}

function copyToClipboard(text) {
    navigator.clipboard.writeText(text).then(() => {
        alert('IP address copied to clipboard!');
    }).catch(() => {
        alert('Failed to copy to clipboard');
    });
}
</script>
@endsection
