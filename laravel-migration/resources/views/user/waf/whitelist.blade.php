@extends('layouts.user')

@section('title', 'WAF Whitelist')

@section('content')
<div class="container mx-auto px-4 py-8">
    <!-- Header -->
    <div class="mb-8">
        <div class="flex items-center text-sm text-gray-600 mb-2">
            <a href="{{ route('user.waf.index') }}" class="hover:text-blue-600">Web Application Firewall</a>
            <svg class="w-4 h-4 mx-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
            </svg>
            <span>IP Whitelist</span>
        </div>
        <h1 class="text-3xl font-bold text-gray-900">IP Whitelist</h1>
        <p class="text-gray-600 mt-2">Manage trusted IP addresses that bypass WAF protection</p>
    </div>

    <!-- Info Notice -->
    <div class="bg-blue-50 border-l-4 border-blue-500 p-6 mb-8">
        <div class="flex items-start">
            <svg class="w-6 h-6 text-blue-500 mr-3 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
            <div>
                <h3 class="text-lg font-semibold text-blue-800 mb-2">About IP Whitelisting</h3>
                <p class="text-blue-700 mb-2">
                    Whitelisted IP addresses will bypass WAF protection. Use this feature carefully - only whitelist IPs you completely trust.
                </p>
                <p class="text-blue-700 text-sm">
                    <strong>Common use cases:</strong> Your office IP, trusted API servers, payment gateways, monitoring services
                </p>
            </div>
        </div>
    </div>

    <!-- Add IP Form -->
    <div class="bg-white rounded-lg shadow p-6 mb-8">
        <h2 class="text-xl font-semibold text-gray-900 mb-4">Add IP to Whitelist</h2>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">IP Address or CIDR Range</label>
                <input type="text"
                       id="whitelistIP"
                       placeholder="e.g., 203.0.113.0 or 203.0.113.0/24"
                       class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                <p class="text-xs text-gray-500 mt-1">Single IP or CIDR notation for ranges</p>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Description (Optional)</label>
                <input type="text"
                       id="whitelistDescription"
                       placeholder="e.g., Office IP"
                       class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
            </div>
            <div class="flex items-end">
                <button onclick="addToWhitelist()"
                        class="w-full bg-green-600 text-white px-4 py-2 rounded-lg hover:bg-green-700 transition">
                    Add to Whitelist
                </button>
            </div>
        </div>
    </div>

    <!-- Whitelist Entries -->
    <div class="bg-white rounded-lg shadow overflow-hidden">
        <div class="px-6 py-4 bg-gray-50 border-b border-gray-200">
            <h2 class="text-xl font-semibold text-gray-900">
                Whitelisted IPs ({{ $entries->count() }} / 50)
            </h2>
        </div>
        <div class="p-6">
            @if($entries->count() > 0)
            <div class="space-y-3">
                @foreach($entries as $entry)
                <div class="flex items-center justify-between p-4 border border-gray-200 rounded-lg hover:bg-gray-50">
                    <div class="flex-1">
                        <div class="flex items-center gap-3 mb-2">
                            <span class="font-mono font-semibold text-gray-900">{{ $entry->value }}</span>
                            <span class="px-2 py-1 bg-green-100 text-green-800 text-xs rounded">
                                {{ $entry->type === 'ip' ? 'Single IP' : 'IP Range' }}
                            </span>
                        </div>
                        @if($entry->description)
                        <p class="text-sm text-gray-600">{{ $entry->description }}</p>
                        @endif
                        <p class="text-xs text-gray-500 mt-1">Added {{ $entry->created_at->diffForHumans() }}</p>
                    </div>
                    <button onclick="removeFromWhitelist({{ $entry->id }})"
                            class="ml-4 bg-red-600 text-white px-4 py-2 rounded-lg hover:bg-red-700 transition text-sm">
                        Remove
                    </button>
                </div>
                @endforeach
            </div>
            @else
            <div class="text-center py-12">
                <svg class="w-16 h-16 mx-auto text-gray-400 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0121 12c0 .722-.067 1.427-.196 2.113m0 0a11.955 11.955 0 01-11.724 11.724M3 5.277a11.955 11.955 0 012.277-.196c6.627 0 12 5.373 12 12 0 .778-.074 1.54-.216 2.277"/>
                </svg>
                <h3 class="text-lg font-semibold text-gray-700 mb-2">No Whitelisted IPs</h3>
                <p class="text-gray-600 mb-4">Add trusted IP addresses to bypass WAF protection</p>
            </div>
            @endif
        </div>
    </div>

    <!-- Tips -->
    <div class="mt-8 bg-yellow-50 border border-yellow-200 rounded-lg p-6">
        <h3 class="text-lg font-semibold text-yellow-900 mb-3">Best Practices</h3>
        <ul class="space-y-2 text-sm text-yellow-800">
            <li class="flex items-start">
                <svg class="w-5 h-5 mr-2 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                <span>Only whitelist IPs you completely trust - they will bypass all WAF protection</span>
            </li>
            <li class="flex items-start">
                <svg class="w-5 h-5 mr-2 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                <span>Use static IPs for whitelisting - dynamic IPs may change and expose your sites</span>
            </li>
            <li class="flex items-start">
                <svg class="w-5 h-5 mr-2 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                <span>Add descriptions to remember why each IP was whitelisted</span>
            </li>
            <li class="flex items-start">
                <svg class="w-5 h-5 mr-2 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                <span>Review your whitelist regularly and remove entries that are no longer needed</span>
            </li>
            <li class="flex items-start">
                <svg class="w-5 h-5 mr-2 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                <span>Maximum of 50 whitelist entries per account</span>
            </li>
        </ul>
    </div>
</div>

<script>
function addToWhitelist() {
    const ip = document.getElementById('whitelistIP').value.trim();
    const description = document.getElementById('whitelistDescription').value.trim();

    if (!ip) {
        alert('Please enter an IP address');
        return;
    }

    // Basic validation
    const ipPattern = /^(\d{1,3}\.){3}\d{1,3}(\/\d{1,2})?$/;
    if (!ipPattern.test(ip)) {
        alert('Please enter a valid IP address or CIDR range (e.g., 203.0.113.0 or 203.0.113.0/24)');
        return;
    }

    const type = ip.includes('/') ? 'ip_range' : 'ip';

    fetch('{{ route('user.waf.whitelist.add') }}', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
        },
        body: JSON.stringify({
            type: type,
            value: ip,
            description: description
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
    .catch(error => {
        alert('Error adding IP to whitelist');
    });
}

function removeFromWhitelist(id) {
    if (!confirm('Remove this IP from the whitelist?')) return;

    fetch(`{{ route('user.waf.whitelist.remove', ':id') }}`.replace(':id', id), {
        method: 'DELETE',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
        }
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
</script>
@endsection
