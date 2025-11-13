@extends('layouts.user')

@section('title', 'GPU Dashboard')

@section('content')
<div class="container-fluid">
    <div class="row mb-4">
        <div class="col-12">
            <h1 class="h3 mb-3">GPU Dashboard</h1>
        </div>
    </div>

    @if($allocations->isEmpty())
        <div class="alert alert-info">
            <h4>No GPU Allocated</h4>
            <p>You don't have any GPU allocated to your account. Please contact administrator to allocate a GPU.</p>
        </div>
    @else
        <!-- GPU Allocations -->
        <div class="row mb-4">
            @foreach($allocations as $allocation)
            <div class="col-md-6 mb-3">
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h5 class="card-title mb-0">
                            <i class="fas fa-microchip"></i> GPU {{ $allocation->gpuDevice->index }}
                        </h5>
                    </div>
                    <div class="card-body">
                        <h6>{{ $allocation->gpuDevice->name }}</h6>
                        <hr>
                        <div class="row">
                            <div class="col-6">
                                <p class="mb-2"><strong>Memory Allocated:</strong></p>
                                <p class="mb-2"><strong>Compute Power:</strong></p>
                                <p class="mb-2"><strong>Mode:</strong></p>
                                <p class="mb-0"><strong>Status:</strong></p>
                            </div>
                            <div class="col-6">
                                <p class="mb-2">{{ $allocation->memory_allocated }} MB</p>
                                <p class="mb-2">{{ $allocation->compute_percentage }}%</p>
                                <p class="mb-2">
                                    <span class="badge badge-info">{{ ucfirst($allocation->allocation_mode) }}</span>
                                </p>
                                <p class="mb-0">
                                    <span class="badge badge-success">Active</span>
                                </p>
                            </div>
                        </div>
                        <hr>
                        <div class="d-flex justify-content-between">
                            <button class="btn btn-info btn-sm view-utilization" data-id="{{ $allocation->id }}">
                                <i class="fas fa-chart-line"></i> View Utilization
                            </button>
                            <a href="{{ route('user.gpu.jupyter') }}" class="btn btn-primary btn-sm">
                                <i class="fas fa-play"></i> Launch Jupyter
                            </a>
                        </div>
                    </div>
                </div>
            </div>
            @endforeach
        </div>

        <!-- Running Containers -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Running Containers</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Name</th>
                                        <th>Type</th>
                                        <th>Framework</th>
                                        <th>GPU</th>
                                        <th>Status</th>
                                        <th>Started</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($containers as $container)
                                    <tr>
                                        <td>{{ $container->container_name }}</td>
                                        <td>
                                            <span class="badge badge-primary">{{ ucfirst($container->type) }}</span>
                                        </td>
                                        <td>{{ $container->framework ? $container->framework->full_name : 'Default' }}</td>
                                        <td>GPU {{ $container->allocation->gpuDevice->index }}</td>
                                        <td>
                                            <span class="badge badge-{{ $container->status == 'running' ? 'success' : 'secondary' }}">
                                                {{ ucfirst($container->status) }}
                                            </span>
                                        </td>
                                        <td>{{ $container->started_at ? $container->started_at->diffForHumans() : 'N/A' }}</td>
                                        <td>
                                            @if($container->isRunning())
                                                <a href="{{ $container->getJupyterUrl() }}" target="_blank"
                                                   class="btn btn-sm btn-success">
                                                    <i class="fas fa-external-link-alt"></i> Access
                                                </a>
                                                <button class="btn btn-sm btn-warning stop-container"
                                                        data-id="{{ $container->id }}">
                                                    <i class="fas fa-stop"></i>
                                                </button>
                                            @else
                                                <button class="btn btn-sm btn-success start-container"
                                                        data-id="{{ $container->id }}">
                                                    <i class="fas fa-play"></i>
                                                </button>
                                            @endif
                                            <button class="btn btn-sm btn-danger remove-container"
                                                    data-id="{{ $container->id }}">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </td>
                                    </tr>
                                    @empty
                                    <tr>
                                        <td colspan="7" class="text-center">No running containers</td>
                                    </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Available ML Frameworks -->
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Available ML Frameworks</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            @foreach($frameworks as $framework)
                            <div class="col-md-4 mb-3">
                                <div class="card border-primary">
                                    <div class="card-body">
                                        <h6 class="card-title">{{ $framework->full_name }}</h6>
                                        <p class="card-text text-muted">
                                            <small>
                                                CUDA: {{ $framework->cuda_version ?? 'Any' }}<br>
                                                Python: {{ $framework->python_version }}
                                            </small>
                                        </p>
                                        <p class="card-text">{{ $framework->description }}</p>
                                    </div>
                                </div>
                            </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>

@push('scripts')
<script>
$(document).ready(function() {
    // View utilization
    $('.view-utilization').click(function() {
        const allocationId = $(this).data('id');
        window.location.href = `/user/gpu/allocation/${allocationId}`;
    });

    // Stop container
    $('.stop-container').click(function() {
        if (!confirm('Are you sure you want to stop this container?')) return;

        const containerId = $(this).data('id');
        const btn = $(this);

        btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i>');

        $.ajax({
            url: `/user/gpu/container/${containerId}/stop`,
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
                alert('Error: ' + (xhr.responseJSON?.message || 'Failed to stop container'));
            },
            complete: function() {
                btn.prop('disabled', false).html('<i class="fas fa-stop"></i>');
            }
        });
    });

    // Start container
    $('.start-container').click(function() {
        const containerId = $(this).data('id');
        const btn = $(this);

        btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i>');

        $.ajax({
            url: `/user/gpu/container/${containerId}/start`,
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
                alert('Error: ' + (xhr.responseJSON?.message || 'Failed to start container'));
            },
            complete: function() {
                btn.prop('disabled', false).html('<i class="fas fa-play"></i>');
            }
        });
    });

    // Remove container
    $('.remove-container').click(function() {
        if (!confirm('Are you sure you want to remove this container? This action cannot be undone.')) return;

        const containerId = $(this).data('id');
        const btn = $(this);

        btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i>');

        $.ajax({
            url: `/user/gpu/container/${containerId}/remove`,
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
                alert('Error: ' + (xhr.responseJSON?.message || 'Failed to remove container'));
            },
            complete: function() {
                btn.prop('disabled', false).html('<i class="fas fa-trash"></i>');
            }
        });
    });
});
</script>
@endpush
@endsection
