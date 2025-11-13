@extends('admin.layouts.app')

@section('title', 'Backup Destinations')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h3 class="card-title">Remote Backup Destinations</h3>
                    <button type="button" class="btn btn-primary" data-toggle="modal" data-target="#createDestinationModal">
                        <i class="fas fa-plus"></i> Add Destination
                    </button>
                </div>

                <div class="card-body">
                    <table class="table table-bordered table-hover">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Name</th>
                                <th>Type</th>
                                <th>Hostname</th>
                                <th>Path</th>
                                <th>Status</th>
                                <th>Last Tested</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($destinations as $destination)
                            <tr>
                                <td>{{ $destination->id }}</td>
                                <td>{{ $destination->name }}</td>
                                <td>
                                    <span class="badge badge-secondary">{{ strtoupper($destination->type) }}</span>
                                </td>
                                <td>{{ $destination->hostname ?? 'N/A' }}</td>
                                <td>{{ $destination->path }}</td>
                                <td>
                                    @if($destination->connection_status === 'success')
                                        <span class="badge badge-success">Connected</span>
                                    @elseif($destination->connection_status === 'failed')
                                        <span class="badge badge-danger" title="{{ $destination->connection_error }}">Failed</span>
                                    @else
                                        <span class="badge badge-warning">Untested</span>
                                    @endif
                                </td>
                                <td>{{ $destination->last_tested_at ? $destination->last_tested_at->diffForHumans() : 'Never' }}</td>
                                <td>
                                    <button class="btn btn-sm btn-success" onclick="testDestination({{ $destination->id }})">
                                        <i class="fas fa-plug"></i> Test
                                    </button>
                                    <button class="btn btn-sm btn-danger" onclick="deleteDestination({{ $destination->id }})">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="8" class="text-center">No destinations configured</td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>

                    <div class="mt-3">
                        {{ $destinations->links() }}
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Create Destination Modal -->
<div class="modal fade" id="createDestinationModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add Remote Destination</h5>
                <button type="button" class="close" data-dismiss="modal">&times;</button>
            </div>
            <form id="createDestinationForm">
                <div class="modal-body">
                    <div class="form-group">
                        <label>Name</label>
                        <input type="text" name="name" class="form-control" required>
                    </div>

                    <div class="form-group">
                        <label>Type</label>
                        <select name="type" id="destination_type" class="form-control" required>
                            <option value="local">Local Path</option>
                            <option value="ftp">FTP</option>
                            <option value="sftp">SFTP</option>
                            <option value="ssh">SSH/SCP</option>
                            <option value="s3">Amazon S3</option>
                        </select>
                    </div>

                    <div class="form-group" id="hostname_group">
                        <label>Hostname/Server</label>
                        <input type="text" name="hostname" class="form-control">
                    </div>

                    <div class="form-group" id="port_group">
                        <label>Port</label>
                        <input type="number" name="port" class="form-control" placeholder="Default port will be used if empty">
                    </div>

                    <div class="form-group" id="username_group">
                        <label>Username</label>
                        <input type="text" name="username" class="form-control">
                    </div>

                    <div class="form-group" id="password_group">
                        <label>Password</label>
                        <input type="password" name="password" class="form-control">
                    </div>

                    <div class="form-group">
                        <label>Path/Directory</label>
                        <input type="text" name="path" class="form-control" required>
                        <small class="form-text text-muted">For S3, enter bucket name. For others, enter full path.</small>
                    </div>

                    <div class="form-group" id="s3_region_group" style="display:none;">
                        <label>AWS Region</label>
                        <input type="text" name="region" class="form-control" placeholder="e.g., us-east-1">
                    </div>

                    <div class="form-group" id="s3_keys_group" style="display:none;">
                        <label>AWS Access Key</label>
                        <input type="text" name="access_key" class="form-control">
                        <label class="mt-2">AWS Secret Key</label>
                        <input type="password" name="secret_key" class="form-control">
                    </div>

                    <div class="custom-control custom-checkbox">
                        <input type="checkbox" class="custom-control-input" id="is_active" name="is_active" checked>
                        <label class="custom-control-label" for="is_active">Active</label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Add Destination</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    $('#destination_type').on('change', function() {
        const type = $(this).val();
        const isLocal = type === 'local';
        const isS3 = type === 's3';

        $('#hostname_group, #port_group, #username_group, #password_group').toggle(!isLocal);
        $('#s3_region_group, #s3_keys_group').toggle(isS3);
    });

    $('#createDestinationForm').on('submit', function(e) {
        e.preventDefault();

        $.ajax({
            url: '{{ route("admin.backups.destinations.store") }}',
            method: 'POST',
            data: $(this).serialize(),
            headers: {
                'X-CSRF-TOKEN': '{{ csrf_token() }}'
            },
            success: function(response) {
                if (response.success) {
                    alert('Destination added successfully');
                    location.reload();
                }
            },
            error: function(xhr) {
                alert('Error: ' + (xhr.responseJSON?.message || 'Failed to add destination'));
            }
        });
    });
});

function testDestination(id) {
    $.ajax({
        url: '/admin/backups/destinations/' + id + '/test',
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': '{{ csrf_token() }}'
        },
        success: function(response) {
            if (response.success) {
                alert('Connection successful!');
                location.reload();
            } else {
                alert('Connection failed: ' + response.error);
            }
        },
        error: function(xhr) {
            alert('Test failed: ' + (xhr.responseJSON?.message || 'Unknown error'));
        }
    });
}

function deleteDestination(id) {
    if (confirm('Are you sure you want to delete this destination?')) {
        $.ajax({
            url: '/admin/backups/destinations/' + id,
            method: 'DELETE',
            headers: {
                'X-CSRF-TOKEN': '{{ csrf_token() }}'
            },
            success: function(response) {
                if (response.success) {
                    location.reload();
                }
            },
            error: function(xhr) {
                alert('Error: ' + (xhr.responseJSON?.message || 'Failed to delete destination'));
            }
        });
    }
}
</script>
@endsection
