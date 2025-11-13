@extends('admin.layout')

@section('title', 'Provision Hetzner Server')

@section('content')
<div class="container-fluid">
    <div class="row mb-4">
        <div class="col-12">
            <h1 class="h3 mb-0">Provision Hetzner Cloud Server</h1>
            <p class="text-muted">Create a new virtual machine on Hetzner Cloud</p>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-8">
            <div class="card">
                <div class="card-body">
                    <form id="provisionForm">
                        @csrf

                        <h5 class="mb-3">Server Details</h5>

                        <div class="mb-3">
                            <label for="serverName" class="form-label">Server Name *</label>
                            <input type="text" class="form-control" id="serverName" name="name" required>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="serverType" class="form-label">Server Type *</label>
                                <select class="form-select" id="serverType" name="server_type" required>
                                    @if($serverTypes)
                                        @foreach($serverTypes as $type)
                                            <option value="{{ $type['name'] }}">
                                                {{ strtoupper($type['name']) }} - {{ $type['cores'] }} vCPU, {{ $type['memory'] }}GB RAM, {{ $type['disk'] }}GB SSD
                                                (€{{ number_format($type['prices'][0]['price_monthly']['gross'] ?? 0, 2) }}/mo)
                                            </option>
                                        @endforeach
                                    @endif
                                </select>
                            </div>

                            <div class="col-md-6 mb-3">
                                <label for="location" class="form-label">Location *</label>
                                <select class="form-select" id="location" name="location" required>
                                    @if($locations)
                                        @foreach($locations as $location)
                                            <option value="{{ $location['name'] }}">
                                                {{ $location['city'] }} ({{ $location['name'] }})
                                            </option>
                                        @endforeach
                                    @endif
                                </select>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="image" class="form-label">Operating System *</label>
                            <select class="form-select" id="image" name="image" required>
                                @if($images)
                                    @foreach($images as $image)
                                        <option value="{{ $image['name'] }}">
                                            {{ $image['description'] }}
                                        </option>
                                    @endforeach
                                @endif
                            </select>
                        </div>

                        <hr class="my-4">

                        <h5 class="mb-3">Assignment (Optional)</h5>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="userId" class="form-label">Assign to User</label>
                                <select class="form-select" id="userId" name="user_id">
                                    <option value="">Select user...</option>
                                    <!-- Users will be loaded dynamically -->
                                </select>
                            </div>

                            <div class="col-md-6 mb-3">
                                <label for="accountId" class="form-label">Assign to Account</label>
                                <select class="form-select" id="accountId" name="account_id">
                                    <option value="">Select account...</option>
                                    <!-- Accounts will be loaded dynamically -->
                                </select>
                            </div>
                        </div>

                        <hr class="my-4">

                        <h5 class="mb-3">SSH Keys (Optional)</h5>

                        <div class="mb-3">
                            @if($sshKeys && count($sshKeys) > 0)
                                @foreach($sshKeys as $key)
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="ssh_keys[]" value="{{ $key['id'] }}" id="sshKey{{ $key['id'] }}">
                                        <label class="form-check-label" for="sshKey{{ $key['id'] }}">
                                            {{ $key['name'] }}
                                            <small class="text-muted">({{ substr($key['fingerprint'], 0, 20) }}...)</small>
                                        </label>
                                    </div>
                                @endforeach
                            @else
                                <p class="text-muted">No SSH keys found in your Hetzner account.</p>
                            @endif
                        </div>

                        <hr class="my-4">

                        <h5 class="mb-3">Options</h5>

                        <div class="form-check mb-3">
                            <input class="form-check-input" type="checkbox" name="start_after_create" id="startAfterCreate" checked value="1">
                            <label class="form-check-label" for="startAfterCreate">
                                Start server after creation
                            </label>
                        </div>

                        <div class="mt-4">
                            <button type="submit" class="btn btn-primary btn-lg">Provision Server</button>
                            <a href="{{ route('admin.hetzner.servers') }}" class="btn btn-secondary">Cancel</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">Provisioning Info</h5>
                </div>
                <div class="card-body">
                    <h6>What happens next?</h6>
                    <ol class="small">
                        <li>Server is created on Hetzner Cloud</li>
                        <li>OS is installed automatically</li>
                        <li>Server starts (if enabled)</li>
                        <li>IP addresses are assigned</li>
                        <li>SSH access is configured</li>
                        <li>Root password is saved securely</li>
                    </ol>

                    <hr>

                    <h6>Typical Provisioning Time:</h6>
                    <p class="small mb-0">Usually takes 30-60 seconds for the server to be ready.</p>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.getElementById('provisionForm').addEventListener('submit', async function(e) {
    e.preventDefault();

    const formData = new FormData(this);
    const data = Object.fromEntries(formData.entries());

    // Handle SSH keys array
    const sshKeys = [];
    document.querySelectorAll('input[name="ssh_keys[]"]:checked').forEach(checkbox => {
        sshKeys.push(parseInt(checkbox.value));
    });
    data.ssh_keys = sshKeys;

    // Handle checkboxes
    data.start_after_create = document.getElementById('startAfterCreate').checked ? 1 : 0;

    if (!confirm('Provision this server? You will be charged starting immediately.')) {
        return;
    }

    try {
        const button = this.querySelector('button[type="submit"]');
        button.disabled = true;
        button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Provisioning...';

        const response = await fetch('{{ route("admin.hetzner.servers.provision") }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}'
            },
            body: JSON.stringify(data)
        });

        const result = await response.json();

        if (result.success) {
            alert('Server provisioned successfully!');
            window.location = '{{ route("admin.hetzner.servers") }}';
        } else {
            alert('Error: ' + (result.message || 'Failed to provision server'));
            button.disabled = false;
            button.innerHTML = 'Provision Server';
        }
    } catch (error) {
        alert('Error: ' + error.message);
        button.disabled = false;
        button.innerHTML = 'Provision Server';
    }
});
</script>
@endsection
