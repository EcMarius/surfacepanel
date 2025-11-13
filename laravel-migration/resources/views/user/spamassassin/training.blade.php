@extends('layouts.user')

@section('title', 'SpamAssassin Training')

@section('content')
<div class="container mx-auto px-4 py-8">
    <!-- Header -->
    <div class="mb-8">
        <h1 class="text-3xl font-bold text-gray-900">SpamAssassin Training</h1>
        <p class="text-gray-600 mt-2">Train SpamAssassin to better recognize spam and legitimate emails</p>
    </div>

    <!-- Training Overview -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
        <div class="bg-white rounded-lg shadow-lg p-6 border-l-4 border-red-500">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-600">Spam Emails Trained</p>
                    <p class="text-4xl font-bold text-red-600 mt-2">{{ number_format($spamTrained) }}</p>
                </div>
                <div class="bg-red-100 p-4 rounded-full">
                    <svg class="w-10 h-10 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                    </svg>
                </div>
            </div>
            <p class="text-xs text-gray-500 mt-3">Emails you've marked as spam help improve detection</p>
        </div>

        <div class="bg-white rounded-lg shadow-lg p-6 border-l-4 border-green-500">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-600">Ham Emails Trained</p>
                    <p class="text-4xl font-bold text-green-600 mt-2">{{ number_format($hamTrained) }}</p>
                </div>
                <div class="bg-green-100 p-4 rounded-full">
                    <svg class="w-10 h-10 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                </div>
            </div>
            <p class="text-xs text-gray-500 mt-3">Legitimate emails marked as ham reduce false positives</p>
        </div>
    </div>

    <!-- What is Training? -->
    <div class="bg-blue-50 border-l-4 border-blue-500 p-6 mb-8 rounded-lg">
        <h3 class="text-lg font-semibold text-blue-900 mb-2">What is SpamAssassin Training?</h3>
        <p class="text-blue-800 mb-3">
            SpamAssassin uses Bayesian filtering, which learns from examples you provide. By marking emails as spam or ham (legitimate email),
            you help SpamAssassin become more accurate at detecting spam specific to your needs.
        </p>
        <ul class="list-disc list-inside text-blue-800 space-y-1 text-sm">
            <li><strong>Spam Training:</strong> Mark unwanted emails as spam to help SpamAssassin recognize similar patterns</li>
            <li><strong>Ham Training:</strong> Mark legitimate emails that were incorrectly flagged as spam</li>
            <li><strong>Auto-Learning:</strong> SpamAssassin can also learn automatically from obvious spam and ham</li>
        </ul>
    </div>

    <!-- Training Actions -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
        <!-- Mark as Spam -->
        <div class="bg-white rounded-lg shadow p-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">Mark Email as Spam</h3>
            <p class="text-gray-600 text-sm mb-4">
                If you received an email that should have been marked as spam, you can train SpamAssassin to recognize it.
            </p>
            <form onsubmit="trainSpam(event)" class="space-y-3">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">From Address *</label>
                    <input type="email" id="spam_from" required
                           class="w-full border border-gray-300 rounded-lg px-3 py-2"
                           placeholder="spammer@example.com">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Subject</label>
                    <input type="text" id="spam_subject"
                           class="w-full border border-gray-300 rounded-lg px-3 py-2"
                           placeholder="Email subject">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Message ID (optional)</label>
                    <input type="text" id="spam_message_id"
                           class="w-full border border-gray-300 rounded-lg px-3 py-2"
                           placeholder="<message@example.com>">
                </div>
                <button type="submit" class="w-full bg-red-600 text-white px-4 py-2 rounded-lg hover:bg-red-700 transition">
                    Train as Spam
                </button>
            </form>
        </div>

        <!-- Mark as Ham -->
        <div class="bg-white rounded-lg shadow p-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">Mark Email as Ham (Legitimate)</h3>
            <p class="text-gray-600 text-sm mb-4">
                If a legitimate email was incorrectly marked as spam, train SpamAssassin to recognize it as ham.
            </p>
            <form onsubmit="trainHam(event)" class="space-y-3">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">From Address *</label>
                    <input type="email" id="ham_from" required
                           class="w-full border border-gray-300 rounded-lg px-3 py-2"
                           placeholder="legitimate@example.com">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Subject</label>
                    <input type="text" id="ham_subject"
                           class="w-full border border-gray-300 rounded-lg px-3 py-2"
                           placeholder="Email subject">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Message ID (optional)</label>
                    <input type="text" id="ham_message_id"
                           class="w-full border border-gray-300 rounded-lg px-3 py-2"
                           placeholder="<message@example.com>">
                </div>
                <button type="submit" class="w-full bg-green-600 text-white px-4 py-2 rounded-lg hover:bg-green-700 transition">
                    Train as Ham
                </button>
            </form>
        </div>
    </div>

    <!-- Pending Training Items -->
    @if($pendingTraining->count() > 0)
    <div class="bg-white rounded-lg shadow p-6 mb-8">
        <h3 class="text-lg font-semibold text-gray-900 mb-4">Pending Training Items</h3>
        <p class="text-gray-600 text-sm mb-4">These items are queued for training and will be processed shortly.</p>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Time</th>
                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Type</th>
                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">From</th>
                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Subject</th>
                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    @foreach($pendingTraining as $item)
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-2 text-sm text-gray-600">{{ \Carbon\Carbon::parse($item->created_at)->diffForHumans() }}</td>
                        <td class="px-4 py-2">
                            <span class="px-2 py-1 text-xs rounded {{ $item->type === 'spam' ? 'bg-red-100 text-red-800' : 'bg-green-100 text-green-800' }}">
                                {{ ucfirst($item->type) }}
                            </span>
                        </td>
                        <td class="px-4 py-2 text-sm truncate max-w-xs">{{ $item->from_address ?? 'N/A' }}</td>
                        <td class="px-4 py-2 text-sm truncate max-w-xs">{{ $item->subject ?? 'No subject' }}</td>
                        <td class="px-4 py-2 text-sm">
                            <span class="px-2 py-1 text-xs rounded bg-yellow-100 text-yellow-800">
                                Pending
                            </span>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
    @endif

    <!-- Training Tips -->
    <div class="bg-white rounded-lg shadow p-6">
        <h3 class="text-lg font-semibold text-gray-900 mb-4">Training Tips</h3>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div class="flex items-start">
                <div class="bg-blue-100 p-2 rounded-full mr-3">
                    <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                </div>
                <div>
                    <h4 class="font-semibold text-gray-900 mb-1">Train Regularly</h4>
                    <p class="text-sm text-gray-600">The more examples you provide, the more accurate SpamAssassin becomes</p>
                </div>
            </div>

            <div class="flex items-start">
                <div class="bg-green-100 p-2 rounded-full mr-3">
                    <svg class="w-5 h-5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                </div>
                <div>
                    <h4 class="font-semibold text-gray-900 mb-1">Balance Training</h4>
                    <p class="text-sm text-gray-600">Train both spam and ham for best results</p>
                </div>
            </div>

            <div class="flex items-start">
                <div class="bg-orange-100 p-2 rounded-full mr-3">
                    <svg class="w-5 h-5 text-orange-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                    </svg>
                </div>
                <div>
                    <h4 class="font-semibold text-gray-900 mb-1">Review False Positives</h4>
                    <p class="text-sm text-gray-600">Check your spam folder regularly for legitimate emails</p>
                </div>
            </div>

            <div class="flex items-start">
                <div class="bg-purple-100 p-2 rounded-full mr-3">
                    <svg class="w-5 h-5 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                </div>
                <div>
                    <h4 class="font-semibold text-gray-900 mb-1">Be Patient</h4>
                    <p class="text-sm text-gray-600">It takes time for the Bayesian filter to learn your patterns</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Back Button -->
    <div class="mt-8">
        <a href="{{ route('user.spamassassin.index') }}" class="inline-flex items-center text-blue-600 hover:text-blue-800">
            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
            </svg>
            Back to SpamAssassin Dashboard
        </a>
    </div>
</div>

<script>
function trainSpam(event) {
    event.preventDefault();

    const data = {
        type: 'spam',
        from_address: document.getElementById('spam_from').value,
        subject: document.getElementById('spam_subject').value,
        message_id: document.getElementById('spam_message_id').value || 'spam-' + Date.now()
    };

    fetch('{{ route('user.spamassassin.train') }}', {
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
            alert('Email marked as spam and training scheduled');
            location.reload();
        } else {
            alert('Error: ' + data.message);
        }
    });
}

function trainHam(event) {
    event.preventDefault();

    const data = {
        type: 'ham',
        from_address: document.getElementById('ham_from').value,
        subject: document.getElementById('ham_subject').value,
        message_id: document.getElementById('ham_message_id').value || 'ham-' + Date.now()
    };

    fetch('{{ route('user.spamassassin.train') }}', {
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
            alert('Email marked as ham and training scheduled');
            location.reload();
        } else {
            alert('Error: ' + data.message);
        }
    });
}
</script>
@endsection
