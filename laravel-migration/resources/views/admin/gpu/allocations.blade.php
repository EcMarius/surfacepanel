@extends('layouts.admin')

@section('title', 'GPU Allocations Management')

@section('content')
<div class="container-fluid">
    <div class="row mb-4">
        <div class="col-12">
            <h1 class="h3 mb-3">GPU Allocations Management</h1>
        </div>
    </div>

    <!-- Action Buttons -->
    <div class="row mb-3">
        <div class="col-12">
            <button class="btn btn-primary" data-toggle="modal" data-target="#allocateModal">
                <i class="fas fa-plus"></i> Allocate GPU
            </button>
            <a href="{{ route('admin.gpu.index') }}" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Back to GPU Management
            </a>
        </div>
    </div>

    <!-- Active Allocations -->
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">Active GPU Allocations</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover" id="allocationsTable">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Account</th>
                                    <th>GPU Device</th>
                                    <th>Memory Allocated</th>
                                    <th>Compute %</th>
                                    <th>Mode</th>
                                    <th>Status</th>
                                    <th>Allocated Date</th>
                                    <th>Expires</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($allocations as $allocation)
                                <tr>
                                    <td>{{ $allocation->id }}</td>
                                    <td>
                                        <strong>{{ $allocation->account->username }}</strong><br>
                                        <small class="text-muted">{{ $allocation->account->domain }}</small>
                                    </td>
                                    <td>
                                        <strong>GPU {{ $allocation->gpuDevice->index }}</strong><br>
                                        <small class="text-muted">{{ $allocation->gpuDevice->name }}</small>
                                    </td>
                                    <td>{{ $allocation->memory_allocated }} MB</td>
                                    <td>
                                        <div class="progress" style="height: 20px;">
                                            <div class="progress-bar bg-success" role="progressbar"
                                                 style="width: {{ $allocation->compute_percentage }}%"
                                                 aria-valuenow="{{ $allocation->compute_percentage }}"
                                                 aria-valuemin="0" aria-valuemax="100">
                                                {{ $allocation->compute_percentage }}%
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="badge badge-info">{{ ucfirst($allocation->allocation_mode) }}</span>
                                        @if($allocation->mig_profile)
                                            <br><small class="text-muted">{{ $allocation->mig_profile }}</small>
                                        @endif
                                    </td>
                                    <td>
                                        <span class="badge badge-{{ $allocation->status == 'active' ? 'success' : 'secondary' }}">
                                            {{ ucfirst($allocation->status) }}
                                        </span>
                                    </td>
                                    <td>{{ $allocation->allocated_at->format('Y-m-d H:i') }}</td>
                                    <td>
                                        @if($allocation->expires_at)
                                            {{ $allocation->expires_at->format('Y-m-d') }}
                                            @if($allocation->hasExpired())
                                                <span class="badge badge-danger">Expired</span>
                                            @elseif($allocation->daysUntilExpiration() <= 7)
                                                <span class="badge badge-warning">{{ $allocation->daysUntilExpiration() }}d left</span>
                                            @endif
                                        @else
                                            <span class="text-muted">Never</span>
                                        @endif
                                    </td>
                                    <td>
                                        <button class="btn btn-sm btn-danger deallocate-btn" data-id="{{ $allocation->id }}">
                                            <i class="fas fa-times"></i> Deallocate
                                        </button>
                                    </td>
                                </tr>
                                @empty
                                <tr>
                                    <td colspan="10" class="text-center">No active allocations</td>
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

<!-- Allocate GPU Modal -->
<div class="modal fade" id="allocateModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Allocate GPU to Account</h5>
                <button type="button" class="close" data-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <form id="allocateForm">
                    <div class="form-group">
                        <label>Account</label>
                        <select class="form-control" name="account_id" required>
                            <option value="">Select Account</option>
                            @foreach($accounts as $account)
                                <option value="{{ $account->id }}">
                                    {{ $account->username }} ({{ $account->domain }})
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="form-group">
                        <label>GPU Device</label>
                        <select class="form-control" name="gpu_device_id" required>
                            <option value="">Select GPU</option>
                            @foreach($availableGPUs as $gpu)
                                <option value="{{ $gpu->id }}">
                                    GPU {{ $gpu->index }}: {{ $gpu->name }} ({{ $gpu->memory_total }}MB)
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Memory Allocated (MB)</label>
                        <input type="number" class="form-control" name="memory_allocated"
                               min="0" required>
                    </div>

                    <div class="form-group">
                        <label>Compute Percentage</label>
                        <input type="number" class="form-control" name="compute_percentage"
                               min="0" max="100" value="100" required>
                        <small class="form-text text-muted">
                            Percentage of GPU compute power allocated (0-100)
                        </small>
                    </div>

                    <div class="form-group">
                        <label>Allocation Mode</label>
                        <select class="form-control" name="allocation_mode" required>
                            <option value="exclusive">Exclusive (Full GPU)</option>
                            <option value="shared">Shared (Multiple users)</option>
                            <option value="mig">MIG (Multi-Instance GPU - A100/H100)</option>
                        </select>
                    </div>

                    <div class="form-group" id="migProfileGroup" style="display: none;">
                        <label>MIG Profile</label>
                        <select class="form-control" name="mig_profile">
                            <option value="">Select MIG Profile</option>
                            <option value="1g.5gb">1g.5gb (1 GPU slice, 5GB)</option>
                            <option value="2g.10gb">2g.10gb (2 GPU slices, 10GB)</option>
                            <option value="3g.20gb">3g.20gb (3 GPU slices, 20GB)</option>
                            <option value="4g.20gb">4g.20gb (4 GPU slices, 20GB)</option>
                            <option value="7g.40gb">7g.40gb (7 GPU slices, 40GB)</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Expiration Date (Optional)</label>
                        <input type="date" class="form-control" name="expires_at">
                        <small class="form-text text-muted">
                            Leave empty for no expiration
                        </small>
                    </div>

                    <div class="form-group">
                        <label>Notes</label>
                        <textarea class="form-control" name="notes" rows="3"></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="allocateBtn">Allocate GPU</button>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
$(document).ready(function() {
    // Show/hide MIG profile based on allocation mode
    $('select[name="allocation_mode"]').change(function() {
        if ($(this).val() === 'mig') {
            $('#migProfileGroup').show();
        } else {
            $('#migProfileGroup').hide();
        }
    });

    // Allocate GPU
    $('#allocateBtn').click(function() {
        const btn = $(this);
        const formData = $('#allocateForm').serialize();

        btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Allocating...');

        $.ajax({
            url: '{{ route("admin.gpu.allocate") }}',
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': '{{ csrf_token() }}'
            },
            data: formData,
            success: function(response) {
                if (response.success) {
                    alert(response.message);
                    location.reload();
                }
            },
            error: function(xhr) {
                alert('Error: ' + (xhr.responseJSON?.message || 'Failed to allocate GPU'));
            },
            complete: function() {
                btn.prop('disabled', false).html('Allocate GPU');
            }
        });
    });

    // Deallocate GPU
    $('.deallocate-btn').click(function() {
        if (!confirm('Are you sure you want to deallocate this GPU?')) return;

        const allocationId = $(this).data('id');
        const btn = $(this);

        btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i>');

        $.ajax({
            url: `/admin/gpu/allocation/${allocationId}/deallocate`,
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': '{{ csrf_token() }}'
            },
            success: function(response) {
                if (response.success) {
                    alert(response.message);
                    location.reload();
                }
            },
            error: function(xhr) {
                alert('Error: ' + (xhr.responseJSON?.message || 'Failed to deallocate GPU'));
            },
            complete: function() {
                btn.prop('disabled', false).html('<i class="fas fa-times"></i> Deallocate');
            }
        });
    });
});
</script>
@endpush
@endsection
