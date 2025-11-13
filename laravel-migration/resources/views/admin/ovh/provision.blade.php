@extends('layouts.admin')

@section('title', 'Provision OVH Server')

@section('content')
<div class="container-fluid">
    <div class="row mb-4">
        <div class="col-md-12">
            <h1 class="h3 mb-3">Provision OVH Server</h1>
            <a href="{{ route('admin.ovh.servers') }}" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Back to Servers
            </a>
        </div>
    </div>

    <div class="row">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header">
                    <h6 class="m-0 font-weight-bold text-primary">Server Provisioning</h6>
                </div>
                <div class="card-body">
                    <form id="provisionForm">
                        @csrf

                        <div class="form-group">
                            <label for="service_name">Service Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="service_name" name="service_name" required
                                   placeholder="e.g., ns12345.ip-1-2-3.eu">
                            <small class="form-text text-muted">The OVH service identifier</small>
                        </div>

                        <div class="form-group">
                            <label for="server_type">Server Type <span class="text-danger">*</span></label>
                            <select class="form-control" id="server_type" name="server_type" required>
                                <option value="dedicated">Dedicated Server</option>
                                <option value="vps">VPS</option>
                                <option value="public_cloud">Public Cloud</option>
                                <option value="private_cloud">Private Cloud</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="display_name">Display Name</label>
                            <input type="text" class="form-control" id="display_name" name="display_name"
                                   placeholder="Friendly name for this server">
                        </div>

                        <div class="form-group">
                            <label for="account_id">Assign to Account</label>
                            <select class="form-control" id="account_id" name="account_id">
                                <option value="">-- Unassigned --</option>
                                @foreach($accounts as $account)
                                <option value="{{ $account->id }}">{{ $account->username }} ({{ $account->domain }})</option>
                                @endforeach
                            </select>
                            <small class="form-text text-muted">Optionally assign this server to a user account</small>
                        </div>

                        <div class="form-group">
                            <label for="ip_address">IP Address</label>
                            <input type="text" class="form-control" id="ip_address" name="ip_address"
                                   placeholder="192.168.1.1">
                        </div>

                        <div class="form-group">
                            <label for="datacenter">Datacenter</label>
                            <select class="form-control" id="datacenter" name="datacenter">
                                <option value="">-- Select Datacenter --</option>
                                <option value="rbx">Roubaix (France)</option>
                                <option value="sbg">Strasbourg (France)</option>
                                <option value="gra">Gravelines (France)</option>
                                <option value="bhs">Beauharnois (Canada)</option>
                                <option value="waw">Warsaw (Poland)</option>
                                <option value="de">Frankfurt (Germany)</option>
                                <option value="lon">London (UK)</option>
                                <option value="sgp">Singapore</option>
                                <option value="syd">Sydney (Australia)</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="notes">Notes</label>
                            <textarea class="form-control" id="notes" name="notes" rows="3"
                                      placeholder="Additional notes about this server"></textarea>
                        </div>

                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-plus"></i> Provision Server
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card">
                <div class="card-header">
                    <h6 class="m-0 font-weight-bold text-primary">Provisioning Guide</h6>
                </div>
                <div class="card-body">
                    <h6>Manual Provisioning</h6>
                    <p class="small">
                        Use this form to manually add an OVH server to VirPanel. The server should already exist in your OVH account.
                    </p>

                    <h6 class="mt-3">Automatic Sync</h6>
                    <p class="small">
                        Alternatively, you can use the "Sync from OVH" button on the servers page to automatically import all servers from your OVH account.
                    </p>

                    <h6 class="mt-3">Service Name Format</h6>
                    <ul class="small">
                        <li><strong>Dedicated:</strong> nsXXXXXX.ip-X-X-X.eu</li>
                        <li><strong>VPS:</strong> vpsXXXXXX.ovh.net</li>
                        <li><strong>Cloud:</strong> Instance ID from OVH</li>
                    </ul>

                    <div class="alert alert-info mt-3">
                        <i class="fas fa-info-circle"></i>
                        <small>After provisioning, you can manage the server from the servers list page.</small>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
$(document).ready(function() {
    $('#provisionForm').submit(function(e) {
        e.preventDefault();

        const btn = $(this).find('button[type="submit"]');
        btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Provisioning...');

        $.post('{{ route("admin.ovh.provision-server") }}', $(this).serialize())
        .done(function(response) {
            if (response.success) {
                alert('Success: Server provisioned successfully!');
                window.location.href = '{{ route("admin.ovh.servers") }}';
            } else {
                alert('Error: ' + response.message);
            }
        })
        .fail(function(xhr) {
            let errors = xhr.responseJSON?.errors;
            if (errors) {
                let errorMsg = 'Validation errors:\n';
                Object.keys(errors).forEach(key => {
                    errorMsg += '- ' + errors[key][0] + '\n';
                });
                alert(errorMsg);
            } else {
                alert('Error: ' + (xhr.responseJSON?.message || 'Failed to provision server'));
            }
        })
        .always(function() {
            btn.prop('disabled', false).html('<i class="fas fa-plus"></i> Provision Server');
        });
    });
});
</script>
@endpush
@endsection
