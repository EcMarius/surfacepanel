@extends('admin.layout')

@section('title', 'Hetzner Cloud Volumes')

@section('content')
<div class="container-fluid">
    <div class="row mb-4">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h1 class="h3 mb-0">Hetzner Cloud Volumes</h1>
                    <p class="text-muted">Manage block storage volumes</p>
                </div>
                <div>
                    <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#createVolumeModal">
                        <i class="fas fa-plus"></i> Create Volume
                    </button>
                    <button class="btn btn-primary" onclick="syncVolumes()">
                        <i class="fas fa-sync"></i> Sync from Hetzner
                    </button>
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Size</th>
                            <th>Location</th>
                            <th>Status</th>
                            <th>Attached To</th>
                            <th>Mount Point</th>
                            <th>Monthly Cost</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($volumes as $volume)
                        <tr>
                            <td>
                                <strong>{{ $volume->name }}</strong>
                                <br>
                                <small class="text-muted">ID: {{ $volume->hetzner_volume_id }}</small>
                            </td>
                            <td>{{ $volume->size }} GB</td>
                            <td>{{ $volume->location }}</td>
                            <td>
                                @if($volume->status === 'attached')
                                    <span class="badge bg-success">Attached</span>
                                @elseif($volume->status === 'available')
                                    <span class="badge bg-info">Available</span>
                                @else
                                    <span class="badge bg-secondary">{{ ucfirst($volume->status) }}</span>
                                @endif
                            </td>
                            <td>
                                @if($volume->server)
                                    {{ $volume->server->name }}
                                @else
                                    <span class="text-muted">Not attached</span>
                                @endif
                            </td>
                            <td>
                                {{ $volume->mount_point ?? '-' }}
                            </td>
                            <td>€{{ number_format($volume->monthly_price, 2) }}</td>
                            <td>
                                <div class="btn-group btn-group-sm">
                                    @if($volume->canAttach())
                                        <button class="btn btn-primary" onclick="showAttachModal({{ $volume->id }})" title="Attach">
                                            <i class="fas fa-link"></i>
                                        </button>
                                    @endif
                                    @if($volume->canDetach())
                                        <button class="btn btn-warning" onclick="detachVolume({{ $volume->id }})" title="Detach">
                                            <i class="fas fa-unlink"></i>
                                        </button>
                                    @endif
                                    <button class="btn btn-danger" onclick="deleteVolume({{ $volume->id }})" title="Delete">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="8" class="text-center text-muted py-4">
                                No volumes found. Create your first volume to get started.
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if($volumes->hasPages())
                <div class="mt-3">
                    {{ $volumes->links() }}
                </div>
            @endif
        </div>
    </div>
</div>

<!-- Create Volume Modal -->
<div class="modal fade" id="createVolumeModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Create Volume</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="createVolumeForm">
                    @csrf
                    <div class="mb-3">
                        <label for="volumeName" class="form-label">Name *</label>
                        <input type="text" class="form-control" id="volumeName" name="name" required>
                    </div>

                    <div class="mb-3">
                        <label for="volumeSize" class="form-label">Size (GB) *</label>
                        <input type="number" class="form-control" id="volumeSize" name="size" min="10" max="10000" required>
                        <small class="text-muted">Minimum 10 GB, maximum 10,000 GB</small>
                    </div>

                    <div class="mb-3">
                        <label for="volumeLocation" class="form-label">Location *</label>
                        <select class="form-select" id="volumeLocation" name="location" required>
                            <option value="nbg1">Nuremberg (nbg1)</option>
                            <option value="fsn1">Falkenstein (fsn1)</option>
                            <option value="hel1">Helsinki (hel1)</option>
                            <option value="ash">Ashburn (ash)</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label for="volumeFormat" class="form-label">Format</label>
                        <select class="form-select" id="volumeFormat" name="format">
                            <option value="">No formatting</option>
                            <option value="ext4">ext4</option>
                            <option value="xfs">xfs</option>
                        </select>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="createVolume()">Create Volume</button>
            </div>
        </div>
    </div>
</div>

<script>
async function syncVolumes() {
    if (!confirm('Sync all volumes from Hetzner Cloud?')) return;

    try {
        const response = await fetch('{{ route("admin.hetzner.volumes.sync") }}', {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': '{{ csrf_token() }}'
            }
        });

        const result = await response.json();

        if (result.success) {
            alert(result.message);
            window.location.reload();
        } else {
            alert('Error: ' + result.message);
        }
    } catch (error) {
        alert('Error: ' + error.message);
    }
}

async function createVolume() {
    const form = document.getElementById('createVolumeForm');
    const formData = new FormData(form);
    const data = Object.fromEntries(formData.entries());

    try {
        const response = await fetch('{{ route("admin.hetzner.volumes.create") }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}'
            },
            body: JSON.stringify(data)
        });

        const result = await response.json();

        if (result.success) {
            alert('Volume created successfully!');
            window.location.reload();
        } else {
            alert('Error: ' + result.message);
        }
    } catch (error) {
        alert('Error: ' + error.message);
    }
}

async function detachVolume(id) {
    if (!confirm('Detach this volume? Make sure it is unmounted first!')) return;

    try {
        const response = await fetch(`/admin/hetzner/volumes/${id}/detach`, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': '{{ csrf_token() }}'
            }
        });

        const result = await response.json();

        if (result.success) {
            alert(result.message);
            window.location.reload();
        } else {
            alert('Error: ' + result.message);
        }
    } catch (error) {
        alert('Error: ' + error.message);
    }
}

async function deleteVolume(id) {
    if (!confirm('Are you sure you want to delete this volume? All data will be lost!')) return;

    try {
        const response = await fetch(`/admin/hetzner/volumes/${id}`, {
            method: 'DELETE',
            headers: {
                'X-CSRF-TOKEN': '{{ csrf_token() }}'
            }
        });

        const result = await response.json();

        if (result.success) {
            alert(result.message);
            window.location.reload();
        } else {
            alert('Error: ' + result.message);
        }
    } catch (error) {
        alert('Error: ' + error.message);
    }
}
</script>
@endsection
