@extends('user.layouts.app')

@section('title', 'Backups')

@section('content')
<div class="container-fluid">
    <!-- Backup Actions -->
    <div class="row mb-4">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Backup Management</h3>
                </div>
                <div class="card-body">
                    <button type="button" class="btn btn-primary" onclick="createBackup('full')">
                        <i class="fas fa-database"></i> Create Full Backup
                    </button>
                    <button type="button" class="btn btn-info" onclick="createBackup('incremental')">
                        <i class="fas fa-layer-group"></i> Create Incremental Backup
                    </button>
                    <a href="{{ route('user.backups.schedules') }}" class="btn btn-secondary">
                        <i class="fas fa-calendar-alt"></i> Manage Schedules
                    </a>
                    <a href="{{ route('user.backups.destinations') }}" class="btn btn-secondary">
                        <i class="fas fa-server"></i> Remote Destinations
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Backup Statistics -->
    @if($account)
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card">
                <div class="card-body">
                    <h5>Total Backups</h5>
                    <h2>{{ $backups->total() }}</h2>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card">
                <div class="card-body">
                    <h5>Completed</h5>
                    <h2 class="text-success">{{ $backups->where('status', 'completed')->count() }}</h2>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card">
                <div class="card-body">
                    <h5>Active Schedules</h5>
                    <h2 class="text-info">{{ $schedules->where('is_active', true)->count() }}</h2>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card">
                <div class="card-body">
                    <h5>Total Size</h5>
                    <h2>{{ number_format($backups->sum('size') / 1024 / 1024 / 1024, 2) }} GB</h2>
                </div>
            </div>
        </div>
    </div>
    @endif

    <!-- Backups List -->
    <div class="row">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Your Backups</h3>
                </div>
                <div class="card-body">
                    <table class="table table-bordered table-hover">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Filename</th>
                                <th>Type</th>
                                <th>Size</th>
                                <th>Status</th>
                                <th>Created</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($backups as $backup)
                            <tr>
                                <td>{{ $backup->id }}</td>
                                <td>{{ $backup->filename }}</td>
                                <td>
                                    <span class="badge badge-primary">{{ ucfirst($backup->type) }}</span>
                                    @if($backup->is_encrypted)
                                        <span class="badge badge-warning">
                                            <i class="fas fa-lock"></i> Encrypted
                                        </span>
                                    @endif
                                </td>
                                <td>{{ $backup->human_size }}</td>
                                <td>
                                    @if($backup->status === 'completed')
                                        <span class="badge badge-success">Completed</span>
                                    @elseif($backup->status === 'processing')
                                        <span class="badge badge-info">Processing</span>
                                    @elseif($backup->status === 'failed')
                                        <span class="badge badge-danger" title="{{ $backup->error_message }}">Failed</span>
                                    @else
                                        <span class="badge badge-secondary">{{ ucfirst($backup->status) }}</span>
                                    @endif
                                </td>
                                <td>{{ $backup->created_at->format('Y-m-d H:i') }}</td>
                                <td>
                                    @if($backup->status === 'completed')
                                        <a href="{{ route('user.backups.restore', $backup->id) }}" class="btn btn-sm btn-success" title="Restore">
                                            <i class="fas fa-undo"></i>
                                        </a>
                                        <button class="btn btn-sm btn-primary" onclick="downloadBackup({{ $backup->id }})" title="Download">
                                            <i class="fas fa-download"></i>
                                        </button>
                                        <button class="btn btn-sm btn-info" onclick="previewBackup({{ $backup->id }})" title="Preview">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                        <button class="btn btn-sm btn-secondary" onclick="verifyBackup({{ $backup->id }})" title="Verify">
                                            <i class="fas fa-check-circle"></i>
                                        </button>
                                    @endif
                                    <button class="btn btn-sm btn-danger" onclick="deleteBackup({{ $backup->id }})" title="Delete">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="7" class="text-center">
                                    No backups found. Create your first backup to get started!
                                </td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>

                    <div class="mt-3">
                        {{ $backups->links() }}
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Preview Modal -->
<div class="modal fade" id="previewModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Backup Contents</h5>
                <button type="button" class="close" data-dismiss="modal">&times;</button>
            </div>
            <div class="modal-body" id="previewContent">
                <div class="text-center">
                    <i class="fas fa-spinner fa-spin fa-3x"></i>
                    <p>Loading backup contents...</p>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function createBackup(type) {
    if (confirm(`Create a ${type} backup? This may take several minutes.`)) {
        $.ajax({
            url: '{{ route("user.backups.create") }}',
            method: 'POST',
            data: { type: type },
            headers: {
                'X-CSRF-TOKEN': '{{ csrf_token() }}'
            },
            success: function(response) {
                if (response.success) {
                    alert('Backup started successfully! Refresh the page to see progress.');
                    location.reload();
                }
            },
            error: function(xhr) {
                alert('Error: ' + (xhr.responseJSON?.message || 'Failed to start backup'));
            }
        });
    }
}

function downloadBackup(id) {
    window.location.href = '/user/backups/' + id + '/download';
}

function previewBackup(id) {
    $('#previewModal').modal('show');
    $('#previewContent').html('<div class="text-center"><i class="fas fa-spinner fa-spin fa-3x"></i></div>');

    $.ajax({
        url: '/user/backups/' + id + '/preview',
        method: 'GET',
        success: function(response) {
            if (response.success) {
                const preview = response.preview.preview;
                let html = '<div class="row">';
                html += '<div class="col-md-4"><div class="card"><div class="card-body">';
                html += '<h5>Files</h5><p class="h3">' + response.preview.file_count + '</p></div></div></div>';
                html += '<div class="col-md-4"><div class="card"><div class="card-body">';
                html += '<h5>Databases</h5><p class="h3">' + response.preview.database_count + '</p></div></div></div>';
                html += '<div class="col-md-4"><div class="card"><div class="card-body">';
                html += '<h5>Emails</h5><p class="h3">' + response.preview.email_count + '</p></div></div></div>';
                html += '</div>';

                if (preview.databases.length > 0) {
                    html += '<h5 class="mt-3">Databases:</h5><ul>';
                    preview.databases.forEach(db => {
                        html += '<li>' + db + '</li>';
                    });
                    html += '</ul>';
                }

                $('#previewContent').html(html);
            }
        },
        error: function(xhr) {
            $('#previewContent').html('<div class="alert alert-danger">Failed to load preview</div>');
        }
    });
}

function verifyBackup(id) {
    if (confirm('Verify backup integrity?')) {
        $.ajax({
            url: '/user/backups/' + id + '/verify',
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': '{{ csrf_token() }}'
            },
            success: function(response) {
                alert(response.message);
            },
            error: function(xhr) {
                alert('Verification failed: ' + (xhr.responseJSON?.message || 'Unknown error'));
            }
        });
    }
}

function deleteBackup(id) {
    if (confirm('Are you sure you want to delete this backup? This action cannot be undone.')) {
        $.ajax({
            url: '/user/backups/' + id,
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
                alert('Error: ' + (xhr.responseJSON?.message || 'Failed to delete backup'));
            }
        });
    }
}
</script>
@endsection
