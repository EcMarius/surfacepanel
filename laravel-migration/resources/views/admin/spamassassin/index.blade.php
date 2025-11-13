@extends('layouts.admin')

@section('title', 'SpamAssassin - WHM')

@section('content')
<div class="container mx-auto px-4 py-8">
    <!-- Header -->
    <div class="mb-8">
        <h1 class="text-3xl font-bold text-gray-900">SpamAssassin</h1>
        <p class="text-gray-600 mt-2">Email spam filtering and protection</p>
    </div>

    <!-- Statistics Cards -->
    <div class="grid grid-cols-1 lg:grid-cols-4 gap-6 mb-8">
        <!-- Accounts Card -->
        <div class="bg-white rounded-lg shadow-lg p-6 border-l-4 border-blue-500">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-600">Enabled Accounts</p>
                    <p class="text-3xl font-bold text-gray-900 mt-1">{{ $enabledAccounts }}</p>
                    <p class="text-xs text-gray-500 mt-1">of {{ $totalAccounts }} total</p>
                </div>
                <div class="bg-blue-100 p-3 rounded-full">
                    <svg class="w-8 h-8 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                </div>
            </div>
        </div>

        <!-- Emails Scanned Card -->
        <div class="bg-white rounded-lg shadow-lg p-6 border-l-4 border-green-500">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-600">Emails Scanned (7d)</p>
                    <p class="text-3xl font-bold text-gray-900 mt-1">{{ number_format($stats->total_emails ?? 0) }}</p>
                </div>
                <div class="bg-green-100 p-3 rounded-full">
                    <svg class="w-8 h-8 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                    </svg>
                </div>
            </div>
        </div>

        <!-- Spam Detected Card -->
        <div class="bg-white rounded-lg shadow-lg p-6 border-l-4 border-red-500">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-600">Spam Detected (7d)</p>
                    <p class="text-3xl font-bold text-red-600 mt-1">{{ number_format($stats->spam_count ?? 0) }}</p>
                    <p class="text-xs text-gray-500 mt-1">
                        {{ $stats->total_emails > 0 ? round(($stats->spam_count / $stats->total_emails) * 100, 1) : 0 }}% of total
                    </p>
                </div>
                <div class="bg-red-100 p-3 rounded-full">
                    <svg class="w-8 h-8 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                    </svg>
                </div>
            </div>
        </div>

        <!-- Average Spam Score Card -->
        <div class="bg-white rounded-lg shadow-lg p-6 border-l-4 border-orange-500">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-600">Avg Spam Score</p>
                    <p class="text-3xl font-bold text-orange-600 mt-1">{{ number_format($stats->avg_spam_score ?? 0, 2) }}</p>
                    <p class="text-xs text-gray-500 mt-1">Ham: {{ number_format($stats->avg_ham_score ?? 0, 2) }}</p>
                </div>
                <div class="bg-orange-100 p-3 rounded-full">
                    <svg class="w-8 h-8 text-orange-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                    </svg>
                </div>
            </div>
        </div>
    </div>

    <!-- Actions Taken Card -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
        <!-- Spam Actions -->
        <div class="bg-white rounded-lg shadow p-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">Spam Actions Taken (Last 7 Days)</h3>
            <div class="space-y-3">
                <div class="flex items-center justify-between p-3 bg-red-50 rounded-lg">
                    <div class="flex items-center">
                        <svg class="w-5 h-5 text-red-600 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                        </svg>
                        <span class="font-medium text-gray-900">Deleted</span>
                    </div>
                    <span class="text-sm font-semibold text-red-600">{{ number_format($actionStats['deleted'] ?? 0) }}</span>
                </div>
                <div class="flex items-center justify-between p-3 bg-orange-50 rounded-lg">
                    <div class="flex items-center">
                        <svg class="w-5 h-5 text-orange-600 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4"/>
                        </svg>
                        <span class="font-medium text-gray-900">Quarantined</span>
                    </div>
                    <span class="text-sm font-semibold text-orange-600">{{ number_format($actionStats['quarantined'] ?? 0) }}</span>
                </div>
                <div class="flex items-center justify-between p-3 bg-blue-50 rounded-lg">
                    <div class="flex items-center">
                        <svg class="w-5 h-5 text-blue-600 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"/>
                        </svg>
                        <span class="font-medium text-gray-900">Tagged</span>
                    </div>
                    <span class="text-sm font-semibold text-blue-600">{{ number_format($actionStats['tagged'] ?? 0) }}</span>
                </div>
            </div>
        </div>

        <!-- Top Spam Accounts -->
        <div class="bg-white rounded-lg shadow p-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">Top Spam Accounts</h3>
            @if($topSpamAccounts->count() > 0)
            <div class="space-y-2">
                @foreach($topSpamAccounts as $spamAccount)
                <div class="flex items-center justify-between p-2 hover:bg-gray-50 rounded">
                    <span class="font-mono text-sm text-gray-700">{{ $spamAccount->account->username ?? 'Unknown' }}</span>
                    <div class="flex items-center gap-2">
                        <span class="text-sm font-semibold text-red-600">{{ number_format($spamAccount->spam_count) }} spam</span>
                        <a href="{{ route('admin.spamassassin.logs', ['account_id' => $spamAccount->account_id]) }}"
                           class="text-xs bg-blue-600 text-white px-2 py-1 rounded hover:bg-blue-700">
                            View
                        </a>
                    </div>
                </div>
                @endforeach
            </div>
            @else
            <p class="text-center text-gray-500 py-4">No spam detected</p>
            @endif
        </div>
    </div>

    <!-- Recent Spam Samples -->
    <div class="bg-white rounded-lg shadow p-6 mb-8">
        <div class="flex justify-between items-center mb-4">
            <h3 class="text-lg font-semibold text-gray-900">Recent Spam Samples</h3>
            <a href="{{ route('admin.spamassassin.logs') }}" class="text-blue-600 hover:text-blue-800 text-sm font-medium">
                View All Logs →
            </a>
        </div>
        @if($recentSpam->count() > 0)
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Time</th>
                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Account</th>
                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">From</th>
                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">To</th>
                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Subject</th>
                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Score</th>
                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Action</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    @foreach($recentSpam as $spam)
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-2 text-sm text-gray-600">{{ $spam->created_at->diffForHumans() }}</td>
                        <td class="px-4 py-2 text-sm font-mono">{{ $spam->account->username ?? 'N/A' }}</td>
                        <td class="px-4 py-2 text-sm truncate max-w-xs">{{ $spam->email_from ?? 'Unknown' }}</td>
                        <td class="px-4 py-2 text-sm truncate max-w-xs">{{ $spam->email_to }}</td>
                        <td class="px-4 py-2 text-sm truncate max-w-xs">{{ $spam->subject ?? 'No subject' }}</td>
                        <td class="px-4 py-2">
                            <span class="px-2 py-1 text-xs rounded {{ $spam->getScoreColor() }} text-white">
                                {{ number_format($spam->spam_score, 2) }}
                            </span>
                        </td>
                        <td class="px-4 py-2 text-sm capitalize">{{ $spam->action_taken }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @else
        <p class="text-center text-gray-500 py-8">No spam detected</p>
        @endif
    </div>

    <!-- Action Buttons -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
        <button onclick="updateRules()" class="bg-blue-600 text-white px-6 py-3 rounded-lg hover:bg-blue-700 transition">
            <div class="flex items-center justify-center">
                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                </svg>
                Update SpamAssassin Rules
            </div>
        </button>

        <a href="{{ route('admin.spamassassin.logs') }}" class="bg-gray-600 text-white px-6 py-3 rounded-lg hover:bg-gray-700 transition text-center">
            <div class="flex items-center justify-center">
                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                </svg>
                View All Logs
            </div>
        </a>

        <button onclick="showStatistics()" class="bg-green-600 text-white px-6 py-3 rounded-lg hover:bg-green-700 transition">
            <div class="flex items-center justify-center">
                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                </svg>
                View Statistics
            </div>
        </button>
    </div>
</div>

<script>
function updateRules() {
    if (!confirm('Update SpamAssassin rules? This will fetch the latest spam detection rules from the internet.')) return;

    fetch('{{ route('admin.spamassassin.update-rules') }}', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('SpamAssassin rules updated successfully');
            location.reload();
        } else {
            alert('Error: ' + data.message);
        }
    });
}

function showStatistics() {
    window.location.href = '{{ route('admin.spamassassin.statistics') }}';
}
</script>
@endsection
