@extends('layouts.user')

@section('title', 'Email Deliverability')

@section('content')
<div class="container mx-auto px-4 py-8">
    <div class="mb-8">
        <h1 class="text-3xl font-bold text-gray-900 mb-2">Email Deliverability</h1>
        <p class="text-gray-600">Configure DKIM, SPF, and DMARC to improve email delivery (Required by Gmail & Yahoo 2024)</p>
    </div>

    <!-- Critical Notice -->
    <div class="bg-red-50 border-l-4 border-red-500 p-6 mb-8">
        <div class="flex items-start">
            <svg class="w-6 h-6 text-red-500 mr-3 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
            </svg>
            <div>
                <h3 class="text-lg font-semibold text-red-800 mb-2">CRITICAL: Email Authentication Required!</h3>
                <p class="text-red-700 mb-2">As of February 2024, Gmail and Yahoo <strong>REQUIRE</strong> DKIM, SPF, and DMARC for email delivery.</p>
                <p class="text-red-700">Emails without proper authentication will be <strong>REJECTED</strong>.</p>
            </div>
        </div>
    </div>

    @foreach($domains as $domain)
        @php
            $status = $domain_status[$domain];
            $score = $status['overall_score'];
            $scoreClass = $score >= 90 ? 'text-green-600' : ($score >= 50 ? 'text-yellow-600' : 'text-red-600');
            $scoreBg = $score >= 90 ? 'bg-green-100' : ($score >= 50 ? 'bg-yellow-100' : 'bg-red-100');
        @endphp

        <div class="bg-white rounded-lg shadow-lg mb-8 overflow-hidden">
            <!-- Domain Header -->
            <div class="bg-gradient-to-r from-blue-600 to-blue-700 px-6 py-4">
                <div class="flex items-center justify-between">
                    <h2 class="text-2xl font-bold text-white">{{ $domain }}</h2>
                    <div class="text-right">
                        <div class="text-sm text-blue-100 mb-1">Deliverability Score</div>
                        <div class="text-4xl font-bold text-white">{{ $score }}%</div>
                    </div>
                </div>
            </div>

            <!-- Authentication Status -->
            <div class="p-6">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
                    <!-- DKIM Status -->
                    <div class="border rounded-lg p-6 {{ $status['dkim']['status'] === 'configured' ? 'border-green-300 bg-green-50' : 'border-red-300 bg-red-50' }}">
                        <div class="flex items-center justify-between mb-4">
                            <h3 class="text-lg font-semibold text-gray-900">DKIM</h3>
                            @if($status['dkim']['status'] === 'configured')
                                <svg class="w-8 h-8 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                            @else
                                <svg class="w-8 h-8 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                            @endif
                        </div>
                        <p class="text-sm text-gray-600 mb-4">Digital signature to verify email authenticity</p>
                        @if($status['dkim']['status'] === 'configured')
                            <div class="flex items-center text-green-700 text-sm font-medium mb-2">
                                <svg class="w-4 h-4 mr-2" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                                </svg>
                                Configured
                            </div>
                            <button onclick="viewDKIMRecord('{{ $domain }}')" class="text-blue-600 hover:text-blue-800 text-sm font-medium">
                                View Record
                            </button>
                        @else
                            <button onclick="installDKIM('{{ $domain }}')" class="w-full bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition font-medium">
                                Install DKIM
                            </button>
                        @endif
                    </div>

                    <!-- SPF Status -->
                    <div class="border rounded-lg p-6 {{ $status['spf']['status'] === 'configured' ? 'border-green-300 bg-green-50' : 'border-red-300 bg-red-50' }}">
                        <div class="flex items-center justify-between mb-4">
                            <h3 class="text-lg font-semibold text-gray-900">SPF</h3>
                            @if($status['spf']['status'] === 'configured')
                                <svg class="w-8 h-8 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                            @else
                                <svg class="w-8 h-8 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                            @endif
                        </div>
                        <p class="text-sm text-gray-600 mb-4">Authorized servers to send email</p>
                        @if($status['spf']['status'] === 'configured')
                            <div class="flex items-center text-green-700 text-sm font-medium mb-2">
                                <svg class="w-4 h-4 mr-2" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                                </svg>
                                Configured
                            </div>
                            <button onclick="viewSPFRecord('{{ $domain }}')" class="text-blue-600 hover:text-blue-800 text-sm font-medium">
                                View Record
                            </button>
                        @else
                            <button onclick="showSPFModal('{{ $domain }}')" class="w-full bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition font-medium">
                                Install SPF
                            </button>
                        @endif
                    </div>

                    <!-- DMARC Status -->
                    <div class="border rounded-lg p-6 {{ $status['dmarc']['status'] === 'configured' ? 'border-green-300 bg-green-50' : 'border-red-300 bg-red-50' }}">
                        <div class="flex items-center justify-between mb-4">
                            <h3 class="text-lg font-semibold text-gray-900">DMARC</h3>
                            @if($status['dmarc']['status'] === 'configured')
                                <svg class="w-8 h-8 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                            @else
                                <svg class="w-8 h-8 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                            @endif
                        </div>
                        <p class="text-sm text-gray-600 mb-4">Email authentication policy</p>
                        @if($status['dmarc']['status'] === 'configured')
                            <div class="flex items-center text-green-700 text-sm font-medium mb-2">
                                <svg class="w-4 h-4 mr-2" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                                </svg>
                                Configured
                            </div>
                            <button onclick="viewDMARCRecord('{{ $domain }}')" class="text-blue-600 hover:text-blue-800 text-sm font-medium">
                                View Record
                            </button>
                        @else
                            <button onclick="showDMARCModal('{{ $domain }}')" class="w-full bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition font-medium">
                                Install DMARC
                            </button>
                        @endif
                    </div>
                </div>

                <!-- What These Mean -->
                <div class="bg-blue-50 rounded-lg p-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">What These Mean:</h3>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 text-sm">
                        <div>
                            <strong class="text-blue-900">DKIM:</strong>
                            <p class="text-gray-700">Adds a digital signature to your emails proving they came from your server.</p>
                        </div>
                        <div>
                            <strong class="text-blue-900">SPF:</strong>
                            <p class="text-gray-700">Lists which servers are allowed to send email for your domain.</p>
                        </div>
                        <div>
                            <strong class="text-blue-900">DMARC:</strong>
                            <p class="text-gray-700">Tells receiving servers what to do with emails that fail authentication.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @endforeach
</div>

<!-- SPF Configuration Modal -->
<div id="spfModal" class="hidden fixed inset-0 bg-gray-900 bg-opacity-50 flex items-center justify-center z-50">
    <div class="bg-white rounded-lg shadow-xl max-w-2xl w-full mx-4 p-8">
        <h2 class="text-2xl font-bold text-gray-900 mb-6">Configure SPF Record</h2>

        <form id="spfForm" onsubmit="installSPF(event)">
            <input type="hidden" id="spf_domain" name="domain">

            <div class="mb-6">
                <label class="flex items-center mb-3">
                    <input type="checkbox" name="include_mx" checked class="mr-3 w-5 h-5">
                    <span class="text-gray-700">Include MX Records (Recommended)</span>
                </label>
                <label class="flex items-center mb-3">
                    <input type="checkbox" name="include_a" checked class="mr-3 w-5 h-5">
                    <span class="text-gray-700">Include A Record (Recommended)</span>
                </label>
                <label class="flex items-center mb-3">
                    <input type="checkbox" name="include_ip" checked class="mr-3 w-5 h-5">
                    <span class="text-gray-700">Include Server IP (Recommended)</span>
                </label>
            </div>

            <div class="mb-6">
                <label class="block text-sm font-medium text-gray-700 mb-2">Additional Includes (Optional)</label>
                <input type="text" name="additional_includes" placeholder="e.g., _spf.google.com, spf.protection.outlook.com"
                       class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                <p class="text-xs text-gray-500 mt-1">Comma-separated list of additional SPF includes</p>
            </div>

            <div class="flex space-x-4">
                <button type="submit" class="flex-1 bg-blue-600 text-white px-6 py-3 rounded-lg hover:bg-blue-700 transition font-semibold">
                    Install SPF Record
                </button>
                <button type="button" onclick="closeSPFModal()" class="flex-1 bg-gray-200 text-gray-700 px-6 py-3 rounded-lg hover:bg-gray-300 transition font-semibold">
                    Cancel
                </button>
            </div>
        </form>
    </div>
</div>

<!-- DMARC Configuration Modal -->
<div id="dmarcModal" class="hidden fixed inset-0 bg-gray-900 bg-opacity-50 flex items-center justify-center z-50">
    <div class="bg-white rounded-lg shadow-xl max-w-2xl w-full mx-4 p-8">
        <h2 class="text-2xl font-bold text-gray-900 mb-6">Configure DMARC Policy</h2>

        <form id="dmarcForm" onsubmit="installDMARC(event)">
            <input type="hidden" id="dmarc_domain" name="domain">

            <div class="mb-6">
                <label class="block text-sm font-medium text-gray-700 mb-2">Policy *</label>
                <select name="policy" required class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                    <option value="none">None (Monitor only - Recommended for testing)</option>
                    <option value="quarantine">Quarantine (Send to spam)</option>
                    <option value="reject">Reject (Block email)</option>
                </select>
            </div>

            <div class="mb-6">
                <label class="block text-sm font-medium text-gray-700 mb-2">Report Email (Optional)</label>
                <input type="email" name="rua" placeholder="reports@{{ $account->domain }}"
                       class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                <p class="text-xs text-gray-500 mt-1">Receive aggregate reports about email authentication</p>
            </div>

            <div class="mb-6 bg-yellow-50 border border-yellow-200 rounded-lg p-4">
                <p class="text-sm text-yellow-800"><strong>Tip:</strong> Start with "none" policy to monitor without affecting delivery. After verifying DKIM and SPF work correctly, upgrade to "quarantine" or "reject".</p>
            </div>

            <div class="flex space-x-4">
                <button type="submit" class="flex-1 bg-blue-600 text-white px-6 py-3 rounded-lg hover:bg-blue-700 transition font-semibold">
                    Install DMARC Policy
                </button>
                <button type="button" onclick="closeDMARCModal()" class="flex-1 bg-gray-200 text-gray-700 px-6 py-3 rounded-lg hover:bg-gray-300 transition font-semibold">
                    Cancel
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function installDKIM(domain) {
    if (!confirm(`Generate and install DKIM keys for ${domain}?\n\nThis will:\n- Generate a 2048-bit RSA key pair\n- Install the public key in DNS\n- Configure email signing`)) {
        return;
    }

    fetch('/email-deliverability/dkim/install', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
        },
        body: JSON.stringify({ domain: domain })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('DKIM installed successfully!\n\nDNS Record:\n' + data.dns_record);
            location.reload();
        } else {
            alert('Error: ' + data.error);
        }
    })
    .catch(error => {
        alert('Error installing DKIM');
        console.error(error);
    });
}

function showSPFModal(domain) {
    document.getElementById('spf_domain').value = domain;
    document.getElementById('spfModal').classList.remove('hidden');
}

function closeSPFModal() {
    document.getElementById('spfModal').classList.add('hidden');
}

function installSPF(event) {
    event.preventDefault();
    const formData = new FormData(event.target);
    const data = {
        domain: formData.get('domain'),
        include_mx: formData.get('include_mx') === 'on',
        include_a: formData.get('include_a') === 'on',
        include_ip: formData.get('include_ip') === 'on',
        additional_includes: formData.get('additional_includes')
    };

    fetch('/email-deliverability/spf/install', {
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
            alert('SPF installed successfully!\n\nRecord:\n' + data.spf_record);
            closeSPFModal();
            location.reload();
        } else {
            alert('Error: ' + data.error);
        }
    })
    .catch(error => {
        alert('Error installing SPF');
        console.error(error);
    });
}

function showDMARCModal(domain) {
    document.getElementById('dmarc_domain').value = domain;
    document.getElementById('dmarcModal').classList.remove('hidden');
}

function closeDMARCModal() {
    document.getElementById('dmarcModal').classList.add('hidden');
}

function installDMARC(event) {
    event.preventDefault();
    const formData = new FormData(event.target);
    const data = {
        domain: formData.get('domain'),
        policy: formData.get('policy'),
        rua: formData.get('rua')
    };

    fetch('/email-deliverability/dmarc/install', {
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
            alert('DMARC installed successfully!\n\nRecord:\n' + data.dmarc_record);
            closeDMARCModal();
            location.reload();
        } else {
            alert('Error: ' + data.error);
        }
    })
    .catch(error => {
        alert('Error installing DMARC');
        console.error(error);
    });
}

function viewDKIMRecord(domain) {
    // View DKIM record details
    alert('DKIM record details for ' + domain);
}

function viewSPFRecord(domain) {
    // View SPF record details
    alert('SPF record details for ' + domain);
}

function viewDMARCRecord(domain) {
    // View DMARC record details
    alert('DMARC record details for ' + domain);
}
</script>
@endsection
