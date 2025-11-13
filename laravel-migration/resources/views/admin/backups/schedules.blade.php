@extends('admin.layouts.app')

@section('title', 'Backup Schedules')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h3 class="card-title">Backup Schedules</h3>
                    <button type="button" class="btn btn-primary" data-toggle="modal" data-target="#createScheduleModal">
                        <i class="fas fa-plus"></i> Create Schedule
                    </button>
                </div>

                <div class="card-body">
                    <table class="table table-bordered table-hover">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Name</th>
                                <th>Account</th>
                                <th>Frequency</th>
                                <th>Type</th>
                                <th>Next Run</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($schedules as $schedule)
                            <tr>
                                <td>{{ $schedule->id }}</td>
                                <td>{{ $schedule->name }}</td>
                                <td>
                                    @if($schedule->is_system_wide)
                                        <span class="badge badge-info">System-wide</span>
                                    @else
                                        {{ $schedule->account->username ?? 'N/A' }}
                                    @endif
                                </td>
                                <td>{{ ucfirst($schedule->frequency) }}</td>
                                <td>{{ ucfirst($schedule->backup_type) }}</td>
                                <td>{{ $schedule->next_run_at ? $schedule->next_run_at->format('Y-m-d H:i') : 'Not scheduled' }}</td>
                                <td>
                                    @if($schedule->is_active)
                                        <span class="badge badge-success">Active</span>
                                    @else
                                        <span class="badge badge-secondary">Inactive</span>
                                    @endif
                                </td>
                                <td>
                                    <button class="btn btn-sm btn-info" onclick="editSchedule({{ $schedule->id }})">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button class="btn btn-sm btn-danger" onclick="deleteSchedule({{ $schedule->id }})">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="8" class="text-center">No backup schedules found</td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>

                    <div class="mt-3">
                        {{ $schedules->links() }}
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Create Schedule Modal -->
<div class="modal fade" id="createScheduleModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Create Backup Schedule</h5>
                <button type="button" class="close" data-dismiss="modal">&times;</button>
            </div>
            <form id="createScheduleForm">
                <div class="modal-body">
                    <div class="form-group">
                        <label>Name</label>
                        <input type="text" name="name" class="form-control" required>
                    </div>

                    <div class="form-group">
                        <label>Account (Optional - leave empty for system-wide)</label>
                        <select name="account_id" class="form-control">
                            <option value="">System-wide</option>
                            @foreach($accounts as $account)
                                <option value="{{ $account->id }}">{{ $account->username }} ({{ $account->domain }})</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Frequency</label>
                                <select name="frequency" class="form-control" required>
                                    <option value="daily">Daily</option>
                                    <option value="weekly">Weekly</option>
                                    <option value="monthly">Monthly</option>
                                    <option value="custom">Custom</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Backup Time</label>
                                <input type="time" name="backup_time" class="form-control" value="02:00" required>
                            </div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Backup Type</label>
                        <select name="backup_type" class="form-control" required>
                            <option value="full">Full Backup</option>
                            <option value="incremental">Incremental Backup</option>
                            <option value="files">Files Only</option>
                            <option value="databases">Databases Only</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Include in Backup:</label>
                        <div class="custom-control custom-checkbox">
                            <input type="checkbox" class="custom-control-input" id="include_files" name="include_files" checked>
                            <label class="custom-control-label" for="include_files">Files</label>
                        </div>
                        <div class="custom-control custom-checkbox">
                            <input type="checkbox" class="custom-control-input" id="include_databases" name="include_databases" checked>
                            <label class="custom-control-label" for="include_databases">Databases</label>
                        </div>
                        <div class="custom-control custom-checkbox">
                            <input type="checkbox" class="custom-control-input" id="include_emails" name="include_emails">
                            <label class="custom-control-label" for="include_emails">Emails</label>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Encryption</label>
                        <select name="encryption_id" class="form-control">
                            <option value="">No Encryption</option>
                            @foreach($encryptionKeys as $key)
                                <option value="{{ $key->id }}">{{ $key->name }} ({{ $key->method }})</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Remote Destinations</label>
                        <p class="text-muted small">Hold Ctrl/Cmd to select multiple destinations</p>
                    </div>

                    <div class="form-group">
                        <label>Rotation Policy</label>
                        <select name="rotation_id" class="form-control">
                            <option value="">No Rotation</option>
                            @foreach($rotationPolicies as $policy)
                                <option value="{{ $policy->id }}">{{ $policy->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="custom-control custom-checkbox">
                        <input type="checkbox" class="custom-control-input" id="is_active" name="is_active" checked>
                        <label class="custom-control-label" for="is_active">Active</label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Create Schedule</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    $('#createScheduleForm').on('submit', function(e) {
        e.preventDefault();

        $.ajax({
            url: '{{ route("admin.backups.schedules.store") }}',
            method: 'POST',
            data: $(this).serialize(),
            headers: {
                'X-CSRF-TOKEN': '{{ csrf_token() }}'
            },
            success: function(response) {
                if (response.success) {
                    alert('Schedule created successfully');
                    location.reload();
                }
            },
            error: function(xhr) {
                alert('Error: ' + (xhr.responseJSON?.message || 'Failed to create schedule'));
            }
        });
    });
});

function editSchedule(id) {
    // Implementation for edit
    alert('Edit schedule ' + id);
}

function deleteSchedule(id) {
    if (confirm('Are you sure you want to delete this schedule?')) {
        $.ajax({
            url: '/admin/backups/schedules/' + id,
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
                alert('Error: ' + (xhr.responseJSON?.message || 'Failed to delete schedule'));
            }
        });
    }
}
</script>
@endsection
