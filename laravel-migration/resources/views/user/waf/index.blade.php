@extends('layouts.user')

@section('title', 'Web Application Firewall')

@section('content')
<div class="container mx-auto px-4 py-8">
    <!-- Header -->
    <div class="mb-8">
        <h1 class="text-3xl font-bold text-gray-900">Web Application Firewall</h1>
        <p class="text-gray-600 mt-2">Monitor security and protect your websites from attacks</p>
    </div>

    <!-- WAF Status Notice -->
    <div class="bg-{{ $config->is_enabled ? 'green' : 'yellow' }}-50 border-l-4 border-{{ $config->is_enabled ? 'green' : 'yellow' }}-500 p-6 mb-8">
        <div class="flex items-start">
            <svg class="w-6 h-6 text-{{ $config->is_enabled ? 'green' : 'yellow' }}-500 mr-3 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0121 12a11.955 11.955 0 01-2.382 7.157m0 0L21 21l-2.382-1.843M3 12a11.955 11.955 0 012.382-7.157M3 12a11.955 11.955 0 01 2.382 7.157m0 0L3 21l2.382-1.843"/>
            </svg>
            <div>
                <h3 class="text-lg font-semibold text-{{ $config->is_enabled ? 'green' : 'yellow' }}-800 mb-2">
                    Web Application Firewall {{ $config->is_enabled ? 'Active' : 'Disabled' }}
                </h3>
                <p class="text-{{ $config->is_enabled ? 'green' : 'yellow' }}-700">
                    @if($config->is_enabled)
                        Your websites are being protected from web attacks. Mode: <strong class="capitalize">{{ $config->mode }}</strong>
                    @else
                        The WAF is currently disabled. Please contact your hosting provider for protection.
                    @endif
                </p>
            </div>
        </div>
    </div>

    <!-- Statistics Cards -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
        <!-- Total Attacks -->
        <div class="bg-white rounded-lg shadow p-6 border-l-4 border-red-500">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-600 mb-1">Total Attacks (7 days)</p>
                    <p class="text-3xl font-bold text-gray-900">{{ number_format($stats['total']) }}</p>
                </div>
                <div class="bg-red-100 rounded-full p-3">
                    <svg class="w-8 h-8 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                    </svg>
                </div>
            </div>
        </div>

        <!-- Blocked Attacks -->
        <div class="bg-white rounded-lg shadow p-6 border-l-4 border-orange-500">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-600 mb-1">Blocked Attacks</p>
                    <p class="text-3xl font-bold text-gray-900">{{ number_format($stats['blocked']) }}</p>
                </div>
                <div class="bg-orange-100 rounded-full p-3">
                    <svg class="w-8 h-8 text-orange-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"/>
                    </svg>
                </div>
            </div>
        </div>

        <!-- SQL Injection Attempts -->
        <div class="bg-white rounded-lg shadow p-6 border-l-4 border-purple-500">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-600 mb-1">SQL Injection Attempts</p>
                    <p class="text-3xl font-bold text-gray-900">{{ number_format($stats['sql_injection']) }}</p>
                </div>
                <div class="bg-purple-100 rounded-full p-3">
                    <svg class="w-8 h-8 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7M4 7c0 2.21 3.582 4 8 4s8-1.79 8-4M4 7c0-2.21 3.582-4 8-4s8 1.79 8 4"/>
                    </svg>
                </div>
            </div>
        </div>
    </div>

    <!-- Attack Types Breakdown -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
        <!-- Attack Types Chart -->
        <div class="bg-white rounded-lg shadow p-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">Attack Types (Last 7 Days)</h3>
            <div class="space-y-4">
                <div>
                    <div class="flex justify-between text-sm mb-1">
                        <span class="text-gray-700">SQL Injection</span>
                        <span class="font-semibold text-red-600">{{ number_format($stats['sql_injection']) }}</span>
                    </div>
                    <div class="w-full bg-gray-200 rounded-full h-3">
                        <div class="bg-red-600 h-3 rounded-full" style="width: {{ $stats['total'] > 0 ? ($stats['sql_injection'] / $stats['total'] * 100) : 0 }}%"></div>
                    </div>
                </div>
                <div>
                    <div class="flex justify-between text-sm mb-1">
                        <span class="text-gray-700">Cross-Site Scripting (XSS)</span>
                        <span class="font-semibold text-orange-600">{{ number_format($stats['xss']) }}</span>
                    </div>
                    <div class="w-full bg-gray-200 rounded-full h-3">
                        <div class="bg-orange-600 h-3 rounded-full" style="width: {{ $stats['total'] > 0 ? ($stats['xss'] / $stats['total'] * 100) : 0 }}%"></div>
                    </div>
                </div>
                <div>
                    <div class="flex justify-between text-sm mb-1">
                        <span class="text-gray-700">File Inclusion (LFI/RFI)</span>
                        <span class="font-semibold text-yellow-600">{{ number_format($stats['lfi'] + $stats['rfi']) }}</span>
                    </div>
                    <div class="w-full bg-gray-200 rounded-full h-3">
                        <div class="bg-yellow-600 h-3 rounded-full" style="width: {{ $stats['total'] > 0 ? (($stats['lfi'] + $stats['rfi']) / $stats['total'] * 100) : 0 }}%"></div>
                    </div>
                </div>
                <div>
                    <div class="flex justify-between text-sm mb-1">
                        <span class="text-gray-700">Remote Code Execution (RCE)</span>
                        <span class="font-semibold text-purple-600">{{ number_format($stats['rce']) }}</span>
                    </div>
                    <div class="w-full bg-gray-200 rounded-full h-3">
                        <div class="bg-purple-600 h-3 rounded-full" style="width: {{ $stats['total'] > 0 ? ($stats['rce'] / $stats['total'] * 100) : 0 }}%"></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Top Attacking IPs -->
        <div class="bg-white rounded-lg shadow p-6">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-lg font-semibold text-gray-900">Top Attacking IPs</h3>
                <a href="{{ route('user.waf.whitelist') }}" class="text-sm text-blue-600 hover:text-blue-800">
                    Manage Whitelist →
                </a>
            </div>
            @if(count($topIPs) > 0)
            <div class="space-y-2">
                @foreach($topIPs as $ipData)
                <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                    <span class="font-mono text-sm text-gray-700">{{ $ipData['ip'] }}</span>
                    <div class="flex items-center gap-2">
                        <span class="text-sm font-semibold text-red-600">{{ number_format($ipData['count']) }} attacks</span>
                        <button onclick="whitelistIP('{{ $ipData['ip'] }}')"
                                class="text-xs bg-green-600 text-white px-2 py-1 rounded hover:bg-green-700">
                            Whitelist
                        </button>
                    </div>
                </div>
                @endforeach
            </div>
            @else
            <p class="text-center text-gray-500 py-8">No attacks detected</p>
            @endif
        </div>
    </div>

    <!-- Recent Critical Attacks -->
    <div class="bg-white rounded-lg shadow p-6 mb-8">
        <div class="flex justify-between items-center mb-4">
            <h3 class="text-lg font-semibold text-gray-900">Recent Critical Attacks</h3>
            <a href="{{ route('user.waf.logs') }}" class="text-blue-600 hover:text-blue-800 text-sm font-medium">
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
                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
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
                        <td class="px-4 py-2">
                            <span class="px-2 py-1 text-xs rounded {{ $attack->action === 'blocked' ? 'bg-red-100 text-red-800' : 'bg-yellow-100 text-yellow-800' }}">
                                {{ ucfirst($attack->action) }}
                            </span>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @else
        <p class="text-center text-gray-500 py-8">No critical attacks detected - your sites are secure!</p>
        @endif
    </div>

    <!-- Quick Actions -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <a href="{{ route('user.waf.logs') }}" class="bg-white rounded-lg shadow p-6 hover:shadow-lg transition">
            <div class="flex items-center">
                <div class="bg-blue-100 rounded-full p-3 mr-4">
                    <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                    </svg>
                </div>
                <div>
                    <h4 class="font-semibold text-gray-900">View All Attack Logs</h4>
                    <p class="text-sm text-gray-600">Review detailed security logs</p>
                </div>
            </div>
        </a>

        <a href="{{ route('user.waf.whitelist') }}" class="bg-white rounded-lg shadow p-6 hover:shadow-lg transition">
            <div class="flex items-center">
                <div class="bg-green-100 rounded-full p-3 mr-4">
                    <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0121 12c0 .722-.067 1.427-.196 2.113m-.196 2.113A11.955 11.955 0 0112 21c-4.97 0-9.213-3.022-11.015-7.313M3.015 7.313A11.955 11.955 0 013 5c0-4.97 3.022-9.213 7.313-11.015"/>
                    </svg>
                </div>
                <div>
                    <h4 class="font-semibold text-gray-900">Manage IP Whitelist</h4>
                    <p class="text-sm text-gray-600">Add trusted IP addresses</p>
                </div>
            </div>
        </a>
    </div>
</div>

<script>
function whitelistIP(ip) {
    if (!confirm(`Add ${ip} to your whitelist? This IP will not be blocked by the WAF.`)) return;

    fetch('{{ route('user.waf.whitelist.add') }}', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
        },
        body: JSON.stringify({
            type: 'ip',
            value: ip,
            description: 'Whitelisted from dashboard'
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('✓ IP added to whitelist');
            location.reload();
        } else {
            alert('Error: ' + data.message);
        }
    });
}
</script>
@endsection
