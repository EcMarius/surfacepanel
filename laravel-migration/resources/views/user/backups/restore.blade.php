@extends('user.layouts.app')

@section('title', 'Restore Backup')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Restore Backup: {{ $backup->filename }}</h3>
                    <a href="{{ route('user.backups.index') }}" class="btn btn-secondary float-right">
                        <i class="fas fa-arrow-left"></i> Back to Backups
                    </a>
                </div>

                <div class="card-body">
                    <!-- Backup Information -->
                    <div class="row mb-4">
                        <div class="col-md-3">
                            <div class="card">
                                <div class="card-body">
                                    <h6>Backup Type</h6>
                                    <p class="h4">{{ ucfirst($backup->type) }}</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card">
                                <div class="card-body">
                                    <h6>Size</h6>
                                    <p class="h4">{{ $backup->human_size }}</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card">
                                <div class="card-body">
                                    <h6>Created</h6>
                                    <p class="h4">{{ $backup->created_at->format('Y-m-d H:i') }}</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card">
                                <div class="card-body">
                                    <h6>Status</h6>
                                    <p class="h4">
                                        @if($backup->is_encrypted)
                                            <span class="badge badge-warning">
                                                <i class="fas fa-lock"></i> Encrypted
                                            </span>
                                        @endif
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Backup Contents Preview -->
                    @if($preview['success'])
                    <div class="row mb-4">
                        <div class="col-md-12">
                            <h4>Backup Contents</h4>
                            <div class="row">
                                <div class="col-md-4">
                                    <div class="card">
                                        <div class="card-body text-center">
                                            <h5>Files</h5>
                                            <p class="h2 text-primary">{{ $preview['file_count'] }}</p>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="card">
                                        <div class="card-body text-center">
                                            <h5>Databases</h5>
                                            <p class="h2 text-success">{{ $preview['database_count'] }}</p>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="card">
                                        <div class="card-body text-center">
                                            <h5>Email Accounts</h5>
                                            <p class="h2 text-info">{{ $preview['email_count'] }}</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    @endif

                    <!-- Restore Form -->
                    <div class="row">
                        <div class="col-md-12">
                            <form id="restoreForm">
                                <div class="card">
                                    <div class="card-header">
                                        <h4>Restore Options</h4>
                                    </div>
                                    <div class="card-body">
                                        <div class="alert alert-warning">
                                            <i class="fas fa-exclamation-triangle"></i>
                                            <strong>Warning:</strong> Restoring will replace your current files and data with the backup.
                                            This action cannot be undone. Make sure you understand what you're doing.
                                        </div>

                                        <h5 class="mb-3">What to Restore:</h5>

                                        <div class="row">
                                            <div class="col-md-6">
                                                <div class="custom-control custom-checkbox mb-3">
                                                    <input type="checkbox" class="custom-control-input" id="restore_files" name="restore_files" checked>
                                                    <label class="custom-control-label" for="restore_files">
                                                        <strong>Files</strong>
                                                        <small class="d-block text-muted">Restore all website files and directories</small>
                                                    </label>
                                                </div>

                                                <div class="custom-control custom-checkbox mb-3">
                                                    <input type="checkbox" class="custom-control-input" id="restore_databases" name="restore_databases" checked>
                                                    <label class="custom-control-label" for="restore_databases">
                                                        <strong>Databases</strong>
                                                        <small class="d-block text-muted">Restore all MySQL/MariaDB databases</small>
                                                    </label>
                                                </div>
                                            </div>

                                            <div class="col-md-6">
                                                <div class="custom-control custom-checkbox mb-3">
                                                    <input type="checkbox" class="custom-control-input" id="restore_emails" name="restore_emails" checked>
                                                    <label class="custom-control-label" for="restore_emails">
                                                        <strong>Email Accounts</strong>
                                                        <small class="d-block text-muted">Restore email accounts and mailboxes</small>
                                                    </label>
                                                </div>

                                                <div class="custom-control custom-checkbox mb-3">
                                                    <input type="checkbox" class="custom-control-input" id="restore_config" name="restore_config" checked>
                                                    <label class="custom-control-label" for="restore_config">
                                                        <strong>Configuration</strong>
                                                        <small class="d-block text-muted">Restore account settings and configuration</small>
                                                    </label>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Selective Restore -->
                                        <div class="mt-4">
                                            <h5>Or Selective Restore:</h5>
                                            <p class="text-muted">Choose specific files or databases to restore instead of everything</p>

                                            <button type="button" class="btn btn-outline-primary" data-toggle="modal" data-target="#selectFilesModal">
                                                <i class="fas fa-file"></i> Select Specific Files
                                            </button>

                                            <button type="button" class="btn btn-outline-success" data-toggle="modal" data-target="#selectDatabasesModal">
                                                <i class="fas fa-database"></i> Select Specific Databases
                                            </button>
                                        </div>

                                        <hr class="my-4">

                                        <div class="form-group">
                                            <div class="custom-control custom-checkbox">
                                                <input type="checkbox" class="custom-control-input" id="confirm_restore" required>
                                                <label class="custom-control-label" for="confirm_restore">
                                                    <strong>I understand that this will replace my current data with the backup</strong>
                                                </label>
                                            </div>
                                        </div>

                                        <button type="submit" class="btn btn-primary btn-lg">
                                            <i class="fas fa-undo"></i> Start Restore
                                        </button>
                                        <a href="{{ route('user.backups.index') }}" class="btn btn-secondary btn-lg">
                                            Cancel
                                        </a>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Select Files Modal -->
<div class="modal fade" id="selectFilesModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Select Files to Restore</h5>
                <button type="button" class="close" data-dismiss="modal">&times;</button>
            </div>
            <div class="modal-body">
                <p>Select specific files you want to restore from the backup:</p>
                <div id="filesList" style="max-height: 400px; overflow-y: auto;">
                    @if(isset($preview['preview']['files']) && count($preview['preview']['files']) > 0)
                        @foreach($preview['preview']['files'] as $file)
                            <div class="custom-control custom-checkbox">
                                <input type="checkbox" class="custom-control-input file-checkbox" id="file_{{ $loop->index }}" value="{{ $file }}">
                                <label class="custom-control-label" for="file_{{ $loop->index }}">{{ $file }}</label>
                            </div>
                        @endforeach
                    @else
                        <p class="text-muted">No files in backup</p>
                    @endif
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary" onclick="selectFiles()">Restore Selected Files</button>
            </div>
        </div>
    </div>
</div>

<!-- Select Databases Modal -->
<div class="modal fade" id="selectDatabasesModal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Select Databases to Restore</h5>
                <button type="button" class="close" data-dismiss="modal">&times;</button>
            </div>
            <div class="modal-body">
                <p>Select specific databases you want to restore:</p>
                @if(isset($preview['preview']['databases']) && count($preview['preview']['databases']) > 0)
                    @foreach($preview['preview']['databases'] as $db)
                        <div class="custom-control custom-checkbox">
                            <input type="checkbox" class="custom-control-input db-checkbox" id="db_{{ $loop->index }}" value="{{ $db }}">
                            <label class="custom-control-label" for="db_{{ $loop->index }}">{{ $db }}</label>
                        </div>
                    @endforeach
                @else
                    <p class="text-muted">No databases in backup</p>
                @endif
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary" onclick="selectDatabases()">Restore Selected Databases</button>
            </div>
        </div>
    </div>
</div>

<script>
let selectedFiles = [];
let selectedDatabases = [];

$('#restoreForm').on('submit', function(e) {
    e.preventDefault();

    if (!$('#confirm_restore').is(':checked')) {
        alert('Please confirm that you understand this will replace your current data');
        return;
    }

    if (confirm('Are you absolutely sure you want to restore this backup? This will replace your current data.')) {
        const formData = {
            restore_files: $('#restore_files').is(':checked'),
            restore_databases: $('#restore_databases').is(':checked'),
            restore_emails: $('#restore_emails').is(':checked'),
            restore_config: $('#restore_config').is(':checked'),
            specific_files: selectedFiles,
            specific_databases: selectedDatabases
        };

        $.ajax({
            url: '{{ route("user.backups.restore.process", $backup->id) }}',
            method: 'POST',
            data: formData,
            headers: {
                'X-CSRF-TOKEN': '{{ csrf_token() }}'
            },
            success: function(response) {
                if (response.success) {
                    alert('Restore completed successfully!');
                    window.location.href = '{{ route("user.backups.index") }}';
                }
            },
            error: function(xhr) {
                alert('Restore failed: ' + (xhr.responseJSON?.message || 'Unknown error'));
            }
        });
    }
});

function selectFiles() {
    selectedFiles = [];
    $('.file-checkbox:checked').each(function() {
        selectedFiles.push($(this).val());
    });

    if (selectedFiles.length > 0) {
        $('#selectFilesModal').modal('hide');
        // Uncheck the full restore options
        $('#restore_files, #restore_databases, #restore_emails, #restore_config').prop('checked', false);
        alert(selectedFiles.length + ' file(s) selected for restore');
    } else {
        alert('Please select at least one file');
    }
}

function selectDatabases() {
    selectedDatabases = [];
    $('.db-checkbox:checked').each(function() {
        selectedDatabases.push($(this).val());
    });

    if (selectedDatabases.length > 0) {
        $('#selectDatabasesModal').modal('hide');
        // Uncheck the full restore options
        $('#restore_files, #restore_databases, #restore_emails, #restore_config').prop('checked', false);
        alert(selectedDatabases.length + ' database(s) selected for restore');
    } else {
        alert('Please select at least one database');
    }
}
</script>
@endsection
