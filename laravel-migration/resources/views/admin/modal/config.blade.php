@extends('layouts.admin')

@section('title', 'Modal.com Configuration')

@section('content')
<div class="container-fluid">
    <div class="row mb-4">
        <div class="col-12">
            <h1 class="h3 mb-0">Modal.com Serverless Configuration</h1>
            <p class="text-muted">Configure Modal.com API credentials and system-wide settings</p>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">API Configuration</h5>
                </div>
                <div class="card-body">
                    <form id="configForm">
                        @csrf
                        <div class="mb-3">
                            <label for="api_key" class="form-label">API Key</label>
                            <input type="text" class="form-control" id="api_key" name="api_key"
                                   value="{{ $config->api_key }}" required>
                            <small class="form-text text-muted">Get your API key from Modal.com dashboard</small>
                        </div>

                        <div class="mb-3">
                            <label for="api_secret" class="form-label">API Secret</label>
                            <input type="password" class="form-control" id="api_secret" name="api_secret"
                                   value="{{ $config->api_secret }}" required>
                            <small class="form-text text-muted">Keep this secret secure</small>
                        </div>

                        <div class="mb-3">
                            <label for="workspace_name" class="form-label">Workspace Name</label>
                            <input type="text" class="form-control" id="workspace_name" name="workspace_name"
                                   value="{{ $config->workspace_name }}">
                            <small class="form-text text-muted">Optional: Your Modal workspace name</small>
                        </div>

                        <div class="mb-3">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="is_enabled" name="is_enabled"
                                       value="1" {{ $config->is_enabled ? 'checked' : '' }}>
                                <label class="form-check-label" for="is_enabled">
                                    Enable Modal.com Integration
                                </label>
                            </div>
                            <small class="form-text text-muted">Allow users to deploy serverless functions</small>
                        </div>

                        <div class="mb-3">
                            <button type="button" class="btn btn-secondary" id="testConnectionBtn">
                                <i class="fas fa-plug"></i> Test Connection
                            </button>
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save"></i> Save Configuration
                            </button>
                        </div>

                        <div id="alertContainer"></div>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">Configuration Status</h5>
                </div>
                <div class="card-body">
                    <dl class="row mb-0">
                        <dt class="col-sm-6">Status:</dt>
                        <dd class="col-sm-6">
                            @if($config->isActive())
                                <span class="badge bg-success">Active</span>
                            @elseif($config->isConfigured())
                                <span class="badge bg-warning">Configured (Disabled)</span>
                            @else
                                <span class="badge bg-secondary">Not Configured</span>
                            @endif
                        </dd>

                        <dt class="col-sm-6">Workspace ID:</dt>
                        <dd class="col-sm-6">
                            <code>{{ $config->workspace_id ?: 'N/A' }}</code>
                        </dd>

                        <dt class="col-sm-6">Last Updated:</dt>
                        <dd class="col-sm-6">
                            {{ $config->updated_at->diffForHumans() }}
                        </dd>
                    </dl>
                </div>
            </div>

            <div class="card mt-3">
                <div class="card-header">
                    <h5 class="card-title mb-0">Available Runtimes</h5>
                </div>
                <div class="card-body">
                    <ul class="list-unstyled mb-0">
                        @foreach($runtimes as $key => $name)
                            <li><i class="fas fa-check text-success"></i> {{ $name }}</li>
                        @endforeach
                    </ul>
                </div>
            </div>

            <div class="card mt-3">
                <div class="card-header">
                    <h5 class="card-title mb-0">Available GPUs</h5>
                </div>
                <div class="card-body">
                    <ul class="list-unstyled mb-0">
                        @foreach($gpuTypes as $key => $name)
                            <li><i class="fas fa-microchip text-primary"></i> {{ $name }}</li>
                        @endforeach
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
$(document).ready(function() {
    // Test connection
    $('#testConnectionBtn').click(function() {
        $(this).prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Testing...');

        $.ajax({
            url: '{{ route("admin.modal.test-connection") }}',
            method: 'POST',
            data: {
                _token: '{{ csrf_token() }}'
            },
            success: function(response) {
                if (response.success) {
                    showAlert('success', response.message);
                } else {
                    showAlert('danger', response.message);
                }
            },
            error: function(xhr) {
                showAlert('danger', 'Connection test failed');
            },
            complete: function() {
                $('#testConnectionBtn').prop('disabled', false).html('<i class="fas fa-plug"></i> Test Connection');
            }
        });
    });

    // Save configuration
    $('#configForm').submit(function(e) {
        e.preventDefault();

        const formData = {
            _token: '{{ csrf_token() }}',
            api_key: $('#api_key').val(),
            api_secret: $('#api_secret').val(),
            workspace_name: $('#workspace_name').val(),
            is_enabled: $('#is_enabled').is(':checked') ? 1 : 0
        };

        $.ajax({
            url: '{{ route("admin.modal.config.update") }}',
            method: 'POST',
            data: formData,
            success: function(response) {
                if (response.success) {
                    showAlert('success', response.message);
                    setTimeout(() => location.reload(), 1500);
                } else {
                    showAlert('danger', response.message);
                }
            },
            error: function(xhr) {
                if (xhr.responseJSON && xhr.responseJSON.errors) {
                    let errors = xhr.responseJSON.errors;
                    let errorMsg = Object.values(errors).flat().join('<br>');
                    showAlert('danger', errorMsg);
                } else {
                    showAlert('danger', 'Failed to save configuration');
                }
            }
        });
    });

    function showAlert(type, message) {
        const alertHtml = `
            <div class="alert alert-${type} alert-dismissible fade show" role="alert">
                ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        `;
        $('#alertContainer').html(alertHtml);
    }
});
</script>
@endpush
@endsection
