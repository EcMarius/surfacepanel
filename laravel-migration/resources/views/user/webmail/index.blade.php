@extends('layouts.user')

@section('title', 'Webmail Access')

@section('content')
<div class="container mx-auto px-4 py-8">
    <!-- Header -->
    <div class="mb-8">
        <h1 class="text-3xl font-bold text-gray-900">Webmail Access</h1>
        <p class="text-gray-600 mt-2">Access your email accounts through webmail</p>
    </div>

    <!-- Webmail Info Card -->
    <div class="bg-gradient-to-r from-blue-500 to-blue-600 rounded-lg shadow-lg p-8 mb-8 text-white">
        <div class="flex items-center justify-between">
            <div class="flex items-center">
                <div class="bg-white bg-opacity-20 rounded-full p-4 mr-6">
                    <svg class="w-12 h-12" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                    </svg>
                </div>
                <div>
                    <h2 class="text-2xl font-bold mb-2">{{ $config->product_name }}</h2>
                    <p class="text-blue-100">Access your emails from anywhere, anytime</p>
                </div>
            </div>
            <div class="text-right">
                <div class="text-4xl font-bold">{{ count($emailAccounts) }}</div>
                <div class="text-blue-100">Email Account{{ count($emailAccounts) !== 1 ? 's' : '' }}</div>
            </div>
        </div>
    </div>

    @if(count($emailAccounts) > 0)
        <!-- Email Accounts List -->
        <div class="bg-white rounded-lg shadow-lg p-6 mb-8">
            <h3 class="text-xl font-semibold text-gray-900 mb-6">Your Email Accounts</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                @foreach($emailAccounts as $email)
                    <div class="border border-gray-200 rounded-lg p-4 hover:shadow-md transition">
                        <div class="flex items-start justify-between mb-3">
                            <div class="flex items-center">
                                <div class="w-10 h-10 rounded-full bg-blue-100 flex items-center justify-center mr-3">
                                    <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                                    </svg>
                                </div>
                                <div>
                                    <h4 class="font-semibold text-gray-900 text-sm break-all">{{ $email->email }}</h4>
                                    <p class="text-xs text-gray-500">{{ number_format($email->used) }} / {{ number_format($email->quota) }} MB</p>
                                </div>
                            </div>
                        </div>
                        <div class="w-full bg-gray-200 rounded-full h-1.5 mb-3">
                            <div class="bg-blue-600 h-1.5 rounded-full" style="width: {{ $email->quota > 0 ? min(($email->used / $email->quota) * 100, 100) : 0 }}%"></div>
                        </div>
                        <button onclick="openWebmail('{{ $email->email }}')" class="w-full bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition text-sm font-medium flex items-center justify-center">
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                            </svg>
                            Open Webmail
                        </button>
                    </div>
                @endforeach
            </div>
        </div>

        <!-- Quick Access -->
        <div class="bg-white rounded-lg shadow p-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">Quick Actions</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <a href="{{ route('user.email.index') }}" class="flex items-center p-4 bg-gray-50 rounded-lg hover:bg-gray-100 transition">
                    <svg class="w-8 h-8 text-blue-600 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/>
                    </svg>
                    <div>
                        <h4 class="font-semibold text-gray-900">Manage Email Accounts</h4>
                        <p class="text-sm text-gray-600">Create, edit, or delete email accounts</p>
                    </div>
                </a>
                <a href="{{ route('user.email-deliverability.index') }}" class="flex items-center p-4 bg-gray-50 rounded-lg hover:bg-gray-100 transition">
                    <svg class="w-8 h-8 text-green-600 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    <div>
                        <h4 class="font-semibold text-gray-900">Email Deliverability</h4>
                        <p class="text-sm text-gray-600">Configure DKIM, SPF, and DMARC</p>
                    </div>
                </a>
            </div>
        </div>
    @else
        <!-- No Email Accounts -->
        <div class="bg-yellow-50 border-l-4 border-yellow-400 p-6 rounded-lg">
            <div class="flex items-center mb-4">
                <svg class="w-6 h-6 text-yellow-600 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                </svg>
                <h3 class="text-lg font-semibold text-yellow-800">No Email Accounts Found</h3>
            </div>
            <p class="text-yellow-700 mb-4">You don't have any email accounts yet. Create one to access webmail.</p>
            <a href="{{ route('user.email.index') }}" class="inline-flex items-center bg-yellow-600 text-white px-6 py-2 rounded-lg hover:bg-yellow-700 transition">
                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/>
                </svg>
                Create Email Account
            </a>
        </div>
    @endif

    <!-- Features Info -->
    <div class="mt-8 grid grid-cols-1 md:grid-cols-3 gap-6">
        <div class="bg-white rounded-lg shadow p-6">
            <div class="flex items-center mb-4">
                <div class="bg-blue-100 rounded-full p-3 mr-4">
                    <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                    </svg>
                </div>
                <h4 class="font-semibold text-gray-900">Secure Access</h4>
            </div>
            <p class="text-sm text-gray-600">Your emails are protected with industry-standard encryption and security measures.</p>
        </div>

        <div class="bg-white rounded-lg shadow p-6">
            <div class="flex items-center mb-4">
                <div class="bg-green-100 rounded-full p-3 mr-4">
                    <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                    </svg>
                </div>
                <h4 class="font-semibold text-gray-900">Fast & Reliable</h4>
            </div>
            <p class="text-sm text-gray-600">Lightning-fast webmail interface with 99.9% uptime guarantee.</p>
        </div>

        <div class="bg-white rounded-lg shadow p-6">
            <div class="flex items-center mb-4">
                <div class="bg-purple-100 rounded-full p-3 mr-4">
                    <svg class="w-6 h-6 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 18h.01M8 21h8a2 2 0 002-2V5a2 2 0 00-2-2H8a2 2 0 00-2 2v14a2 2 0 002 2z"/>
                    </svg>
                </div>
                <h4 class="font-semibold text-gray-900">Mobile Friendly</h4>
            </div>
            <p class="text-sm text-gray-600">Access your emails from any device - desktop, tablet, or smartphone.</p>
        </div>
    </div>
</div>

<script>
function openWebmail(email) {
    // Generate SSO token and open webmail
    fetch('{{ route("user.webmail.login") }}', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': '{{ csrf_token() }}'
        },
        body: JSON.stringify({ email: email })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Open webmail in new tab
            window.open(data.url, '_blank');
        } else {
            alert('Error: ' + data.message);
        }
    })
    .catch(error => {
        alert('Failed to open webmail: ' + error.message);
    });
}
</script>
@endsection
