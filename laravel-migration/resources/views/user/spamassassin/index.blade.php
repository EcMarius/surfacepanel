@extends('layouts.user')

@section('title', 'SpamAssassin')

@section('content')
<div class="container mx-auto px-4 py-8">
    <!-- Header -->
    <div class="mb-8">
        <h1 class="text-3xl font-bold text-gray-900">SpamAssassin</h1>
        <p class="text-gray-600 mt-2">Manage spam filtering for your email accounts</p>
    </div>

    <!-- Status Card -->
    <div class="bg-white rounded-lg shadow-lg p-6 mb-8 border-l-4 {{ $config->is_enabled ? 'border-green-500' : 'border-red-500' }}">
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-lg font-semibold text-gray-900">SpamAssassin Status</h3>
            <span class="px-3 py-1 rounded-full text-sm font-semibold {{ $config->is_enabled ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">
                {{ $config->is_enabled ? 'Enabled' : 'Disabled' }}
            </span>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 text-sm mb-4">
            <div class="flex justify-between">
                <span class="text-gray-600">Spam Threshold:</span>
                <span class="font-semibold">{{ $config->spam_threshold }}</span>
            </div>
            <div class="flex justify-between">
                <span class="text-gray-600">Action:</span>
                <span class="font-semibold capitalize">{{ $config->spam_action }}</span>
            </div>
            <div class="flex justify-between">
                <span class="text-gray-600">Bayesian Filter:</span>
                <span class="font-semibold">{{ $config->use_bayes ? 'Enabled' : 'Disabled' }}</span>
            </div>
        </div>
        <button onclick="showConfigModal()" class="w-full bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition">
            Configure SpamAssassin
        </button>
    </div>

    <!-- Statistics Cards -->
    <div class="grid grid-cols-1 lg:grid-cols-4 gap-6 mb-8">
        <div class="bg-white rounded-lg shadow p-6">
            <p class="text-sm text-gray-600">Emails Scanned (7d)</p>
            <p class="text-3xl font-bold text-gray-900 mt-1">{{ number_format($stats['total']) }}</p>
        </div>
        <div class="bg-white rounded-lg shadow p-6">
            <p class="text-sm text-gray-600">Spam Detected</p>
            <p class="text-3xl font-bold text-red-600 mt-1">{{ number_format($stats['spam']) }}</p>
            <p class="text-xs text-gray-500 mt-1">{{ $stats['spam_percentage'] }}% of total</p>
        </div>
        <div class="bg-white rounded-lg shadow p-6">
            <p class="text-sm text-gray-600">Average Spam Score</p>
            <p class="text-3xl font-bold text-orange-600 mt-1">{{ number_format($stats['average_spam_score'], 2) }}</p>
        </div>
        <div class="bg-white rounded-lg shadow p-6">
            <p class="text-sm text-gray-600">Average Ham Score</p>
            <p class="text-3xl font-bold text-green-600 mt-1">{{ number_format($stats['average_ham_score'], 2) }}</p>
        </div>
    </div>

    <!-- Action Statistics -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
        <!-- Spam Actions -->
        <div class="bg-white rounded-lg shadow p-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">Spam Actions (Last 7 Days)</h3>
            <div class="space-y-3">
                <div class="flex items-center justify-between">
                    <span class="text-gray-700">Deleted</span>
                    <span class="font-bold text-red-600">{{ number_format($stats['deleted']) }}</span>
                </div>
                <div class="flex items-center justify-between">
                    <span class="text-gray-700">Quarantined</span>
                    <span class="font-bold text-orange-600">{{ number_format($stats['quarantined']) }}</span>
                </div>
                <div class="flex items-center justify-between">
                    <span class="text-gray-700">Tagged</span>
                    <span class="font-bold text-blue-600">{{ number_format($stats['tagged']) }}</span>
                </div>
            </div>
        </div>

        <!-- Top Spam Senders -->
        <div class="bg-white rounded-lg shadow p-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">Top Spam Senders</h3>
            @if(count($topSpamSenders) > 0)
            <div class="space-y-2">
                @foreach($topSpamSenders as $sender)
                <div class="flex items-center justify-between p-2 hover:bg-gray-50 rounded">
                    <span class="text-sm text-gray-700 truncate mr-2">{{ $sender['email'] }}</span>
                    <div class="flex items-center gap-2">
                        <span class="text-sm font-semibold text-red-600">{{ $sender['count'] }}</span>
                        <button onclick="addToBlacklist('{{ $sender['email'] }}')"
                                class="text-xs bg-red-600 text-white px-2 py-1 rounded hover:bg-red-700">
                            Block
                        </button>
                    </div>
                </div>
                @endforeach
            </div>
            @else
            <p class="text-center text-gray-500 py-4">No spam detected</p>
            @endif
        </div>
    </div>

    <!-- Whitelist/Blacklist Overview -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
        <div class="bg-white rounded-lg shadow p-6">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-semibold text-gray-900">Whitelist</h3>
                <span class="text-sm font-semibold text-green-600">{{ $whitelistCount }} entries</span>
            </div>
            <p class="text-gray-600 text-sm mb-4">Trusted senders that will never be marked as spam</p>
            <a href="{{ route('user.spamassassin.lists', ['type' => 'whitelist']) }}"
               class="block w-full bg-green-600 text-white px-4 py-2 rounded-lg hover:bg-green-700 transition text-center">
                Manage Whitelist
            </a>
        </div>

        <div class="bg-white rounded-lg shadow p-6">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-semibold text-gray-900">Blacklist</h3>
                <span class="text-sm font-semibold text-red-600">{{ $blacklistCount }} entries</span>
            </div>
            <p class="text-gray-600 text-sm mb-4">Blocked senders that will always be marked as spam</p>
            <a href="{{ route('user.spamassassin.lists', ['type' => 'blacklist']) }}"
               class="block w-full bg-red-600 text-white px-4 py-2 rounded-lg hover:bg-red-700 transition text-center">
                Manage Blacklist
            </a>
        </div>
    </div>

    <!-- Recent Spam -->
    <div class="bg-white rounded-lg shadow p-6 mb-8">
        <div class="flex justify-between items-center mb-4">
            <h3 class="text-lg font-semibold text-gray-900">Recent Spam Emails</h3>
            <a href="{{ route('user.spamassassin.logs') }}" class="text-blue-600 hover:text-blue-800 text-sm font-medium">
                View All →
            </a>
        </div>
        @if($recentSpam->count() > 0)
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Time</th>
                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">From</th>
                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Subject</th>
                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Score</th>
                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Action</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    @foreach($recentSpam as $spam)
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-2 text-sm text-gray-600">{{ $spam->created_at->diffForHumans() }}</td>
                        <td class="px-4 py-2 text-sm truncate max-w-xs">{{ $spam->email_from ?? 'Unknown' }}</td>
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
        <p class="text-center text-gray-500 py-8">No spam detected recently</p>
        @endif
    </div>

    <!-- Quick Actions -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
        <a href="{{ route('user.spamassassin.training') }}" class="bg-purple-600 text-white px-6 py-3 rounded-lg hover:bg-purple-700 transition text-center">
            <div class="flex items-center justify-center">
                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/>
                </svg>
                Spam Training
            </div>
        </a>

        <a href="{{ route('user.spamassassin.logs') }}" class="bg-gray-600 text-white px-6 py-3 rounded-lg hover:bg-gray-700 transition text-center">
            <div class="flex items-center justify-center">
                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                </svg>
                View All Logs
            </div>
        </a>

        <button onclick="showConfigModal()" class="bg-blue-600 text-white px-6 py-3 rounded-lg hover:bg-blue-700 transition">
            <div class="flex items-center justify-center">
                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/>
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                </svg>
                Settings
            </div>
        </button>
    </div>
</div>

<!-- Configuration Modal -->
<div id="configModal" class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
    <div class="bg-white rounded-lg shadow-xl max-w-2xl w-full mx-4 max-h-screen overflow-y-auto">
        <div class="px-6 py-4 border-b border-gray-200">
            <h3 class="text-xl font-semibold text-gray-900">SpamAssassin Configuration</h3>
        </div>
        <div class="p-6">
            <form id="spamConfigForm">
                <div class="space-y-4">
                    <!-- Enable SpamAssassin -->
                    <div>
                        <label class="flex items-center">
                            <input type="checkbox" id="is_enabled" {{ $config->is_enabled ? 'checked' : '' }} class="rounded border-gray-300">
                            <span class="ml-2 text-sm font-medium text-gray-700">Enable SpamAssassin</span>
                        </label>
                    </div>

                    <!-- Spam Threshold -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            Spam Threshold: <span id="thresholdLabel" class="font-semibold">{{ $config->spam_threshold }}</span>
                        </label>
                        <input type="range" id="spam_threshold" min="0" max="10" step="0.5" value="{{ $config->spam_threshold }}"
                               class="w-full" onchange="updateThresholdLabel(this.value)">
                        <div class="flex justify-between text-xs text-gray-500 mt-1">
                            <span>0 - Very Strict</span>
                            <span>5 - Balanced</span>
                            <span>10 - Very Lenient</span>
                        </div>
                        <p class="text-xs text-gray-500 mt-2">Lower values = more aggressive filtering</p>
                    </div>

                    <!-- Spam Action -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">When spam is detected:</label>
                        <select id="spam_action" class="w-full border border-gray-300 rounded-lg px-3 py-2">
                            <option value="tag" {{ $config->spam_action === 'tag' ? 'selected' : '' }}>Tag subject line</option>
                            <option value="quarantine" {{ $config->spam_action === 'quarantine' ? 'selected' : '' }}>Move to spam folder</option>
                            <option value="delete" {{ $config->spam_action === 'delete' ? 'selected' : '' }}>Delete immediately</option>
                        </select>
                    </div>

                    <!-- Spam Folder (shown when quarantine is selected) -->
                    <div id="spamFolderDiv" style="display: {{ $config->spam_action === 'quarantine' ? 'block' : 'none' }}">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Spam Folder Name:</label>
                        <input type="text" id="spam_folder" value="{{ $config->spam_folder }}"
                               class="w-full border border-gray-300 rounded-lg px-3 py-2">
                    </div>

                    <!-- Subject Tag (shown when tag is selected) -->
                    <div id="subjectTagDiv" style="display: {{ $config->spam_action === 'tag' ? 'block' : 'none' }}">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Subject Line Tag:</label>
                        <input type="text" id="spam_subject_tag" value="{{ $config->spam_subject_tag }}"
                               class="w-full border border-gray-300 rounded-lg px-3 py-2">
                    </div>

                    <!-- Bayesian Filter -->
                    <div>
                        <label class="flex items-center">
                            <input type="checkbox" id="use_bayes" {{ $config->use_bayes ? 'checked' : '' }} class="rounded border-gray-300">
                            <span class="ml-2 text-sm font-medium text-gray-700">Use Bayesian Filtering</span>
                        </label>
                        <p class="ml-6 text-xs text-gray-500 mt-1">Learns from your spam/ham patterns</p>
                    </div>

                    <!-- Auto-Learn -->
                    <div>
                        <label class="flex items-center">
                            <input type="checkbox" id="auto_learn" {{ $config->auto_learn ? 'checked' : '' }} class="rounded border-gray-300">
                            <span class="ml-2 text-sm font-medium text-gray-700">Auto-Learning</span>
                        </label>
                        <p class="ml-6 text-xs text-gray-500 mt-1">Automatically learn from obvious spam/ham</p>
                    </div>

                    <!-- Rewrite Header -->
                    <div>
                        <label class="flex items-center">
                            <input type="checkbox" id="rewrite_header" {{ $config->rewrite_header ? 'checked' : '' }} class="rounded border-gray-300">
                            <span class="ml-2 text-sm font-medium text-gray-700">Rewrite Subject Header</span>
                        </label>
                        <p class="ml-6 text-xs text-gray-500 mt-1">Add spam tag to subject when spam is detected</p>
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

function updateThresholdLabel(value) {
    document.getElementById('thresholdLabel').textContent = value;
}

document.getElementById('spam_action').addEventListener('change', function() {
    document.getElementById('spamFolderDiv').style.display = this.value === 'quarantine' ? 'block' : 'none';
    document.getElementById('subjectTagDiv').style.display = this.value === 'tag' ? 'block' : 'none';
});

function saveConfig() {
    const data = {
        is_enabled: document.getElementById('is_enabled').checked,
        spam_threshold: parseFloat(document.getElementById('spam_threshold').value),
        spam_action: document.getElementById('spam_action').value,
        spam_folder: document.getElementById('spam_folder').value,
        spam_subject_tag: document.getElementById('spam_subject_tag').value,
        use_bayes: document.getElementById('use_bayes').checked,
        auto_learn: document.getElementById('auto_learn').checked,
        rewrite_header: document.getElementById('rewrite_header').checked
    };

    fetch('{{ route('user.spamassassin.config') }}', {
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
            alert('Configuration saved successfully');
            location.reload();
        } else {
            alert('Error: ' + data.message);
        }
    });
}

function addToBlacklist(email) {
    if (!confirm(`Add ${email} to blacklist?`)) return;

    fetch('{{ route('user.spamassassin.lists.add') }}', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
        },
        body: JSON.stringify({
            list_type: 'blacklist',
            entry_type: 'email',
            value: email,
            description: 'Top spam sender'
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Email added to blacklist');
            location.reload();
        }
    });
}
</script>
@endsection
