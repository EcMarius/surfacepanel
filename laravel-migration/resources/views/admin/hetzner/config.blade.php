@extends('admin.layout')

@section('title', 'Hetzner Cloud Configuration')

@section('content')
<div class="container-fluid">
    <div class="row mb-4">
        <div class="col-12">
            <h1 class="h3 mb-0">Hetzner Cloud Configuration</h1>
            <p class="text-muted">Configure Hetzner Cloud API integration and default settings</p>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">API Configuration</h5>
                </div>
                <div class="card-body">
                    <form id="hetznerConfigForm">
                        @csrf

                        <div class="mb-3">
                            <label class="form-label">Enable Hetzner Cloud Integration</label>
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" name="is_enabled" id="isEnabled"
                                    {{ $config->is_enabled ? 'checked' : '' }} value="1">
                                <label class="form-check-label" for="isEnabled">
                                    Integration Enabled
                                </label>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="apiToken" class="form-label">API Token</label>
                            <input type="password" class="form-control" id="apiToken" name="api_token"
                                placeholder="Enter Hetzner Cloud API token">
                            <small class="form-text text-muted">
                                Leave blank to keep current token. Get your API token from Hetzner Cloud Console.
                            </small>
                        </div>

                        <hr class="my-4">

                        <h5 class="mb-3">Default Settings</h5>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="defaultDatacenter" class="form-label">Default Datacenter</label>
                                <select class="form-select" id="defaultDatacenter" name="default_datacenter">
                                    <option value="nbg1" {{ $config->default_datacenter === 'nbg1' ? 'selected' : '' }}>Nuremberg (nbg1)</option>
                                    <option value="fsn1" {{ $config->default_datacenter === 'fsn1' ? 'selected' : '' }}>Falkenstein (fsn1)</option>
                                    <option value="hel1" {{ $config->default_datacenter === 'hel1' ? 'selected' : '' }}>Helsinki (hel1)</option>
                                    <option value="ash" {{ $config->default_datacenter === 'ash' ? 'selected' : '' }}>Ashburn (ash)</option>
                                </select>
                            </div>

                            <div class="col-md-6 mb-3">
                                <label for="defaultServerType" class="form-label">Default Server Type</label>
                                <select class="form-select" id="defaultServerType" name="default_server_type">
                                    <option value="cx11" {{ $config->default_server_type === 'cx11' ? 'selected' : '' }}>CX11 (1 vCPU, 2GB RAM)</option>
                                    <option value="cx21" {{ $config->default_server_type === 'cx21' ? 'selected' : '' }}>CX21 (2 vCPU, 4GB RAM)</option>
                                    <option value="cx31" {{ $config->default_server_type === 'cx31' ? 'selected' : '' }}>CX31 (2 vCPU, 8GB RAM)</option>
                                    <option value="cx41" {{ $config->default_server_type === 'cx41' ? 'selected' : '' }}>CX41 (4 vCPU, 16GB RAM)</option>
                                    <option value="cx51" {{ $config->default_server_type === 'cx51' ? 'selected' : '' }}>CX51 (8 vCPU, 32GB RAM)</option>
                                </select>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="defaultImage" class="form-label">Default OS Image</label>
                            <select class="form-select" id="defaultImage" name="default_image">
                                <option value="ubuntu-22.04" {{ $config->default_image === 'ubuntu-22.04' ? 'selected' : '' }}>Ubuntu 22.04</option>
                                <option value="ubuntu-20.04" {{ $config->default_image === 'ubuntu-20.04' ? 'selected' : '' }}>Ubuntu 20.04</option>
                                <option value="debian-11" {{ $config->default_image === 'debian-11' ? 'selected' : '' }}>Debian 11</option>
                                <option value="centos-stream-9" {{ $config->default_image === 'centos-stream-9' ? 'selected' : '' }}>CentOS Stream 9</option>
                                <option value="rocky-9" {{ $config->default_image === 'rocky-9' ? 'selected' : '' }}>Rocky Linux 9</option>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Automatic Backups</label>
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" name="auto_backups" id="autoBackups"
                                    {{ $config->auto_backups ? 'checked' : '' }} value="1">
                                <label class="form-check-label" for="autoBackups">
                                    Enable automatic backups for new servers (20% additional cost)
                                </label>
                            </div>
                        </div>

                        <hr class="my-4">

                        <h5 class="mb-3">Budget Alerts</h5>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="monthlyBudgetAlert" class="form-label">Monthly Budget Alert (EUR)</label>
                                <input type="number" class="form-control" id="monthlyBudgetAlert" name="monthly_budget_alert"
                                    value="{{ $config->monthly_budget_alert }}" step="0.01" min="0">
                                <small class="form-text text-muted">
                                    Receive alert when monthly costs exceed this amount
                                </small>
                            </div>

                            <div class="col-md-6 mb-3">
                                <label for="notificationEmail" class="form-label">Notification Email</label>
                                <input type="email" class="form-control" id="notificationEmail" name="notification_email"
                                    value="{{ $config->notification_email }}">
                                <small class="form-text text-muted">
                                    Email address for budget alerts
                                </small>
                            </div>
                        </div>

                        <div class="mt-4">
                            <button type="submit" class="btn btn-primary">Save Configuration</button>
                            <a href="{{ route('admin.hetzner.index') }}" class="btn btn-secondary">Cancel</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">Integration Status</h5>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="text-muted">Status:</label>
                        <div class="mt-1">
                            @if($config->isEnabled())
                                <span class="badge bg-success">Active</span>
                            @else
                                <span class="badge bg-warning">Inactive</span>
                            @endif
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="text-muted">API Token:</label>
                        <div class="mt-1">
                            @if($config->api_token)
                                <span class="badge bg-success">Configured</span>
                            @else
                                <span class="badge bg-danger">Not Set</span>
                            @endif
                        </div>
                    </div>

                    <hr>

                    <h6>Getting Started:</h6>
                    <ol class="small">
                        <li>Create a Hetzner Cloud account</li>
                        <li>Generate an API token in Cloud Console</li>
                        <li>Enter the API token above</li>
                        <li>Enable the integration</li>
                        <li>Configure default settings</li>
                    </ol>
                </div>
            </div>

            <div class="card mt-3">
                <div class="card-header">
                    <h5 class="card-title mb-0">Documentation</h5>
                </div>
                <div class="card-body">
                    <a href="https://docs.hetzner.cloud/" target="_blank" class="btn btn-sm btn-outline-primary w-100 mb-2">
                        Hetzner Cloud Documentation
                    </a>
                    <a href="https://console.hetzner.cloud/" target="_blank" class="btn btn-sm btn-outline-primary w-100">
                        Hetzner Cloud Console
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.getElementById('hetznerConfigForm').addEventListener('submit', async function(e) {
    e.preventDefault();

    const formData = new FormData(this);
    const data = Object.fromEntries(formData.entries());

    // Handle checkboxes
    data.is_enabled = document.getElementById('isEnabled').checked ? 1 : 0;
    data.auto_backups = document.getElementById('autoBackups').checked ? 1 : 0;

    try {
        const response = await fetch('{{ route("admin.hetzner.config.update") }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}'
            },
            body: JSON.stringify(data)
        });

        const result = await response.json();

        if (result.success) {
            alert('Configuration updated successfully');
            window.location.reload();
        } else {
            alert('Error: ' + (result.message || 'Failed to update configuration'));
        }
    } catch (error) {
        alert('Error: ' + error.message);
    }
});
</script>
@endsection
