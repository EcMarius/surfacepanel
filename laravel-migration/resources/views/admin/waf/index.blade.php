@extends('layouts.admin')

@section('title', 'Web Application Firewall - WHM')

@section('content')
<div class="container mx-auto px-4 py-8">
    <!-- Header -->
    <div class="mb-8">
        <h1 class="text-3xl font-bold text-gray-900">Web Application Firewall (ModSecurity)</h1>
        <p class="text-gray-600 mt-2">Protect your server from web attacks</p>
    </div>

    <!-- WAF Status Card -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-8">
        <!-- Configuration Card -->
        <div class="bg-white rounded-lg shadow-lg p-6 border-l-4 {{ $config->is_enabled ? 'border-green-500' : 'border-red-500' }}">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-semibold text-gray-900">WAF Status</h3>
                <span class="px-3 py-1 rounded-full text-sm font-semibold {{ $config->is_enabled ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">
                    {{ $config->is_enabled ? 'Enabled' : 'Disabled' }}
                </span>
            </div>
            <div class="space-y-2 text-sm">
                <div class="flex justify-between">
                    <span class="text-gray-600">Mode:</span>
                    <span class="font-semibold capitalize">{{ $config->mode }}</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-gray-600">OWASP CRS:</span>
                    <span class="font-semibold">{{ $config->use_owasp_crs ? 'Enabled' : 'Disabled' }}</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-gray-600">Paranoia Level:</span>
                    <span class="font-semibold">{{ $config->paranoia_level }}</span>
                </div>
            </div>
            <button onclick="showConfigModal()" class="mt-4 w-full bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition">
                Configure WAF
            </button>
        </div>

        <!-- Statistics Card -->
        <div class="bg-white rounded-lg shadow-lg p-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">Last 7 Days</h3>
            <div class="space-y-3">
                <div>
                    <div class="flex justify-between text-sm mb-1">
                        <span class="text-gray-600">Total Attacks</span>
                        <span class="font-bold text-gray-900">{{ number_format($stats['total']) }}</span>
                    </div>
                    <div class="w-full bg-gray-200 rounded-full h-2">
                        <div class="bg-red-600 h-2 rounded-full" style="width: 100%"></div>
                    </div>
                </div>
                <div>
                    <div class="flex justify-between text-sm mb-1">
                        <span class="text-gray-600">Blocked</span>
                        <span class="font-bold text-red-600">{{ number_format($stats['blocked']) }}</span>
                    </div>
                    <div class="w-full bg-gray-200 rounded-full h-2">
                        <div class="bg-red-600 h-2 rounded-full" style="width: {{ $stats['total'] > 0 ? ($stats['blocked'] / $stats['total'] * 100) : 0 }}%"></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Lists Card -->
        <div class="bg-white rounded-lg shadow-lg p-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">Access Lists</h3>
            <div class="space-y-3">
                <a href="{{ route('admin.waf.whitelist') }}" class="block">
                    <div class="flex items-center justify-between p-3 bg-green-50 rounded-lg hover:bg-green-100 transition">
                        <div class="flex items-center">
                            <svg class="w-5 h-5 text-green-600 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                            <span class="font-medium text-gray-900">Whitelist</span>
                        </div>
                        <span class="text-sm font-semibold text-green-600">{{ $whitelistCount }}</span>
                    </div>
                </a>
                <a href="{{ route('admin.waf.blacklist') }}" class="block">
                    <div class="flex items-center justify-between p-3 bg-red-50 rounded-lg hover:bg-red-100 transition">
                        <div class="flex items-center">
                            <svg class="w-5 h-5 text-red-600 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"/>
                            </svg>
                            <span class="font-medium text-gray-900">Blacklist</span>
                        </div>
                        <span class="text-sm font-semibold text-red-600">{{ $blacklistCount }}</span>
                    </div>
                </a>
            </div>
        </div>
    </div>

    <!-- Attack Statistics -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
        <!-- Attack Types -->
        <div class="bg-white rounded-lg shadow p-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">Attack Types (Last 7 Days)</h3>
            <div class="space-y-3">
                <div class="flex items-center justify-between">
                    <span class="text-gray-700">SQL Injection</span>
                    <span class="font-bold text-red-600">{{ number_format($stats['sql_injection']) }}</span>
                </div>
                <div class="flex items-center justify-between">
                    <span class="text-gray-700">Cross-Site Scripting (XSS)</span>
                    <span class="font-bold text-orange-600">{{ number_format($stats['xss']) }}</span>
                </div>
                <div class="flex items-center justify-between">
                    <span class="text-gray-700">Local File Inclusion (LFI)</span>
                    <span class="font-bold text-yellow-600">{{ number_format($stats['lfi']) }}</span>
                </div>
                <div class="flex items-center justify-between">
                    <span class="text-gray-700">Remote File Inclusion (RFI)</span>
                    <span class="font-bold text-yellow-600">{{ number_format($stats['rfi']) }}</span>
                </div>
                <div class="flex items-center justify-between">
                    <span class="text-gray-700">Remote Code Execution (RCE)</span>
                    <span class="font-bold text-red-600">{{ number_format($stats['rce']) }}</span>
                </div>
            </div>
        </div>

        <!-- Top Attacking IPs -->
        <div class="bg-white rounded-lg shadow p-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">Top Attacking IPs</h3>
            @if(count($topIPs) > 0)
            <div class="space-y-2">
                @foreach($topIPs as $ipData)
                <div class="flex items-center justify-between p-2 hover:bg-gray-50 rounded">
                    <span class="font-mono text-sm text-gray-700">{{ $ipData['ip'] }}</span>
                    <div class="flex items-center gap-2">
                        <span class="text-sm font-semibold text-red-600">{{ number_format($ipData['count']) }} attacks</span>
                        <button onclick="blockIP('{{ $ipData['ip'] }}')" class="text-xs bg-red-600 text-white px-2 py-1 rounded hover:bg-red-700">
                            Block
                        </button>
                    </div>
                </div>
                @endforeach
            </div>
            @else
            <p class="text-center text-gray-500 py-4">No attacks detected</p>
            @endif
        </div>
    </div>

    <!-- Recent Critical Attacks -->
    <div class="bg-white rounded-lg shadow p-6 mb-8">
        <div class="flex justify-between items-center mb-4">
            <h3 class="text-lg font-semibold text-gray-900">Recent Critical Attacks</h3>
            <a href="{{ route('admin.waf.logs') }}" class="text-blue-600 hover:text-blue-800 text-sm font-medium">
                View All Logs →
            </a>
        </div>
        @if($recentAttacks->count() > 0)
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Time</th>
                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">IP Address</th>
                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Domain</th>
                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Attack Type</th>
                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Severity</th>
                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Action</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    @foreach($recentAttacks as $attack)
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-2 text-sm text-gray-600">{{ $attack->created_at->diffForHumans() }}</td>
                        <td class="px-4 py-2 text-sm font-mono">{{ $attack->client_ip }}</td>
                        <td class="px-4 py-2 text-sm">{{ $attack->domain }}</td>
                        <td class="px-4 py-2 text-sm">{{ ucwords(str_replace('_', ' ', $attack->attack_type)) }}</td>
                        <td class="px-4 py-2">
                            <span class="px-2 py-1 text-xs rounded {{ $attack->getSeverityColor() }} text-white">
                                {{ $attack->getSeverityName() }}
                            </span>
                        </td>
                        <td class="px-4 py-2 text-sm capitalize">{{ $attack->action }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @else
        <p class="text-center text-gray-500 py-8">No critical attacks detected</p>
        @endif
    </div>
</div>

<!-- Configuration Modal -->
<div id="configModal" class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
    <div class="bg-white rounded-lg shadow-xl max-w-2xl w-full mx-4">
        <div class="px-6 py-4 border-b border-gray-200">
            <h3 class="text-xl font-semibold text-gray-900">WAF Configuration</h3>
        </div>
        <div class="p-6">
            <form id="wafConfigForm">
                <div class="space-y-4">
                    <!-- Enable WAF -->
                    <div>
                        <label class="flex items-center">
                            <input type="checkbox" id="is_enabled" {{ $config->is_enabled ? 'checked' : '' }} class="rounded border-gray-300">
                            <span class="ml-2 text-sm font-medium text-gray-700">Enable Web Application Firewall</span>
                        </label>
                    </div>

                    <!-- Mode -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">WAF Mode</label>
                        <select id="mode" class="w-full border border-gray-300 rounded-lg px-3 py-2">
                            <option value="off" {{ $config->mode === 'off' ? 'selected' : '' }}>Off</option>
                            <option value="detection" {{ $config->mode === 'detection' ? 'selected' : '' }}>Detection Only (Log attacks, don't block)</option>
                            <option value="blocking" {{ $config->mode === 'blocking' ? 'selected' : '' }}>Blocking (Block attacks)</option>
                        </select>
                    </div>

                    <!-- OWASP CRS -->
                    <div>
                        <label class="flex items-center">
                            <input type="checkbox" id="use_owasp_crs" {{ $config->use_owasp_crs ? 'checked' : '' }} class="rounded border-gray-300">
                            <span class="ml-2 text-sm font-medium text-gray-700">Use OWASP Core Rule Set</span>
                        </label>
                        <p class="ml-6 text-xs text-gray-500 mt-1">Industry-standard web application firewall rules</p>
                    </div>

                    <!-- Paranoia Level -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            Paranoia Level: <span id="paranoiaLabel" class="font-semibold">{{ $config->paranoia_level }}</span>
                        </label>
                        <input type="range" id="paranoia_level" min="1" max="4" value="{{ $config->paranoia_level }}"
                               class="w-full" onchange="updateParanoiaLabel(this.value)">
                        <div class="flex justify-between text-xs text-gray-500 mt-1">
                            <span>1 - Normal</span>
                            <span>2 - Elevated</span>
                            <span>3 - High</span>
                            <span>4 - Paranoid</span>
                        </div>
                        <p class="text-xs text-gray-500 mt-2">{{ $config->getParanoiaLevelDescription() }}</p>
                    </div>
                </div>
            </form>
        </div>
        <div class="px-6 py-4 bg-gray-50 border-t border-gray-200 flex justify-end gap-3">
            <button onclick="hideConfigModal()" class="bg-gray-300 text-gray-700 px-4 py-2 rounded-lg hover:bg-gray-400 transition">
                Cancel
            </button>
            <button onclick="saveConfig()" class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition">
                Save Configuration
            </button>
        </div>
    </div>
</div>

<script>
function showConfigModal() {
    document.getElementById('configModal').classList.remove('hidden');
}

function hideConfigModal() {
    document.getElementById('configModal').classList.add('hidden');
}

function updateParanoiaLabel(value) {
    document.getElementById('paranoiaLabel').textContent = value;
}

function saveConfig() {
    const data = {
        is_enabled: document.getElementById('is_enabled').checked,
        mode: document.getElementById('mode').value,
        use_owasp_crs: document.getElementById('use_owasp_crs').checked,
        paranoia_level: parseInt(document.getElementById('paranoia_level').value)
    };

    fetch('{{ route('admin.waf.config') }}', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
        },
        body: JSON.stringify(data)
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

function blockIP(ip) {
    if (!confirm(`Block IP ${ip}? This will add it to the global blacklist.`)) return;

    fetch('{{ route('admin.waf.blacklist.add') }}', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
        },
        body: JSON.stringify({
            type: 'ip',
            value: ip,
            reason: 'Multiple attack attempts'
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('✓ IP blocked successfully');
            location.reload();
        }
    });
}
</script>
@endsection
