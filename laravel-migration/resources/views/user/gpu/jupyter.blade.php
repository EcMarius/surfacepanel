@extends('layouts.user')

@section('title', 'Jupyter Notebook/Lab')

@section('content')
<div class="container-fluid">
    <div class="row mb-4">
        <div class="col-12">
            <h1 class="h3 mb-3">Jupyter Notebook/Lab Management</h1>
        </div>
    </div>

    @if($allocations->isEmpty())
        <div class="alert alert-warning">
            <h4>No GPU Allocated</h4>
            <p>You need a GPU allocation to launch Jupyter. Please contact administrator.</p>
        </div>
    @else
        <!-- Launch Jupyter -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h5 class="card-title mb-0">
                            <i class="fas fa-rocket"></i> Launch New Jupyter Instance
                        </h5>
                    </div>
                    <div class="card-body">
                        <form id="launchForm">
                            <div class="row">
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label>GPU Allocation</label>
                                        <select class="form-control" name="allocation_id" required>
                                            <option value="">Select GPU</option>
                                            @foreach($allocations as $allocation)
                                                <option value="{{ $allocation->id }}">
                                                    GPU {{ $allocation->gpuDevice->index }} - {{ $allocation->gpuDevice->name }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label>Type</label>
                                        <select class="form-control" name="type" required>
                                            <option value="jupyterlab">JupyterLab (Recommended)</option>
                                            <option value="jupyter">Jupyter Notebook</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label>ML Framework</label>
                                        <select class="form-control" name="framework_id">
                                            <option value="">Default (TensorFlow)</option>
                                            @foreach($frameworks as $framework)
                                                <option value="{{ $framework->id }}">
                                                    {{ $framework->full_name }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-2">
                                    <div class="form-group">
                                        <label>&nbsp;</label>
                                        <button type="button" class="btn btn-primary btn-block" id="launchBtn">
                                            <i class="fas fa-play"></i> Launch
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <!-- Running Jupyter Instances -->
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Running Jupyter Instances</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Container Name</th>
                                        <th>Type</th>
                                        <th>Framework</th>
                                        <th>GPU</th>
                                        <th>Port</th>
                                        <th>Status</th>
                                        <th>Uptime</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($containers as $container)
                                    <tr>
                                        <td>
                                            <code>{{ $container->container_name }}</code>
                                        </td>
                                        <td>
                                            <span class="badge badge-primary">{{ ucfirst($container->type) }}</span>
                                        </td>
                                        <td>
                                            {{ $container->framework ? $container->framework->full_name : 'Default' }}
                                        </td>
                                        <td>
                                            <strong>GPU {{ $container->allocation->gpuDevice->index }}</strong><br>
                                            <small class="text-muted">{{ $container->allocation->gpuDevice->name }}</small>
                                        </td>
                                        <td>{{ $container->port }}</td>
                                        <td>
                                            <span class="badge badge-{{ $container->status == 'running' ? 'success' : 'secondary' }}">
                                                {{ ucfirst($container->status) }}
                                            </span>
                                        </td>
                                        <td>
                                            @if($container->uptime())
                                                {{ number_format($container->uptime(), 1) }}h
                                            @else
                                                N/A
                                            @endif
                                        </td>
                                        <td>
                                            @if($container->isRunning())
                                                <a href="{{ $container->getJupyterUrl() }}" target="_blank"
                                                   class="btn btn-sm btn-success" title="Open Jupyter">
                                                    <i class="fas fa-external-link-alt"></i> Open
                                                </a>
                                                <button class="btn btn-sm btn-info view-logs"
                                                        data-id="{{ $container->id }}" title="View Logs">
                                                    <i class="fas fa-file-alt"></i>
                                                </button>
                                                <button class="btn btn-sm btn-warning stop-container"
                                                        data-id="{{ $container->id }}" title="Stop">
                                                    <i class="fas fa-stop"></i>
                                                </button>
                                            @else
                                                <button class="btn btn-sm btn-success start-container"
                                                        data-id="{{ $container->id }}" title="Start">
                                                    <i class="fas fa-play"></i>
                                                </button>
                                            @endif
                                            <button class="btn btn-sm btn-danger remove-container"
                                                    data-id="{{ $container->id }}" title="Remove">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </td>
                                    </tr>
                                    @empty
                                    <tr>
                                        <td colspan="8" class="text-center">
                                            No running Jupyter instances. Launch one using the form above.
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

        <!-- Quick Start Guide -->
        <div class="row mt-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Quick Start Guide</h5>
                    </div>
                    <div class="card-body">
                        <h6>Getting Started with GPU-Enabled Jupyter:</h6>
                        <ol>
                            <li>Select your GPU allocation and preferred framework</li>
                            <li>Click "Launch" to start a new Jupyter instance</li>
                            <li>Wait for the container to start (usually 30-60 seconds)</li>
                            <li>Click "Open" to access your Jupyter environment</li>
                            <li>Test GPU access with: <code>import torch; print(torch.cuda.is_available())</code></li>
                        </ol>
                        <h6 class="mt-3">Available Frameworks:</h6>
                        <ul>
                            <li><strong>PyTorch:</strong> Deep learning framework with dynamic computation graphs</li>
                            <li><strong>TensorFlow:</strong> End-to-end machine learning platform</li>
                            <li><strong>JAX:</strong> High-performance numerical computing</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>

<!-- Logs Modal -->
<div class="modal fade" id="logsModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Container Logs</h5>
                <button type="button" class="close" data-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <pre id="logsContent" style="max-height: 400px; overflow-y: auto; background: #f5f5f5; padding: 10px;"></pre>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
$(document).ready(function() {
    // Launch Jupyter
    $('#launchBtn').click(function() {
        const btn = $(this);
        const formData = $('#launchForm').serialize();

        btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Launching...');

        $.ajax({
            url: '{{ route("user.gpu.launch-jupyter") }}',
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': '{{ csrf_token() }}'
            },
            data: formData,
            success: function(response) {
                if (response.success) {
                    alert('Jupyter launched successfully! It may take a minute to be ready.');
                    location.reload();
                }
            },
            error: function(xhr) {
                alert('Error: ' + (xhr.responseJSON?.message || 'Failed to launch Jupyter'));
            },
            complete: function() {
                btn.prop('disabled', false).html('<i class="fas fa-play"></i> Launch');
            }
        });
    });

    // View logs
    $('.view-logs').click(function() {
        const containerId = $(this).data('id');

        $.ajax({
            url: `/user/gpu/container/${containerId}/logs`,
            method: 'GET',
            headers: {
                'X-CSRF-TOKEN': '{{ csrf_token() }}'
            },
            success: function(response) {
                if (response.success) {
                    $('#logsContent').text(response.logs);
                    $('#logsModal').modal('show');
                }
            },
            error: function(xhr) {
                alert('Error: ' + (xhr.responseJSON?.message || 'Failed to get logs'));
            }
        });
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
