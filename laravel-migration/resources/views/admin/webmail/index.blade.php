@extends('layouts.admin')

@section('title', 'Webmail Management - WHM')

@section('content')
<div class="container mx-auto px-4 py-8">
    <!-- Header -->
    <div class="mb-8">
        <h1 class="text-3xl font-bold text-gray-900">Webmail Management (Roundcube)</h1>
        <p class="text-gray-600 mt-2">Configure and manage webmail access for your users</p>
    </div>

    @if(!$config->isInstalled())
        <!-- Installation Required Card -->
        <div class="bg-yellow-50 border-l-4 border-yellow-400 p-6 rounded-lg mb-8">
            <div class="flex items-center mb-4">
                <svg class="w-6 h-6 text-yellow-600 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                </svg>
                <h3 class="text-lg font-semibold text-yellow-800">Roundcube Not Installed</h3>
            </div>
            <p class="text-yellow-700 mb-4">Roundcube webmail is not installed on this server. Install it now to enable webmail access for your users.</p>
            <a href="{{ route('admin.webmail.install') }}" class="inline-flex items-center bg-yellow-600 text-white px-6 py-2 rounded-lg hover:bg-yellow-700 transition">
                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                </svg>
                Install Roundcube
            </a>
        </div>
    @else
        <!-- Webmail Status Card -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-8">
            <!-- Installation Info -->
            <div class="bg-white rounded-lg shadow-lg p-6 border-l-4 border-green-500">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-semibold text-gray-900">Installation</h3>
                    <span class="px-3 py-1 rounded-full text-sm font-semibold bg-green-100 text-green-800">
                        Installed
                    </span>
                </div>
                <div class="space-y-2 text-sm">
                    <div class="flex justify-between">
                        <span class="text-gray-600">Version:</span>
                        <span class="font-semibold">{{ $config->version ?? 'Unknown' }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-600">Theme:</span>
                        <span class="font-semibold capitalize">{{ $config->default_theme }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-600">Plugins:</span>
                        <span class="font-semibold">{{ count($config->enabled_plugins ?? []) }}</span>
                    </div>
                </div>
                <button onclick="showConfigModal()" class="mt-4 w-full bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition">
                    Configure
                </button>
            </div>

            <!-- Statistics -->
            <div class="bg-white rounded-lg shadow-lg p-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">Last 30 Days</h3>
                <div class="space-y-3">
                    <div>
                        <div class="flex justify-between text-sm mb-1">
                            <span class="text-gray-600">Total Sessions</span>
                            <span class="font-bold text-gray-900">{{ number_format($statistics['total_sessions']) }}</span>
                        </div>
                        <div class="w-full bg-gray-200 rounded-full h-2">
                            <div class="bg-blue-600 h-2 rounded-full" style="width: 100%"></div>
                        </div>
                    </div>
                    <div>
                        <div class="flex justify-between text-sm mb-1">
                            <span class="text-gray-600">Active Sessions</span>
                            <span class="font-bold text-green-600">{{ number_format($statistics['active_sessions']) }}</span>
                        </div>
                        <div class="w-full bg-gray-200 rounded-full h-2">
                            <div class="bg-green-600 h-2 rounded-full" style="width: {{ $statistics['total_sessions'] > 0 ? ($statistics['active_sessions'] / $statistics['total_sessions'] * 100) : 0 }}%"></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Quick Links -->
            <div class="bg-white rounded-lg shadow-lg p-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">Quick Actions</h3>
                <div class="space-y-3">
                    <a href="{{ $config->getWebmailUrl() }}" target="_blank" class="block">
                        <div class="flex items-center justify-between p-3 bg-blue-50 rounded-lg hover:bg-blue-100 transition">
                            <div class="flex items-center">
                                <svg class="w-5 h-5 text-blue-600 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                                </svg>
                                <span class="font-medium text-gray-900">Open Webmail</span>
                            </div>
                            <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/>
                            </svg>
                        </div>
                    </a>
                    <a href="{{ route('admin.webmail.sessions') }}" class="block">
                        <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg hover:bg-gray-100 transition">
                            <div class="flex items-center">
                                <svg class="w-5 h-5 text-gray-600 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                </svg>
                                <span class="font-medium text-gray-900">View Sessions</span>
                            </div>
                        </div>
                    </a>
                </div>
            </div>
        </div>

        <!-- Configuration Details -->
        <div class="bg-white rounded-lg shadow-lg p-6 mb-8">
            <h3 class="text-xl font-semibold text-gray-900 mb-6">Configuration</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <h4 class="font-semibold text-gray-700 mb-3">Server Settings</h4>
                    <div class="space-y-2 text-sm">
                        <div class="flex justify-between py-2 border-b border-gray-100">
                            <span class="text-gray-600">Installation Path:</span>
                            <span class="font-mono text-gray-900">{{ $config->installation_path }}</span>
                        </div>
                        <div class="flex justify-between py-2 border-b border-gray-100">
                            <span class="text-gray-600">IMAP Host:</span>
                            <span class="font-mono text-gray-900">{{ $config->imap_host }}</span>
                        </div>
                        <div class="flex justify-between py-2 border-b border-gray-100">
                            <span class="text-gray-600">SMTP Host:</span>
                            <span class="font-mono text-gray-900">{{ $config->smtp_host }}</span>
                        </div>
                        <div class="flex justify-between py-2">
                            <span class="text-gray-600">SMTP Auth:</span>
                            <span class="font-semibold {{ $config->smtp_auth ? 'text-green-600' : 'text-red-600' }}">
                                {{ $config->smtp_auth ? 'Enabled' : 'Disabled' }}
                            </span>
                        </div>
                    </div>
                </div>
                <div>
                    <h4 class="font-semibold text-gray-700 mb-3">Security & Branding</h4>
                    <div class="space-y-2 text-sm">
                        <div class="flex justify-between py-2 border-b border-gray-100">
                            <span class="text-gray-600">Product Name:</span>
                            <span class="font-semibold text-gray-900">{{ $config->product_name }}</span>
                        </div>
                        <div class="flex justify-between py-2 border-b border-gray-100">
                            <span class="text-gray-600">Force HTTPS:</span>
                            <span class="font-semibold {{ $config->force_https ? 'text-green-600' : 'text-yellow-600' }}">
                                {{ $config->force_https ? 'Enabled' : 'Disabled' }}
                            </span>
                        </div>
                        <div class="flex justify-between py-2 border-b border-gray-100">
                            <span class="text-gray-600">Session Lifetime:</span>
                            <span class="font-semibold text-gray-900">{{ $config->session_lifetime }} minutes</span>
                        </div>
                        <div class="flex justify-between py-2">
                            <span class="text-gray-600">Database:</span>
                            <span class="font-mono text-gray-900">{{ $config->database_name }}</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Plugins -->
        <div class="bg-white rounded-lg shadow-lg p-6 mb-8">
            <div class="flex items-center justify-between mb-6">
                <h3 class="text-xl font-semibold text-gray-900">Enabled Plugins</h3>
                <button onclick="showPluginsModal()" class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition">
                    Manage Plugins
                </button>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                @forelse($config->enabled_plugins ?? [] as $plugin)
                    <div class="flex items-center p-3 bg-gray-50 rounded-lg">
                        <svg class="w-5 h-5 text-green-600 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                        </svg>
                        <span class="font-medium text-gray-900 capitalize">{{ $plugin }}</span>
                    </div>
                @empty
                    <p class="text-gray-500 col-span-3">No plugins enabled</p>
                @endforelse
            </div>
        </div>

        <!-- Danger Zone -->
        <div class="bg-red-50 border border-red-200 rounded-lg p-6">
            <h3 class="text-xl font-semibold text-red-800 mb-4">Danger Zone</h3>
            <p class="text-red-700 mb-4">Uninstalling Roundcube will remove all webmail configuration and data. This action cannot be undone.</p>
            <button onclick="confirmUninstall()" class="bg-red-600 text-white px-6 py-2 rounded-lg hover:bg-red-700 transition">
                Uninstall Roundcube
            </button>
        </div>
    @endif
</div>

<!-- Configuration Modal -->
<div id="configModal" class="hidden fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50">
    <div class="relative top-20 mx-auto p-5 border w-full max-w-2xl shadow-lg rounded-lg bg-white">
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-xl font-semibold text-gray-900">Configure Webmail</h3>
            <button onclick="closeConfigModal()" class="text-gray-400 hover:text-gray-600">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>
        <form id="configForm" class="space-y-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">IMAP Host</label>
                <input type="text" name="imap_host" value="{{ $config->imap_host }}" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">SMTP Host</label>
                <input type="text" name="smtp_host" value="{{ $config->smtp_host }}" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Product Name</label>
                <input type="text" name="product_name" value="{{ $config->product_name }}" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Default Theme</label>
                <select name="default_theme" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                    @foreach(\App\Models\WebmailConfig::getAvailableThemes() as $key => $name)
                        <option value="{{ $key }}" {{ $config->default_theme === $key ? 'selected' : '' }}>{{ $name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Session Lifetime (minutes)</label>
                <input type="number" name="session_lifetime" value="{{ $config->session_lifetime }}" min="5" max="1440" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
            </div>
            <div class="flex items-center">
                <input type="checkbox" name="smtp_auth" id="smtp_auth" {{ $config->smtp_auth ? 'checked' : '' }} class="w-4 h-4 text-blue-600 rounded focus:ring-2 focus:ring-blue-500">
                <label for="smtp_auth" class="ml-2 text-sm text-gray-700">Enable SMTP Authentication</label>
            </div>
            <div class="flex items-center">
                <input type="checkbox" name="force_https" id="force_https" {{ $config->force_https ? 'checked' : '' }} class="w-4 h-4 text-blue-600 rounded focus:ring-2 focus:ring-blue-500">
                <label for="force_https" class="ml-2 text-sm text-gray-700">Force HTTPS</label>
            </div>
            <div class="flex justify-end space-x-3 pt-4">
                <button type="button" onclick="closeConfigModal()" class="px-4 py-2 bg-gray-300 text-gray-700 rounded-lg hover:bg-gray-400 transition">
                    Cancel
                </button>
                <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition">
                    Save Changes
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function showConfigModal() {
    document.getElementById('configModal').classList.remove('hidden');
}

function closeConfigModal() {
    document.getElementById('configModal').classList.add('hidden');
}

function showPluginsModal() {
    // TODO: Implement plugins modal
    alert('Plugins management coming soon');
}

function confirmUninstall() {
    if (confirm('Are you sure you want to uninstall Roundcube? This will remove all webmail data and configuration.')) {
        fetch('{{ route("admin.webmail.uninstall") }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}'
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('Roundcube uninstalled successfully');
                location.reload();
            } else {
                alert('Error: ' + data.message);
            }
        });
    }
}

document.getElementById('configForm').addEventListener('submit', function(e) {
    e.preventDefault();

    const formData = new FormData(this);
    const data = {};
    formData.forEach((value, key) => {
        if (key === 'smtp_auth' || key === 'force_https') {
            data[key] = document.querySelector(`input[name="${key}"]`).checked;
        } else {
            data[key] = value;
        }
    });

    fetch('{{ route("admin.webmail.config") }}', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': '{{ csrf_token() }}'
        },
        body: JSON.stringify(data)
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Configuration updated successfully');
            location.reload();
        } else {
            alert('Error: ' + data.message);
        }
    });
});
</script>
@endsection
