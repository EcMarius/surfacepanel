@extends('layouts.admin')

@section('title', 'GPU Server Management')

@section('content')
<div class="container-fluid">
    <div class="row mb-4">
        <div class="col-12">
            <h1 class="h3 mb-3">GPU Server Management</h1>
        </div>
    </div>

    <!-- Statistics Cards -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="card-subtitle mb-2 text-muted">Total GPUs</h6>
                            <h2 class="card-title mb-0">{{ $totalGPUs }}</h2>
                        </div>
                        <div class="text-primary">
                            <i class="fas fa-microchip fa-2x"></i>
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
                            <h6 class="card-subtitle mb-2 text-muted">Available</h6>
                            <h2 class="card-title mb-0 text-success">{{ $availableGPUs }}</h2>
                        </div>
                        <div class="text-success">
                            <i class="fas fa-check-circle fa-2x"></i>
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
                            <h6 class="card-subtitle mb-2 text-muted">Allocated</h6>
                            <h2 class="card-title mb-0 text-warning">{{ $allocatedGPUs }}</h2>
                        </div>
                        <div class="text-warning">
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
                            <h6 class="card-subtitle mb-2 text-muted">NVIDIA Driver</h6>
                            <p class="card-title mb-0">{{ $driverInfo['driver_version'] }}</p>
                            <small class="text-muted">CUDA: {{ $driverInfo['cuda_version'] }}</small>
                        </div>
                        <div class="text-info">
                            <i class="fab fa-nvidia fa-2x"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Action Buttons -->
    <div class="row mb-3">
        <div class="col-12">
            <button class="btn btn-primary" id="detectGPUsBtn">
                <i class="fas fa-search"></i> Detect GPUs
            </button>
            <button class="btn btn-info" id="installCUDABtn">
                <i class="fas fa-download"></i> Install CUDA Toolkit
            </button>
            <a href="{{ route('admin.gpu.allocations') }}" class="btn btn-success">
                <i class="fas fa-cog"></i> Manage Allocations
            </a>
            <button class="btn btn-secondary" id="refreshAllBtn">
                <i class="fas fa-sync"></i> Refresh All
            </button>
        </div>
    </div>

    <!-- GPU Devices List -->
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">Detected GPU Devices</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover" id="gpuTable">
                            <thead>
                                <tr>
                                    <th>Index</th>
                                    <th>Name</th>
                                    <th>Memory</th>
                                    <th>Temperature</th>
                                    <th>Power</th>
                                    <th>Utilization</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($gpus as $gpu)
                                <tr data-gpu-id="{{ $gpu->id }}">
                                    <td>{{ $gpu->index }}</td>
                                    <td>
                                        <strong>{{ $gpu->name }}</strong><br>
                                        <small class="text-muted">{{ $gpu->uuid }}</small>
                                    </td>
                                    <td>
                                        <div class="progress" style="height: 20px;">
                                            <div class="progress-bar" role="progressbar"
                                                 style="width: {{ $gpu->memoryUsagePercentage() }}%"
                                                 aria-valuenow="{{ $gpu->memoryUsagePercentage() }}"
                                                 aria-valuemin="0" aria-valuemax="100">
                                                {{ $gpu->memory_used }}MB / {{ $gpu->memory_total }}MB
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="badge badge-{{ $gpu->temperatureStatus() == 'normal' ? 'success' : ($gpu->temperatureStatus() == 'warm' ? 'warning' : 'danger') }}">
                                            {{ $gpu->temperature }}°C
                                        </span>
                                    </td>
                                    <td>{{ $gpu->power_draw }}W / {{ $gpu->power_limit }}W</td>
                                    <td>
                                        <div class="progress" style="height: 20px;">
                                            <div class="progress-bar bg-info" role="progressbar"
                                                 style="width: {{ $gpu->utilization }}%"
                                                 aria-valuenow="{{ $gpu->utilization }}"
                                                 aria-valuemin="0" aria-valuemax="100">
                                                {{ $gpu->utilization }}%
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="badge badge-{{ $gpu->status == 'available' ? 'success' : 'warning' }}">
                                            {{ ucfirst($gpu->status) }}
                                        </span>
                                    </td>
                                    <td>
                                        <button class="btn btn-sm btn-info refresh-gpu" data-id="{{ $gpu->id }}">
                                            <i class="fas fa-sync"></i>
                                        </button>
                                    </td>
                                </tr>
                                @empty
                                <tr>
                                    <td colspan="8" class="text-center">
                                        No GPUs detected. Click "Detect GPUs" to scan for NVIDIA GPUs.
                                    </td>
                                </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- CUDA Installations -->
    <div class="row mt-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">CUDA Toolkit Installations</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Version</th>
                                    <th>Install Path</th>
                                    <th>Components</th>
                                    <th>Status</th>
                                    <th>Installed</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($cudaInstallations as $cuda)
                                <tr>
                                    <td><strong>CUDA {{ $cuda->version }}</strong></td>
                                    <td>{{ $cuda->install_path }}</td>
                                    <td>
                                        @if($cuda->has_cudnn)
                                            <span class="badge badge-success">cuDNN {{ $cuda->cudnn_version }}</span>
                                        @endif
                                        @if($cuda->has_nccl)
                                            <span class="badge badge-success">NCCL</span>
                                        @endif
                                        @if($cuda->has_tensorrt)
                                            <span class="badge badge-success">TensorRT</span>
                                        @endif
                                    </td>
                                    <td>
                                        <span class="badge badge-{{ $cuda->status == 'active' ? 'success' : 'secondary' }}">
                                            {{ ucfirst($cuda->status) }}
                                        </span>
                                        @if($cuda->is_default)
                                            <span class="badge badge-primary">Default</span>
                                        @endif
                                    </td>
                                    <td>{{ $cuda->installed_at ? $cuda->installed_at->format('Y-m-d H:i') : 'N/A' }}</td>
                                </tr>
                                @empty
                                <tr>
                                    <td colspan="5" class="text-center">No CUDA installations found</td>
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
    // Detect GPUs
    $('#detectGPUsBtn').click(function() {
        const btn = $(this);
        btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Detecting...');

        $.ajax({
            url: '{{ route("admin.gpu.detect") }}',
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
                alert('Error: ' + (xhr.responseJSON?.message || 'Failed to detect GPUs'));
            },
            complete: function() {
                btn.prop('disabled', false).html('<i class="fas fa-search"></i> Detect GPUs');
            }
        });
    });

    // Refresh individual GPU
    $('.refresh-gpu').click(function() {
        const gpuId = $(this).data('id');
        const btn = $(this);
        btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i>');

        $.ajax({
            url: `/admin/gpu/${gpuId}/refresh`,
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': '{{ csrf_token() }}'
            },
            success: function(response) {
                if (response.success) {
                    location.reload();
                }
            },
            error: function(xhr) {
                alert('Error: ' + (xhr.responseJSON?.message || 'Failed to refresh GPU'));
            },
            complete: function() {
                btn.prop('disabled', false).html('<i class="fas fa-sync"></i>');
            }
        });
    });

    // Install CUDA
    $('#installCUDABtn').click(function() {
        const version = prompt('Enter CUDA version to install (e.g., 12.2, 11.8):');
        if (!version) return;

        const btn = $(this);
        btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Installing...');

        $.ajax({
            url: '{{ route("admin.gpu.install-cuda") }}',
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': '{{ csrf_token() }}'
            },
            data: { version: version },
            success: function(response) {
                if (response.success) {
                    alert(response.message);
                    location.reload();
                }
            },
            error: function(xhr) {
                alert('Error: ' + (xhr.responseJSON?.message || 'Failed to install CUDA'));
            },
            complete: function() {
                btn.prop('disabled', false).html('<i class="fas fa-download"></i> Install CUDA Toolkit');
            }
        });
    });
});
</script>
@endpush
@endsection
