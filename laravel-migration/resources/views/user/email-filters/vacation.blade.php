@extends('layouts.user')

@section('title', 'Vacation Auto-Responder')

@section('content')
<div class="container mx-auto px-4 py-8">
    <div class="mb-8">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-3xl font-bold text-gray-900 mb-2">Vacation Auto-Responder</h1>
                <p class="text-gray-600">Set up automatic out-of-office replies for your email</p>
            </div>
            <a href="{{ route('user.email-filters.index', ['email' => $selectedEmail]) }}" class="text-blue-600 hover:text-blue-800 transition font-medium">
                Back to Filters
            </a>
        </div>
    </div>

    @if(session('success'))
        <div class="bg-green-50 border-l-4 border-green-500 p-4 mb-6">
            <div class="flex items-center">
                <svg class="w-5 h-5 text-green-500 mr-3" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                </svg>
                <p class="text-green-700 font-medium">{{ session('success') }}</p>
            </div>
        </div>
    @endif

    @if(session('error'))
        <div class="bg-red-50 border-l-4 border-red-500 p-4 mb-6">
            <div class="flex items-center">
                <svg class="w-5 h-5 text-red-500 mr-3" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
                </svg>
                <p class="text-red-700 font-medium">{{ session('error') }}</p>
            </div>
        </div>
    @endif

    <!-- Email Account Selector -->
    <div class="bg-white rounded-lg shadow-lg mb-6 p-6">
        <label class="block text-sm font-medium text-gray-700 mb-2">Email Account</label>
        <select id="emailSelect" onchange="selectEmail(this.value)" class="w-full md:w-1/2 px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
            <option value="">Select email account...</option>
            @foreach($emailAccounts as $emailAccount)
                <option value="{{ $emailAccount }}" {{ $selectedEmail === $emailAccount ? 'selected' : '' }}>
                    {{ $emailAccount }}
                </option>
            @endforeach
        </select>
    </div>

    @if($selectedEmail)
        <!-- Current Status -->
        @if($vacationFilter)
            <div class="bg-white rounded-lg shadow-lg mb-6 p-6">
                <div class="flex items-center justify-between mb-4">
                    <div class="flex items-center">
                        <div class="p-3 rounded-full {{ $vacationFilter->is_active ? 'bg-green-100' : 'bg-gray-100' }} mr-4">
                            <svg class="w-6 h-6 {{ $vacationFilter->is_active ? 'text-green-600' : 'text-gray-600' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                        </div>
                        <div>
                            <h3 class="text-lg font-semibold text-gray-900">
                                Vacation Responder is {{ $vacationFilter->is_active ? 'Active' : 'Inactive' }}
                            </h3>
                            <p class="text-sm text-gray-600">
                                {{ $vacationFilter->is_active ? 'Your out-of-office message is being sent automatically' : 'Auto-replies are currently disabled' }}
                            </p>
                        </div>
                    </div>
                    @if($vacationFilter->is_active)
                        <form method="POST" action="{{ route('user.email-filters.vacation.disable') }}">
                            @csrf
                            <input type="hidden" name="email" value="{{ $selectedEmail }}">
                            <button type="submit" class="px-6 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 transition font-medium">
                                Disable
                            </button>
                        </form>
                    @endif
                </div>

                @if($vacationFilter->is_active)
                    @php
                        $action = $vacationFilter->actions->firstWhere('action_type', 'vacation');
                    @endphp
                    @if($action)
                        <div class="border-t pt-4">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div>
                                    <p class="text-sm font-medium text-gray-700 mb-1">Subject:</p>
                                    <p class="text-gray-900">{{ $action->vacation_subject }}</p>
                                </div>
                                <div>
                                    <p class="text-sm font-medium text-gray-700 mb-1">Response Frequency:</p>
                                    <p class="text-gray-900">Once every {{ $action->vacation_days }} day(s)</p>
                                </div>
                            </div>
                            <div class="mt-4">
                                <p class="text-sm font-medium text-gray-700 mb-1">Message:</p>
                                <div class="bg-gray-50 p-4 rounded-lg border border-gray-200">
                                    <p class="text-gray-900 whitespace-pre-wrap">{{ $action->vacation_message }}</p>
                                </div>
                            </div>
                        </div>
                    @endif
                @endif
            </div>
        @endif

        <!-- Setup/Edit Form -->
        <div class="bg-white rounded-lg shadow-lg p-6">
            <h2 class="text-xl font-semibold text-gray-900 mb-6">
                {{ $vacationFilter ? 'Edit' : 'Setup' }} Vacation Responder
            </h2>

            <form method="POST" action="{{ route('user.email-filters.vacation.setup') }}">
                @csrf

                <input type="hidden" name="email" value="{{ $selectedEmail }}">

                <div class="space-y-6">
                    <div>
                        <label for="subject" class="block text-sm font-medium text-gray-700 mb-2">
                            Subject Line
                        </label>
                        <input
                            type="text"
                            name="subject"
                            id="subject"
                            value="{{ old('subject', $vacationFilter ? $vacationFilter->actions->firstWhere('action_type', 'vacation')->vacation_subject ?? '' : 'Out of Office Auto-Reply') }}"
                            required
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                            placeholder="Out of Office Auto-Reply"
                        >
                        @error('subject')
                            <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="message" class="block text-sm font-medium text-gray-700 mb-2">
                            Message
                        </label>
                        <textarea
                            name="message"
                            id="message"
                            rows="8"
                            required
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                            placeholder="Thank you for your email. I am currently out of the office and will respond when I return..."
                        >{{ old('message', $vacationFilter ? $vacationFilter->actions->firstWhere('action_type', 'vacation')->vacation_message ?? '' : '') }}</textarea>
                        @error('message')
                            <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                        @enderror
                        <p class="text-sm text-gray-500 mt-2">
                            This message will be sent automatically to people who email you.
                        </p>
                    </div>

                    <div>
                        <label for="days_between_responses" class="block text-sm font-medium text-gray-700 mb-2">
                            Days Between Responses
                        </label>
                        <input
                            type="number"
                            name="days_between_responses"
                            id="days_between_responses"
                            value="{{ old('days_between_responses', $vacationFilter ? $vacationFilter->actions->firstWhere('action_type', 'vacation')->vacation_days ?? 7 : 7) }}"
                            min="1"
                            max="30"
                            class="w-full md:w-1/3 px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                        >
                        @error('days_between_responses')
                            <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                        @enderror
                        <p class="text-sm text-gray-500 mt-2">
                            The same person will only receive one auto-reply every N days to prevent spam.
                        </p>
                    </div>

                    <div class="flex items-center">
                        <input
                            type="checkbox"
                            name="is_active"
                            id="is_active"
                            value="1"
                            {{ old('is_active', $vacationFilter ? $vacationFilter->is_active : true) ? 'checked' : '' }}
                            class="w-4 h-4 text-blue-600 border-gray-300 rounded focus:ring-blue-500"
                        >
                        <label for="is_active" class="ml-2 text-sm font-medium text-gray-700">
                            Enable vacation responder immediately
                        </label>
                    </div>
                </div>

                <!-- Info Box -->
                <div class="mt-6 bg-blue-50 border-l-4 border-blue-500 p-4">
                    <div class="flex items-start">
                        <svg class="w-5 h-5 text-blue-500 mr-3 flex-shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd" />
                        </svg>
                        <div>
                            <h4 class="text-sm font-semibold text-blue-900 mb-1">How it works:</h4>
                            <ul class="text-sm text-blue-800 space-y-1">
                                <li>Automatic replies are sent when someone emails you</li>
                                <li>Each sender receives only one reply per time period you specify</li>
                                <li>Replies are not sent to mailing lists or bulk mail</li>
                                <li>You can enable or disable this at any time</li>
                            </ul>
                        </div>
                    </div>
                </div>

                <!-- Submit Button -->
                <div class="mt-6 flex justify-end space-x-4">
                    <a href="{{ route('user.email-filters.index', ['email' => $selectedEmail]) }}" class="px-6 py-3 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50 transition font-medium">
                        Cancel
                    </a>
                    <button type="submit" class="px-6 py-3 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition font-medium">
                        {{ $vacationFilter ? 'Update' : 'Enable' }} Vacation Responder
                    </button>
                </div>
            </form>
        </div>
    @else
        <div class="bg-white rounded-lg shadow-lg p-12 text-center">
            <svg class="w-16 h-16 text-gray-400 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
            </svg>
            <h3 class="text-lg font-medium text-gray-900 mb-2">Select an Email Account</h3>
            <p class="text-gray-600">Choose an email account above to configure vacation responder settings</p>
        </div>
    @endif
</div>

<script>
function selectEmail(email) {
    if (email) {
        window.location.href = "{{ route('user.email-filters.vacation') }}?email=" + encodeURIComponent(email);
    }
}
</script>
@endsection
