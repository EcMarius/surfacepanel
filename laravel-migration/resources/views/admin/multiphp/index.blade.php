@extends('layouts.admin')

@section('title', 'MultiPHP Manager - WHM')

@section('content')
<div class="container mx-auto px-4 py-8">
    <!-- Header -->
    <div class="flex justify-between items-center mb-8">
        <div>
            <h1 class="text-3xl font-bold text-gray-900">MultiPHP Manager</h1>
            <p class="text-gray-600 mt-2">Manage PHP versions available to users</p>
        </div>
        <div class="flex gap-3">
            <button onclick="detectVersions()"
                    class="bg-blue-600 text-white px-6 py-2 rounded-lg hover:bg-blue-700 transition">
                Detect PHP Versions
            </button>
            <button onclick="showAddModal()"
                    class="bg-green-600 text-white px-6 py-2 rounded-lg hover:bg-green-700 transition">
                Add Manually
            </button>
        </div>
    </div>

    <!-- Default Version Info -->
    @if($defaultVersion)
    <div class="bg-blue-50 border-l-4 border-blue-500 p-6 mb-8">
        <div class="flex items-center">
            <svg class="w-6 h-6 text-blue-500 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
            <div>
                <h3 class="text-lg font-semibold text-blue-800">Default PHP Version</h3>
                <p class="text-blue-700">
                    <strong>PHP {{ $defaultVersion->version }}</strong> is set as the system default.
                    New domains will use this version unless specified otherwise.
                </p>
            </div>
        </div>
    </div>
    @endif

    <!-- PHP Versions Grid -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        @forelse($phpVersions as $phpVersion)
        <div class="border rounded-lg p-6 {{ $phpVersion->is_default ? 'border-blue-300 bg-blue-50' : 'border-gray-200 bg-white' }} shadow-sm">
            <!-- Version Header -->
            <div class="flex items-center justify-between mb-4">
                <div>
                    <h3 class="text-2xl font-bold text-gray-900">
                        PHP {{ $phpVersion->version }}
                    </h3>
                    @if($phpVersion->is_default)
                    <span class="inline-block bg-blue-600 text-white text-xs px-2 py-1 rounded mt-1">
                        DEFAULT
                    </span>
                    @endif
                </div>

                <!-- Status Toggle -->
                <label class="relative inline-flex items-center cursor-pointer">
                    <input type="checkbox"
                           class="sr-only peer"
                           {{ $phpVersion->is_active ? 'checked' : '' }}
                           onchange="toggleActive({{ $phpVersion->id }}, this)"
                           {{ $phpVersion->is_default ? 'disabled' : '' }}>
                    <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-blue-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-green-600"></div>
                </label>
            </div>

            <!-- Version Details -->
            <div class="space-y-2 text-sm text-gray-600 mb-4">
                <div class="flex items-center">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                    </svg>
                    <span class="font-mono text-xs">{{ $phpVersion->binary_path }}</span>
                </div>
                <div class="flex items-center">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/>
                    </svg>
                    <span>{{ count($phpVersion->extensions ?? []) }} extensions loaded</span>
                </div>
                <div class="flex items-center">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0"/>
                    </svg>
                    <span>{{ $usageStats[$phpVersion->id] ?? 0 }} domains using</span>
                </div>
            </div>

            <!-- Actions -->
            <div class="flex gap-2">
                @if(!$phpVersion->is_default)
                <button onclick="setDefault({{ $phpVersion->id }})"
                        class="flex-1 bg-blue-600 text-white px-3 py-2 rounded text-sm hover:bg-blue-700 transition"
                        {{ !$phpVersion->is_active ? 'disabled' : '' }}>
                    Set Default
                </button>
                @endif
                <button onclick="viewDetails({{ $phpVersion->id }})"
                        class="flex-1 bg-gray-600 text-white px-3 py-2 rounded text-sm hover:bg-gray-700 transition">
                    Details
                </button>
                @if(!$phpVersion->is_default && ($usageStats[$phpVersion->id] ?? 0) === 0)
                <button onclick="deleteVersion({{ $phpVersion->id }})"
                        class="bg-red-600 text-white px-3 py-2 rounded text-sm hover:bg-red-700 transition">
                    Delete
                </button>
                @endif
            </div>
        </div>
        @empty
        <div class="col-span-3 text-center py-12 bg-gray-50 rounded-lg">
            <svg class="w-16 h-16 mx-auto text-gray-400 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.172 16.172a4 4 0 015.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
            <h3 class="text-lg font-semibold text-gray-700 mb-2">No PHP Versions Registered</h3>
            <p class="text-gray-600 mb-4">Click "Detect PHP Versions" to scan your system</p>
            <button onclick="detectVersions()"
                    class="bg-blue-600 text-white px-6 py-2 rounded-lg hover:bg-blue-700 transition">
                Detect Now
            </button>
        </div>
        @endforelse
    </div>
</div>

<script>
function detectVersions() {
    if (!confirm('Scan the system for installed PHP versions?')) return;

    fetch('{{ route('admin.multiphp.detect') }}', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert(data.message);
            location.reload();
        } else {
            alert('Error: ' + data.message);
        }
    })
    .catch(error => alert('Error detecting PHP versions'));
}

function setDefault(id) {
    if (!confirm('Set this PHP version as the system default?')) return;

    fetch(`{{ url('admin/multiphp') }}/${id}/set-default`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert(data.message);
            location.reload();
        } else {
            alert('Error: ' + data.message);
        }
    });
}

function toggleActive(id, checkbox) {
    fetch(`{{ url('admin/multiphp') }}/${id}/toggle-active`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert(data.message);
        } else {
            alert('Error: ' + data.message);
            checkbox.checked = !checkbox.checked;
        }
    });
}

function viewDetails(id) {
    fetch(`{{ url('admin/multiphp') }}/${id}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const php = data.phpVersion;
                alert(`PHP ${php.version} Details:\n\nBinary: ${php.binary_path}\nExtensions: ${php.extensions.length}\nDomains Using: ${php.domain_settings_count}`);
            }
        });
}

function deleteVersion(id) {
    if (!confirm('Delete this PHP version? This action cannot be undone.')) return;

    fetch(`{{ url('admin/multiphp') }}/${id}`, {
        method: 'DELETE',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert(data.message);
            location.reload();
        } else {
            alert('Error: ' + data.message);
        }
    });
}
</script>
@endsection
