@extends('layouts.user')

@section('title', 'Deploy Serverless Function')

@section('content')
<div class="container-fluid">
    <div class="row mb-4">
        <div class="col-12">
            <h1 class="h3 mb-0">Deploy New Serverless Function</h1>
            <p class="text-muted">Deploy Python serverless functions with optional GPU acceleration</p>
        </div>
    </div>

    <form id="deployForm">
        @csrf
        <div class="row">
            <div class="col-lg-8">
                <div class="card mb-3">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Function Configuration</h5>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label for="function_name" class="form-label">Function Name *</label>
                            <input type="text" class="form-control" id="function_name" name="function_name" required>
                            <small class="form-text text-muted">Unique name for your function (lowercase, no spaces)</small>
                        </div>

                        <div class="mb-3">
                            <label for="description" class="form-label">Description</label>
                            <textarea class="form-control" id="description" name="description" rows="2"></textarea>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="runtime" class="form-label">Python Runtime *</label>
                                <select class="form-select" id="runtime" name="runtime" required>
                                    @foreach($runtimes as $key => $name)
                                    <option value="{{ $key }}" {{ $key === 'python3.11' ? 'selected' : '' }}>
                                        {{ $name }}
                                    </option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="col-md-6 mb-3">
                                <label for="entrypoint" class="form-label">Entrypoint Function *</label>
                                <input type="text" class="form-control" id="entrypoint" name="entrypoint"
                                       value="main" required>
                                <small class="form-text text-muted">The function name to execute</small>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="code" class="form-label">Function Code *</label>
                            <textarea class="form-control font-monospace" id="code" name="code" rows="15" required>import modal

stub = modal.Stub("my-function")

@stub.function()
def main(input_data: dict):
    """
    Your serverless function code here.
    Returns: dict
    """
    return {
        "status": "success",
        "message": "Hello from Modal!",
        "input": input_data
    }</textarea>
                        </div>

                        <div class="mb-3">
                            <label for="requirements" class="form-label">Python Dependencies</label>
                            <textarea class="form-control font-monospace" id="requirements" name="requirements" rows="4"
                                      placeholder="numpy&#10;pandas&#10;torch"></textarea>
                            <small class="form-text text-muted">One package per line (pip package names)</small>
                        </div>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Environment & Secrets</h5>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label class="form-label">Environment Variables</label>
                            <div id="envVars">
                                <div class="input-group mb-2">
                                    <input type="text" class="form-control" placeholder="KEY" data-env-key>
                                    <input type="text" class="form-control" placeholder="VALUE" data-env-value>
                                    <button class="btn btn-outline-danger" type="button" onclick="$(this).parent().remove()">
                                        <i class="fas fa-times"></i>
                                    </button>
                                </div>
                            </div>
                            <button type="button" class="btn btn-sm btn-outline-secondary" onclick="addEnvVar()">
                                <i class="fas fa-plus"></i> Add Variable
                            </button>
                        </div>

                        <div class="mb-0">
                            <label class="form-label">Secrets (Encrypted)</label>
                            <div id="secrets">
                                <div class="input-group mb-2">
                                    <input type="text" class="form-control" placeholder="KEY" data-secret-key>
                                    <input type="password" class="form-control" placeholder="VALUE" data-secret-value>
                                    <button class="btn btn-outline-danger" type="button" onclick="$(this).parent().remove()">
                                        <i class="fas fa-times"></i>
                                    </button>
                                </div>
                            </div>
                            <button type="button" class="btn btn-sm btn-outline-secondary" onclick="addSecret()">
                                <i class="fas fa-plus"></i> Add Secret
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                <div class="card mb-3">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Resource Configuration</h5>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label for="cpu_count" class="form-label">CPU Cores</label>
                            <input type="number" class="form-control" id="cpu_count" name="cpu_count"
                                   min="1" max="32" value="1" required>
                        </div>

                        <div class="mb-3">
                            <label for="memory_mb" class="form-label">Memory (MB)</label>
                            <input type="number" class="form-control" id="memory_mb" name="memory_mb"
                                   min="128" max="65536" value="1024" step="128" required>
                        </div>

                        <div class="mb-3">
                            <label for="timeout" class="form-label">Timeout (seconds)</label>
                            <input type="number" class="form-control" id="timeout" name="timeout"
                                   min="1" max="3600" value="300" required>
                        </div>

                        <hr>

                        <div class="mb-3">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="gpu_enabled" name="gpu_enabled">
                                <label class="form-check-label" for="gpu_enabled">
                                    <strong>Enable GPU Acceleration</strong>
                                </label>
                            </div>
                        </div>

                        <div id="gpuConfig" style="display: none;">
                            <div class="mb-3">
                                <label for="gpu_type" class="form-label">GPU Type</label>
                                <select class="form-select" id="gpu_type" name="gpu_type">
                                    @foreach($gpuTypes as $key => $name)
                                    <option value="{{ $key }}">{{ $name }}</option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="mb-3">
                                <label for="gpu_count" class="form-label">GPU Count</label>
                                <input type="number" class="form-control" id="gpu_count" name="gpu_count"
                                       min="1" max="8" value="1">
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card">
                    <div class="card-body">
                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-primary btn-lg">
                                <i class="fas fa-rocket"></i> Deploy Function
                            </button>
                            <a href="{{ route('user.modal.functions') }}" class="btn btn-outline-secondary">
                                Cancel
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>

@push('scripts')
<script>
$(document).ready(function() {
    // Toggle GPU configuration
    $('#gpu_enabled').change(function() {
        $('#gpuConfig').toggle(this.checked);
    });

    // Deploy form submission
    $('#deployForm').submit(function(e) {
        e.preventDefault();

        // Collect environment variables
        const envVars = {};
        $('[data-env-key]').each(function() {
            const key = $(this).val();
            const value = $(this).closest('.input-group').find('[data-env-value]').val();
            if (key && value) {
                envVars[key] = value;
            }
        });

        // Collect secrets
        const secrets = {};
        $('[data-secret-key]').each(function() {
            const key = $(this).val();
            const value = $(this).closest('.input-group').find('[data-secret-value]').val();
            if (key && value) {
                secrets[key] = value;
            }
        });

        // Parse requirements
        const requirementsText = $('#requirements').val();
        const requirements = requirementsText ? requirementsText.split('\n').filter(r => r.trim()) : [];

        const formData = {
            _token: '{{ csrf_token() }}',
            function_name: $('#function_name').val(),
            description: $('#description').val(),
            runtime: $('#runtime').val(),
            code: $('#code').val(),
            entrypoint: $('#entrypoint').val(),
            requirements: requirements,
            environment_variables: envVars,
            secrets: secrets,
            gpu_enabled: $('#gpu_enabled').is(':checked'),
            gpu_type: $('#gpu_type').val(),
            gpu_count: $('#gpu_count').val(),
            cpu_count: $('#cpu_count').val(),
            memory_mb: $('#memory_mb').val(),
            timeout: $('#timeout').val()
        };

        $.ajax({
            url: '{{ route("user.modal.functions.store") }}',
            method: 'POST',
            data: formData,
            success: function(response) {
                if (response.success) {
                    alert('Function created successfully! Redirecting...');
                    window.location.href = '{{ route("user.modal.functions") }}';
                } else {
                    alert('Failed to create function: ' + response.message);
                }
            },
            error: function(xhr) {
                if (xhr.responseJSON && xhr.responseJSON.errors) {
                    let errors = Object.values(xhr.responseJSON.errors).flat();
                    alert('Validation errors:\n' + errors.join('\n'));
                } else {
                    alert('Failed to create function. Please try again.');
                }
            }
        });
    });
});

function addEnvVar() {
    $('#envVars').append(`
        <div class="input-group mb-2">
            <input type="text" class="form-control" placeholder="KEY" data-env-key>
            <input type="text" class="form-control" placeholder="VALUE" data-env-value>
            <button class="btn btn-outline-danger" type="button" onclick="$(this).parent().remove()">
                <i class="fas fa-times"></i>
            </button>
        </div>
    `);
}

function addSecret() {
    $('#secrets').append(`
        <div class="input-group mb-2">
            <input type="text" class="form-control" placeholder="KEY" data-secret-key>
            <input type="password" class="form-control" placeholder="VALUE" data-secret-value>
            <button class="btn btn-outline-danger" type="button" onclick="$(this).parent().remove()">
                <i class="fas fa-times"></i>
            </button>
        </div>
    `);
}
</script>
@endpush
@endsection
