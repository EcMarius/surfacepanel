@extends('layouts.admin')

@section('title', 'OVH API Configuration')

@section('content')
<div class="container-fluid">
    <div class="row mb-4">
        <div class="col-md-12">
            <h1 class="h3 mb-3">OVH API Configuration</h1>
        </div>
    </div>

    <div class="row">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header">
                    <h6 class="m-0 font-weight-bold text-primary">API Credentials</h6>
                </div>
                <div class="card-body">
                    <form id="ovhConfigForm">
                        @csrf

                        <div class="form-group">
                            <label for="endpoint">API Endpoint</label>
                            <select class="form-control" id="endpoint" name="endpoint" required>
                                <option value="ovh-eu" {{ $config->endpoint === 'ovh-eu' ? 'selected' : '' }}>OVH Europe</option>
                                <option value="ovh-ca" {{ $config->endpoint === 'ovh-ca' ? 'selected' : '' }}>OVH Canada</option>
                                <option value="ovh-us" {{ $config->endpoint === 'ovh-us' ? 'selected' : '' }}>OVH US</option>
                                <option value="kimsufi-eu" {{ $config->endpoint === 'kimsufi-eu' ? 'selected' : '' }}>Kimsufi Europe</option>
                                <option value="kimsufi-ca" {{ $config->endpoint === 'kimsufi-ca' ? 'selected' : '' }}>Kimsufi Canada</option>
                                <option value="soyoustart-eu" {{ $config->endpoint === 'soyoustart-eu' ? 'selected' : '' }}>So you Start Europe</option>
                                <option value="soyoustart-ca" {{ $config->endpoint === 'soyoustart-ca' ? 'selected' : '' }}>So you Start Canada</option>
                            </select>
                            <small class="form-text text-muted">Select your OVH API endpoint</small>
                        </div>

                        <div class="form-group">
                            <label for="application_key">Application Key</label>
                            <input type="text" class="form-control" id="application_key" name="application_key"
                                   value="{{ $config->application_key }}" required>
                            <small class="form-text text-muted">
                                Get your credentials from <a href="https://eu.api.ovh.com/createApp/" target="_blank">OVH API</a>
                            </small>
                        </div>

                        <div class="form-group">
                            <label for="application_secret">Application Secret</label>
                            <input type="password" class="form-control" id="application_secret" name="application_secret"
                                   value="{{ $config->application_secret }}" required>
                        </div>

                        <div class="form-group">
                            <label for="consumer_key">Consumer Key</label>
                            <div class="input-group">
                                <input type="text" class="form-control" id="consumer_key" name="consumer_key"
                                       value="{{ $config->consumer_key }}">
                                <div class="input-group-append">
                                    <button type="button" class="btn btn-secondary" id="requestConsumerKeyBtn">
                                        <i class="fas fa-key"></i> Request Key
                                    </button>
                                </div>
                            </div>
                            <small class="form-text text-muted">Click "Request Key" to generate a consumer key</small>
                        </div>

                        <div class="form-group">
                            <label for="webhook_url">Webhook URL (Optional)</label>
                            <input type="url" class="form-control" id="webhook_url" name="webhook_url"
                                   value="{{ $config->webhook_url }}">
                            <small class="form-text text-muted">URL for OVH webhook notifications</small>
                        </div>

                        <div class="form-group">
                            <div class="custom-control custom-switch">
                                <input type="checkbox" class="custom-control-input" id="is_active" name="is_active"
                                       {{ $config->is_active ? 'checked' : '' }}>
                                <label class="custom-control-label" for="is_active">Enable OVH Integration</label>
                            </div>
                        </div>

                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> Save Configuration
                        </button>
                        <button type="button" class="btn btn-info" id="testConnectionBtn">
                            <i class="fas fa-plug"></i> Test Connection
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card">
                <div class="card-header">
                    <h6 class="m-0 font-weight-bold text-primary">Configuration Status</h6>
                </div>
                <div class="card-body">
                    <ul class="list-group">
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            Configured
                            <span class="badge badge-{{ $config->isConfigured() ? 'success' : 'danger' }}">
                                {{ $config->isConfigured() ? 'Yes' : 'No' }}
                            </span>
                        </li>
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            Active
                            <span class="badge badge-{{ $config->is_active ? 'success' : 'warning' }}">
                                {{ $config->is_active ? 'Yes' : 'No' }}
                            </span>
                        </li>
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            Last Sync
                            <span class="badge badge-info">
                                {{ $config->last_sync_at ? $config->last_sync_at->diffForHumans() : 'Never' }}
                            </span>
                        </li>
                    </ul>
                </div>
            </div>

            <div class="card mt-3">
                <div class="card-header">
                    <h6 class="m-0 font-weight-bold text-primary">Setup Instructions</h6>
                </div>
                <div class="card-body">
                    <ol class="small">
                        <li>Create an OVH application at <a href="https://eu.api.ovh.com/createApp/" target="_blank">OVH API</a></li>
                        <li>Enter your Application Key and Secret above</li>
                        <li>Click "Request Key" to generate a Consumer Key</li>
                        <li>Authorize the application in the validation URL</li>
                        <li>Enable the integration and test the connection</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
$(document).ready(function() {
    // Save configuration
    $('#ovhConfigForm').submit(function(e) {
        e.preventDefault();

        const btn = $(this).find('button[type="submit"]');
        btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Saving...');

        $.post('{{ route("admin.ovh.update-config") }}', $(this).serialize())
        .done(function(response) {
            if (response.success) {
                showNotification('success', response.message);
            } else {
                showNotification('error', response.message);
            }
        })
        .fail(function(xhr) {
            showNotification('error', xhr.responseJSON?.message || 'Failed to save configuration');
        })
        .always(function() {
            btn.prop('disabled', false).html('<i class="fas fa-save"></i> Save Configuration');
        });
    });

    // Request consumer key
    $('#requestConsumerKeyBtn').click(function() {
        const btn = $(this);
        btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Requesting...');

        $.post('{{ route("admin.ovh.request-consumer-key") }}', {
            _token: '{{ csrf_token() }}'
        })
        .done(function(response) {
            if (response.success) {
                $('#consumer_key').val(response.consumer_key);
                showNotification('success', 'Consumer key generated. Please visit the validation URL to authorize.');

                // Open validation URL in new window
                window.open(response.validation_url, '_blank');
            } else {
                showNotification('error', response.message);
            }
        })
        .fail(function(xhr) {
            showNotification('error', xhr.responseJSON?.message || 'Failed to request consumer key');
        })
        .always(function() {
            btn.prop('disabled', false).html('<i class="fas fa-key"></i> Request Key');
        });
    });

    // Test connection
    $('#testConnectionBtn').click(function() {
        const btn = $(this);
        btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Testing...');

        $.post('{{ route("admin.ovh.test-connection") }}', {
            _token: '{{ csrf_token() }}'
        })
        .done(function(response) {
            if (response.success) {
                showNotification('success', response.message);
            } else {
                showNotification('error', response.message);
            }
        })
        .fail(function(xhr) {
            showNotification('error', xhr.responseJSON?.message || 'Connection test failed');
        })
        .always(function() {
            btn.prop('disabled', false).html('<i class="fas fa-plug"></i> Test Connection');
        });
    });
});

function showNotification(type, message) {
    // Implement your notification system here
    alert(message);
}
</script>
@endpush
@endsection
