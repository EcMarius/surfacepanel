@extends('layouts.admin')

@section('title', 'OVH Cloud Integration')

@section('content')
<div class="container-fluid">
    <div class="row mb-4">
        <div class="col-md-12">
            <h1 class="h3 mb-3">OVH Cloud Integration</h1>

            @if(!$config->isConfigured())
                <div class="alert alert-warning">
                    <i class="fas fa-exclamation-triangle"></i>
                    OVH API is not configured. Please <a href="{{ route('admin.ovh.config') }}">configure API credentials</a> first.
                </div>
            @elseif(!$config->is_active)
                <div class="alert alert-info">
                    <i class="fas fa-info-circle"></i>
                    OVH integration is configured but not active. <a href="{{ route('admin.ovh.config') }}">Activate it here</a>.
                </div>
            @endif
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
                            <div class="h5 mb-0 font-weight-bold text-gray-800">{{ $stats['total_servers'] }}</div>
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
                            <div class="h5 mb-0 font-weight-bold text-gray-800">{{ $stats['active_servers'] }}</div>
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
                            <div class="h5 mb-0 font-weight-bold text-gray-800">{{ $stats['cloud_instances'] }}</div>
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
                            <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">Monthly Cost</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">€{{ number_format($stats['monthly_cost'], 2) }}</div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-euro-sign fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Quick Actions -->
    <div class="row mb-4">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header">
                    <h6 class="m-0 font-weight-bold text-primary">Quick Actions</h6>
                </div>
                <div class="card-body">
                    <a href="{{ route('admin.ovh.config') }}" class="btn btn-primary mr-2">
                        <i class="fas fa-cog"></i> Configure API
                    </a>
                    <a href="{{ route('admin.ovh.servers') }}" class="btn btn-info mr-2">
                        <i class="fas fa-server"></i> Manage Servers
                    </a>
                    <a href="{{ route('admin.ovh.provision') }}" class="btn btn-success mr-2">
                        <i class="fas fa-plus"></i> Provision Server
                    </a>
                    <button id="syncServersBtn" class="btn btn-warning mr-2">
                        <i class="fas fa-sync"></i> Sync from OVH
                    </button>
                    <a href="{{ route('admin.ovh.billing') }}" class="btn btn-secondary">
                        <i class="fas fa-file-invoice-dollar"></i> View Billing
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Recent Servers -->
    <div class="row mb-4">
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h6 class="m-0 font-weight-bold text-primary">Recent Servers</h6>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>Service Name</th>
                                    <th>Type</th>
                                    <th>IP</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($recentServers as $server)
                                <tr>
                                    <td>{{ $server->service_name }}</td>
                                    <td><span class="badge badge-info">{{ $server->server_type }}</span></td>
                                    <td>{{ $server->ip_address }}</td>
                                    <td>
                                        <span class="badge badge-{{ $server->status === 'active' ? 'success' : 'warning' }}">
                                            {{ $server->status }}
                                        </span>
                                    </td>
                                </tr>
                                @empty
                                <tr>
                                    <td colspan="4" class="text-center">No servers found</td>
                                </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Recent Billing -->
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h6 class="m-0 font-weight-bold text-primary">Recent Billing</h6>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Server</th>
                                    <th>Amount</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($recentBilling as $bill)
                                <tr>
                                    <td>{{ $bill->billing_date->format('Y-m-d') }}</td>
                                    <td>{{ $bill->server->service_name ?? 'N/A' }}</td>
                                    <td>€{{ number_format($bill->amount, 2) }}</td>
                                    <td>
                                        <span class="badge badge-{{ $bill->status === 'paid' ? 'success' : ($bill->status === 'overdue' ? 'danger' : 'warning') }}">
                                            {{ $bill->status }}
                                        </span>
                                    </td>
                                </tr>
                                @empty
                                <tr>
                                    <td colspan="4" class="text-center">No billing records found</td>
                                </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
$(document).ready(function() {
    $('#syncServersBtn').click(function() {
        const btn = $(this);
        btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Syncing...');

        $.post('{{ route("admin.ovh.sync-servers") }}', {
            _token: '{{ csrf_token() }}'
        })
        .done(function(response) {
            if (response.success) {
                showNotification('success', response.message);
                setTimeout(() => location.reload(), 2000);
            } else {
                showNotification('error', response.message);
            }
        })
        .fail(function(xhr) {
            showNotification('error', xhr.responseJSON?.message || 'Failed to sync servers');
        })
        .always(function() {
            btn.prop('disabled', false).html('<i class="fas fa-sync"></i> Sync from OVH');
        });
    });
});
</script>
@endpush
@endsection
