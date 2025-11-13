@extends('layouts.admin')

@section('title', 'OVH Servers')

@section('content')
<div class="container-fluid">
    <div class="row mb-4">
        <div class="col-md-12 d-flex justify-content-between align-items-center">
            <h1 class="h3 mb-0">OVH Servers</h1>
            <div>
                <a href="{{ route('admin.ovh.provision') }}" class="btn btn-success">
                    <i class="fas fa-plus"></i> Provision Server
                </a>
                <button id="syncServersBtn" class="btn btn-primary">
                    <i class="fas fa-sync"></i> Sync from OVH
                </button>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <div class="row">
                <div class="col-md-6">
                    <h6 class="m-0 font-weight-bold text-primary">Servers List</h6>
                </div>
                <div class="col-md-6 text-right">
                    <div class="btn-group" role="group">
                        <button type="button" class="btn btn-sm btn-outline-primary filter-btn" data-filter="all">All</button>
                        @foreach($serverTypes as $type)
                        <button type="button" class="btn btn-sm btn-outline-primary filter-btn" data-filter="{{ $type }}">
                            {{ ucfirst($type) }}
                        </button>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover" id="serversTable">
                    <thead>
                        <tr>
                            <th>Service Name</th>
                            <th>Type</th>
                            <th>IP Address</th>
                            <th>Datacenter</th>
                            <th>Specifications</th>
                            <th>Account</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($servers as $server)
                        <tr data-type="{{ $server->server_type }}">
                            <td>
                                <strong>{{ $server->display_name ?? $server->service_name }}</strong><br>
                                <small class="text-muted">{{ $server->service_name }}</small>
                            </td>
                            <td><span class="badge badge-info">{{ $server->server_type }}</span></td>
                            <td>{{ $server->ip_address ?? 'N/A' }}</td>
                            <td>{{ $server->datacenter ?? 'N/A' }}</td>
                            <td>
                                <small>{{ $server->getSpecifications() }}</small>
                            </td>
                            <td>{{ $server->account->username ?? 'Unassigned' }}</td>
                            <td>
                                <span class="badge badge-{{ $server->status === 'active' ? 'success' : ($server->status === 'suspended' ? 'warning' : 'danger') }}">
                                    {{ ucfirst($server->status) }}
                                </span>
                            </td>
                            <td>
                                <div class="btn-group" role="group">
                                    <button type="button" class="btn btn-sm btn-primary view-details-btn"
                                            data-id="{{ $server->id }}" title="View Details">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                    <button type="button" class="btn btn-sm btn-warning reboot-btn"
                                            data-id="{{ $server->id }}" title="Reboot">
                                        <i class="fas fa-redo"></i>
                                    </button>
                                    <button type="button" class="btn btn-sm btn-info reinstall-btn"
                                            data-id="{{ $server->id }}" title="Reinstall">
                                        <i class="fas fa-download"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="8" class="text-center">No servers found. Click "Sync from OVH" to import servers.</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{ $servers->links() }}
        </div>
    </div>
</div>

<!-- Server Details Modal -->
<div class="modal fade" id="serverDetailsModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Server Details</h5>
                <button type="button" class="close" data-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            <div class="modal-body" id="serverDetailsContent">
                <div class="text-center">
                    <i class="fas fa-spinner fa-spin fa-3x"></i>
                </div>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
$(document).ready(function() {
    // Filter servers by type
    $('.filter-btn').click(function() {
        const filter = $(this).data('filter');
        $('.filter-btn').removeClass('active');
        $(this).addClass('active');

        if (filter === 'all') {
            $('#serversTable tbody tr').show();
        } else {
            $('#serversTable tbody tr').hide();
            $('#serversTable tbody tr[data-type="' + filter + '"]').show();
        }
    });

    // Sync servers
    $('#syncServersBtn').click(function() {
        const btn = $(this);
        btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Syncing...');

        $.post('{{ route("admin.ovh.sync-servers") }}', {
            _token: '{{ csrf_token() }}'
        })
        .done(function(response) {
            if (response.success) {
                alert('Success: ' + response.message);
                location.reload();
            }
        })
        .fail(function(xhr) {
            alert('Error: ' + (xhr.responseJSON?.message || 'Failed to sync servers'));
        })
        .always(function() {
            btn.prop('disabled', false).html('<i class="fas fa-sync"></i> Sync from OVH');
        });
    });

    // View server details
    $('.view-details-btn').click(function() {
        const serverId = $(this).data('id');
        $('#serverDetailsModal').modal('show');

        $.get(`/admin/ovh/servers/${serverId}/details`)
        .done(function(response) {
            if (response.success) {
                const server = response.server;
                let html = `
                    <div class="row">
                        <div class="col-md-6">
                            <h6>Server Information</h6>
                            <table class="table table-sm">
                                <tr><td><strong>Service Name:</strong></td><td>${server.service_name}</td></tr>
                                <tr><td><strong>Type:</strong></td><td>${server.server_type}</td></tr>
                                <tr><td><strong>IP Address:</strong></td><td>${server.ip_address || 'N/A'}</td></tr>
                                <tr><td><strong>Datacenter:</strong></td><td>${server.datacenter || 'N/A'}</td></tr>
                                <tr><td><strong>OS:</strong></td><td>${server.os || 'N/A'}</td></tr>
                                <tr><td><strong>Status:</strong></td><td>${server.status}</td></tr>
                            </table>
                        </div>
                        <div class="col-md-6">
                            <h6>Specifications</h6>
                            <table class="table table-sm">
                                <tr><td><strong>CPU Cores:</strong></td><td>${server.cpu_cores || 'N/A'}</td></tr>
                                <tr><td><strong>RAM:</strong></td><td>${server.ram_mb ? (server.ram_mb / 1024).toFixed(2) + ' GB' : 'N/A'}</td></tr>
                                <tr><td><strong>Disk:</strong></td><td>${server.disk_gb || 'N/A'} GB</td></tr>
                                <tr><td><strong>Bandwidth:</strong></td><td>${server.bandwidth_mbps || 'N/A'} Mbps</td></tr>
                            </table>
                        </div>
                    </div>
                `;
                $('#serverDetailsContent').html(html);
            }
        })
        .fail(function() {
            $('#serverDetailsContent').html('<div class="alert alert-danger">Failed to load server details</div>');
        });
    });

    // Reboot server
    $('.reboot-btn').click(function() {
        if (!confirm('Are you sure you want to reboot this server?')) return;

        const btn = $(this);
        const serverId = btn.data('id');
        btn.prop('disabled', true);

        $.post(`/admin/ovh/servers/${serverId}/reboot`, {
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
});
</script>
@endpush
@endsection
