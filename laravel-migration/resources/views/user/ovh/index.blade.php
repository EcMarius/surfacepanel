@extends('layouts.user')

@section('title', 'My OVH Resources')

@section('content')
<div class="container-fluid">
    <div class="row mb-4">
        <div class="col-md-12">
            <h1 class="h3 mb-3">My OVH Resources</h1>
        </div>
    </div>

    <!-- Statistics Cards -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card border-left-primary">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">Total Servers</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">{{ $totalServers }}</div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-server fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-3">
            <div class="card border-left-success">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-success text-uppercase mb-1">Active Servers</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">{{ $activeServers }}</div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-check-circle fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-3">
            <div class="card border-left-info">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-info text-uppercase mb-1">Cloud Instances</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">{{ $totalInstances }}</div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-cloud fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-3">
            <div class="card border-left-warning">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">Current Month</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">€{{ number_format($currentMonthBilling, 2) }}</div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-euro-sign fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Servers Section -->
    @if($servers->count() > 0)
    <div class="card mb-4">
        <div class="card-header">
            <h6 class="m-0 font-weight-bold text-primary">My Servers</h6>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Server Name</th>
                            <th>Type</th>
                            <th>IP Address</th>
                            <th>Specifications</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($servers as $server)
                        <tr>
                            <td>
                                <strong>{{ $server->display_name ?? $server->service_name }}</strong><br>
                                <small class="text-muted">{{ $server->service_name }}</small>
                            </td>
                            <td><span class="badge badge-info">{{ $server->server_type }}</span></td>
                            <td>{{ $server->ip_address ?? 'N/A' }}</td>
                            <td><small>{{ $server->getSpecifications() }}</small></td>
                            <td>
                                <span class="badge badge-{{ $server->status === 'active' ? 'success' : 'warning' }}">
                                    {{ ucfirst($server->status) }}
                                </span>
                            </td>
                            <td>
                                <a href="{{ route('user.ovh.server-details', $server->id) }}"
                                   class="btn btn-sm btn-primary" title="View Details">
                                    <i class="fas fa-eye"></i>
                                </a>
                                @if($server->status === 'active')
                                <button class="btn btn-sm btn-warning reboot-btn"
                                        data-id="{{ $server->id }}" title="Reboot">
                                    <i class="fas fa-redo"></i>
                                </button>
                                @endif
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    @endif

    <!-- Cloud Instances Section -->
    @if($cloudInstances->count() > 0)
    <div class="card mb-4">
        <div class="card-header">
            <h6 class="m-0 font-weight-bold text-primary">Cloud Instances</h6>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Region</th>
                            <th>IP Address</th>
                            <th>Flavor</th>
                            <th>Status</th>
                            <th>Billing</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($cloudInstances as $instance)
                        <tr>
                            <td><strong>{{ $instance->name }}</strong></td>
                            <td>{{ strtoupper($instance->region) }}</td>
                            <td>{{ $instance->ip_address ?? 'N/A' }}</td>
                            <td><span class="badge badge-secondary">{{ $instance->flavor_id }}</span></td>
                            <td>
                                <span class="badge badge-{{ $instance->status === 'active' ? 'success' : ($instance->status === 'stopped' ? 'warning' : 'danger') }}">
                                    {{ ucfirst($instance->status) }}
                                </span>
                            </td>
                            <td>
                                <small>
                                    @if($instance->monthly_billing)
                                        €{{ number_format($instance->monthly_rate, 2) }}/mo
                                    @else
                                        €{{ number_format($instance->hourly_rate, 4) }}/hr
                                    @endif
                                </small>
                            </td>
                            <td>
                                <div class="btn-group" role="group">
                                    @if($instance->status === 'stopped')
                                    <button class="btn btn-sm btn-success start-instance-btn"
                                            data-id="{{ $instance->id }}" title="Start">
                                        <i class="fas fa-play"></i>
                                    </button>
                                    @elseif($instance->status === 'active')
                                    <button class="btn btn-sm btn-warning stop-instance-btn"
                                            data-id="{{ $instance->id }}" title="Stop">
                                        <i class="fas fa-stop"></i>
                                    </button>
                                    @endif
                                    <a href="{{ route('user.ovh.instance-details', $instance->id) }}"
                                       class="btn btn-sm btn-primary" title="Details">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    @endif

    @if($servers->count() === 0 && $cloudInstances->count() === 0)
    <div class="card">
        <div class="card-body text-center py-5">
            <i class="fas fa-server fa-4x text-muted mb-3"></i>
            <h5>No OVH Resources Found</h5>
            <p class="text-muted">You don't have any OVH servers or cloud instances assigned to your account yet.</p>
            <p class="text-muted">Contact your administrator to have resources assigned to you.</p>
        </div>
    </div>
    @endif
</div>

@push('scripts')
<script>
$(document).ready(function() {
    // Reboot server
    $('.reboot-btn').click(function() {
        if (!confirm('Are you sure you want to reboot this server?')) return;

        const btn = $(this);
        const serverId = btn.data('id');
        btn.prop('disabled', true);

        $.post(`/user/ovh/servers/${serverId}/reboot`, {
            _token: '{{ csrf_token() }}'
        })
        .done(function(response) {
            alert(response.message);
        })
        .fail(function(xhr) {
            alert('Error: ' + (xhr.responseJSON?.message || 'Failed to reboot server'));
        })
        .always(function() {
            btn.prop('disabled', false);
        });
    });

    // Start cloud instance
    $('.start-instance-btn').click(function() {
        const btn = $(this);
        const instanceId = btn.data('id');
        btn.prop('disabled', true);

        $.post(`/user/ovh/instances/${instanceId}/control`, {
            _token: '{{ csrf_token() }}',
            action: 'start'
        })
        .done(function(response) {
            alert(response.message);
            location.reload();
        })
        .fail(function(xhr) {
            alert('Error: ' + (xhr.responseJSON?.message || 'Failed to start instance'));
            btn.prop('disabled', false);
        });
    });

    // Stop cloud instance
    $('.stop-instance-btn').click(function() {
        if (!confirm('Are you sure you want to stop this instance?')) return;

        const btn = $(this);
        const instanceId = btn.data('id');
        btn.prop('disabled', true);

        $.post(`/user/ovh/instances/${instanceId}/control`, {
            _token: '{{ csrf_token() }}',
            action: 'stop'
        })
        .done(function(response) {
            alert(response.message);
            location.reload();
        })
        .fail(function(xhr) {
            alert('Error: ' + (xhr.responseJSON?.message || 'Failed to stop instance'));
            btn.prop('disabled', false);
        });
    });
});
</script>
@endpush
@endsection
