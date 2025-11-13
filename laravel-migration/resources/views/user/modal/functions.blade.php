@extends('layouts.user')

@section('title', 'My Serverless Functions')

@section('content')
<div class="container-fluid">
    <div class="row mb-4">
        <div class="col-12 d-flex justify-content-between align-items-center">
            <div>
                <h1 class="h3 mb-0">My Serverless Functions</h1>
                <p class="text-muted mb-0">Deploy and manage your serverless functions with GPU support</p>
            </div>
            <a href="{{ route('user.modal.functions.create') }}" class="btn btn-primary">
                <i class="fas fa-plus"></i> Deploy New Function
            </a>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <div class="row align-items-center">
                <div class="col">
                    <h5 class="card-title mb-0">Functions List</h5>
                </div>
                <div class="col-auto">
                    <div class="btn-group btn-group-sm" role="group">
                        <button type="button" class="btn btn-outline-secondary active" data-filter="all">All</button>
                        <button type="button" class="btn btn-outline-secondary" data-filter="active">Active</button>
                        <button type="button" class="btn btn-outline-secondary" data-filter="draft">Draft</button>
                        <button type="button" class="btn btn-outline-secondary" data-filter="gpu">GPU</button>
                    </div>
                </div>
            </div>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>Function Name</th>
                            <th>Runtime</th>
                            <th>Resources</th>
                            <th>Endpoint</th>
                            <th>Status</th>
                            <th>Last Deployed</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($functions as $function)
                        <tr data-status="{{ $function->status }}" data-gpu="{{ $function->gpu_enabled ? '1' : '0' }}">
                            <td>
                                <strong>{{ $function->function_name }}</strong>
                                @if($function->description)
                                <br><small class="text-muted">{{ $function->description }}</small>
                                @endif
                            </td>
                            <td><code>{{ $function->runtime }}</code></td>
                            <td>
                                <small>
                                    {{ $function->cpu_count }} CPU<br>
                                    {{ $function->memory_mb }} MB RAM<br>
                                    @if($function->gpu_enabled)
                                    <span class="badge bg-success">{{ $function->gpu_type }} x{{ $function->gpu_count }}</span>
                                    @endif
                                </small>
                            </td>
                            <td>
                                @if($function->endpoint_url)
                                <input type="text" class="form-control form-control-sm"
                                       value="{{ $function->endpoint_url }}" readonly
                                       onclick="this.select()">
                                @else
                                <span class="text-muted">Not deployed</span>
                                @endif
                            </td>
                            <td>
                                @if($function->status === 'active')
                                <span class="badge bg-success">Active</span>
                                @elseif($function->status === 'deploying')
                                <span class="badge bg-info">Deploying</span>
                                @elseif($function->status === 'failed')
                                <span class="badge bg-danger" title="{{ $function->deployment_error }}">Failed</span>
                                @else
                                <span class="badge bg-secondary">{{ ucfirst($function->status) }}</span>
                                @endif
                            </td>
                            <td>
                                @if($function->last_deployed_at)
                                {{ $function->last_deployed_at->diffForHumans() }}
                                @else
                                <span class="text-muted">Never</span>
                                @endif
                            </td>
                            <td>
                                <div class="btn-group btn-group-sm">
                                    @if($function->status === 'active')
                                    <button class="btn btn-success" onclick="invokeFunction({{ $function->id }})"
                                            title="Test/Invoke">
                                        <i class="fas fa-play"></i>
                                    </button>
                                    @elseif($function->status === 'draft' || $function->status === 'failed')
                                    <button class="btn btn-primary" onclick="deployFunction({{ $function->id }})"
                                            title="Deploy">
                                        <i class="fas fa-rocket"></i>
                                    </button>
                                    @endif
                                    <button class="btn btn-info" onclick="editFunction({{ $function->id }})"
                                            title="Edit">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button class="btn btn-danger" onclick="deleteFunction({{ $function->id }})"
                                            title="Delete">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="7" class="text-center text-muted py-5">
                                <i class="fas fa-code fa-4x mb-3 opacity-25"></i>
                                <h5>No functions deployed yet</h5>
                                <p>Deploy your first serverless function with GPU support</p>
                                <a href="{{ route('user.modal.functions.create') }}" class="btn btn-primary">
                                    <i class="fas fa-plus"></i> Deploy Your First Function
                                </a>
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
function deployFunction(id) {
    if (!confirm('Deploy this function to Modal.com?')) return;

    $.ajax({
        url: `/modal/functions/${id}/deploy`,
        method: 'POST',
        data: { _token: '{{ csrf_token() }}' },
        success: function(response) {
            if (response.success) {
                alert('Function deployed successfully!');
                location.reload();
            } else {
                alert('Deployment failed: ' + response.message);
            }
        },
        error: function() {
            alert('Deployment failed. Please try again.');
        }
    });
}

function invokeFunction(id) {
    const input = prompt('Enter JSON input (or leave empty):');
    let inputData = {};

    if (input) {
        try {
            inputData = JSON.parse(input);
        } catch (e) {
            alert('Invalid JSON input');
            return;
        }
    }

    $.ajax({
        url: `/modal/functions/${id}/invoke`,
        method: 'POST',
        data: {
            _token: '{{ csrf_token() }}',
            input: inputData
        },
        success: function(response) {
            if (response.success) {
                alert('Execution successful!\n\nOutput:\n' + JSON.stringify(response.output, null, 2));
            } else {
                alert('Execution failed: ' + response.message);
            }
        }
    });
}

function deleteFunction(id) {
    if (!confirm('Are you sure you want to delete this function? This action cannot be undone.')) return;

    $.ajax({
        url: `/modal/functions/${id}`,
        method: 'DELETE',
        data: { _token: '{{ csrf_token() }}' },
        success: function(response) {
            if (response.success) {
                alert('Function deleted successfully');
                location.reload();
            } else {
                alert('Deletion failed: ' + response.message);
            }
        }
    });
}

// Filter functionality
$('[data-filter]').click(function() {
    const filter = $(this).data('filter');
    $('[data-filter]').removeClass('active');
    $(this).addClass('active');

    if (filter === 'all') {
        $('tbody tr').show();
    } else if (filter === 'gpu') {
        $('tbody tr').hide();
        $('tbody tr[data-gpu="1"]').show();
    } else {
        $('tbody tr').hide();
        $(`tbody tr[data-status="${filter}"]`).show();
    }
});
</script>
@endpush
@endsection
