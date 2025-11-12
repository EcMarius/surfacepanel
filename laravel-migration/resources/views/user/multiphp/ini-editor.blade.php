@extends('layouts.user')

@section('title', 'PHP.ini Editor - ' . $domain)

@section('content')
<div class="container mx-auto px-4 py-8">
    <!-- Header -->
    <div class="mb-8">
        <div class="flex items-center text-sm text-gray-600 mb-2">
            <a href="{{ route('user.multiphp.index') }}" class="hover:text-blue-600">MultiPHP Manager</a>
            <svg class="w-4 h-4 mx-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
            </svg>
            <span>{{ $domain }}</span>
        </div>
        <h1 class="text-3xl font-bold text-gray-900">PHP.ini Editor</h1>
        <p class="text-gray-600 mt-2">Customize PHP settings for <strong>{{ $domain }}</strong></p>
    </div>

    <!-- Current PHP Version Info -->
    <div class="bg-gray-50 border border-gray-200 rounded-lg p-6 mb-8">
        <div class="flex items-center justify-between">
            <div>
                <h3 class="text-lg font-semibold text-gray-900">Current PHP Version</h3>
                <p class="text-gray-600 mt-1">PHP {{ $setting->phpVersion->version }}</p>
            </div>
            <div class="text-right">
                <p class="text-sm text-gray-600">{{ count($setting->iniOverrides) }} custom settings</p>
            </div>
        </div>
    </div>

    <!-- Warning Notice -->
    <div class="bg-yellow-50 border-l-4 border-yellow-500 p-6 mb-8">
        <div class="flex items-start">
            <svg class="w-6 h-6 text-yellow-500 mr-3 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
            </svg>
            <div>
                <h3 class="text-lg font-semibold text-yellow-800 mb-2">Important Notice</h3>
                <p class="text-yellow-700">
                    Incorrect PHP settings can break your website. Only modify settings if you understand their purpose.
                    Changes take effect immediately after PHP-FPM reload.
                </p>
            </div>
        </div>
    </div>

    <!-- Common PHP.ini Directives -->
    <div class="bg-white rounded-lg shadow overflow-hidden mb-8">
        <div class="px-6 py-4 bg-gray-50 border-b border-gray-200">
            <h2 class="text-xl font-semibold text-gray-900">Common PHP Settings</h2>
        </div>
        <div class="p-6">
            <div class="grid grid-cols-1 gap-6">
                @foreach($commonDirectives as $directive => $info)
                @php
                    $override = $setting->iniOverrides->where('directive', $directive)->first();
                    $currentValue = $override ? $override->value : $info['default'];
                @endphp
                <div class="border border-gray-200 rounded-lg p-4">
                    <div class="flex items-start justify-between mb-2">
                        <div class="flex-1">
                            <h3 class="font-semibold text-gray-900">{{ $info['label'] }}</h3>
                            <p class="text-sm text-gray-600 mt-1">{{ $info['description'] }}</p>
                            <p class="text-xs text-gray-500 mt-1">
                                <span class="font-mono">{{ $directive }}</span> · Default: <span class="font-mono">{{ $info['default'] }}</span>
                            </p>
                        </div>
                        <div class="ml-4 flex items-center gap-2">
                            @if($override)
                            <span class="text-xs bg-blue-100 text-blue-800 px-2 py-1 rounded">Modified</span>
                            @endif
                        </div>
                    </div>
                    <div class="flex items-center gap-2 mt-3">
                        <input type="text"
                               id="directive-{{ $loop->index }}"
                               value="{{ $currentValue }}"
                               placeholder="{{ $info['default'] }}"
                               class="flex-1 border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                        <button onclick="updateDirective('{{ $directive }}', document.getElementById('directive-{{ $loop->index }}').value)"
                                class="bg-blue-600 text-white px-4 py-2 rounded-lg text-sm hover:bg-blue-700 transition">
                            Update
                        </button>
                        @if($override)
                        <button onclick="deleteDirective('{{ $directive }}')"
                                class="bg-red-600 text-white px-4 py-2 rounded-lg text-sm hover:bg-red-700 transition">
                            Reset
                        </button>
                        @endif
                    </div>
                </div>
                @endforeach
            </div>
        </div>
    </div>

    <!-- Custom Directives -->
    <div class="bg-white rounded-lg shadow overflow-hidden">
        <div class="px-6 py-4 bg-gray-50 border-b border-gray-200 flex justify-between items-center">
            <h2 class="text-xl font-semibold text-gray-900">Custom PHP Settings</h2>
            <button onclick="showCustomModal()"
                    class="bg-green-600 text-white px-4 py-2 rounded-lg text-sm hover:bg-green-700 transition">
                Add Custom Setting
            </button>
        </div>
        <div class="p-6">
            @php
                $customOverrides = $setting->iniOverrides->filter(function($override) use ($commonDirectives) {
                    return !isset($commonDirectives[$override->directive]);
                });
            @endphp

            @if($customOverrides->isNotEmpty())
            <div class="space-y-3">
                @foreach($customOverrides as $override)
                <div class="flex items-center justify-between border border-gray-200 rounded-lg p-4">
                    <div>
                        <p class="font-mono text-sm font-semibold text-gray-900">{{ $override->directive }}</p>
                        <p class="font-mono text-sm text-gray-600 mt-1">{{ $override->value }}</p>
                    </div>
                    <button onclick="deleteDirective('{{ $override->directive }}')"
                            class="bg-red-600 text-white px-3 py-1 rounded text-sm hover:bg-red-700 transition">
                        Delete
                    </button>
                </div>
                @endforeach
            </div>
            @else
            <p class="text-center text-gray-500 py-8">No custom PHP settings configured</p>
            @endif
        </div>
    </div>
</div>

<!-- Custom Directive Modal -->
<div id="customModal" class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
    <div class="bg-white rounded-lg shadow-xl max-w-md w-full mx-4">
        <div class="px-6 py-4 border-b border-gray-200">
            <h3 class="text-xl font-semibold text-gray-900">Add Custom PHP Setting</h3>
        </div>
        <div class="p-6">
            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Directive Name</label>
                    <input type="text"
                           id="customDirective"
                           placeholder="e.g., max_file_uploads"
                           class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Value</label>
                    <input type="text"
                           id="customValue"
                           placeholder="e.g., 20"
                           class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                </div>
            </div>
        </div>
        <div class="px-6 py-4 bg-gray-50 border-t border-gray-200 flex justify-end gap-3">
            <button onclick="hideCustomModal()"
                    class="bg-gray-300 text-gray-700 px-4 py-2 rounded-lg hover:bg-gray-400 transition">
                Cancel
            </button>
            <button onclick="addCustomDirective()"
                    class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition">
                Add Setting
            </button>
        </div>
    </div>
</div>

<script>
const domain = '{{ $domain }}';

function updateDirective(directive, value) {
    if (!value.trim()) {
        alert('Please enter a value');
        return;
    }

    fetch('{{ route('user.multiphp.ini-directive') }}', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
        },
        body: JSON.stringify({
            domain: domain,
            directive: directive,
            value: value
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('✓ ' + data.message);
            location.reload();
        } else {
            alert('Error: ' + data.message);
        }
    })
    .catch(error => alert('Error updating directive'));
}

function deleteDirective(directive) {
    if (!confirm(`Reset ${directive} to default value?`)) return;

    fetch('{{ route('user.multiphp.ini-directive.delete') }}', {
        method: 'DELETE',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
        },
        body: JSON.stringify({
            domain: domain,
            directive: directive
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('✓ ' + data.message);
            location.reload();
        } else {
            alert('Error: ' + data.message);
        }
    });
}

function showCustomModal() {
    document.getElementById('customModal').classList.remove('hidden');
}

function hideCustomModal() {
    document.getElementById('customModal').classList.add('hidden');
    document.getElementById('customDirective').value = '';
    document.getElementById('customValue').value = '';
}

function addCustomDirective() {
    const directive = document.getElementById('customDirective').value.trim();
    const value = document.getElementById('customValue').value.trim();

    if (!directive || !value) {
        alert('Please enter both directive name and value');
        return;
    }

    updateDirective(directive, value);
    hideCustomModal();
}
</script>
@endsection
