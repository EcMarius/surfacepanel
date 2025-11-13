@extends('layouts.user')

@section('title', 'Create Email Filter')

@section('content')
<div class="container mx-auto px-4 py-8">
    <div class="mb-8">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-3xl font-bold text-gray-900 mb-2">Create Email Filter</h1>
                <p class="text-gray-600">Build a custom filter rule to organize your email</p>
            </div>
            <a href="{{ route('user.email-filters.index', ['email' => $selectedEmail]) }}" class="text-gray-600 hover:text-gray-900 transition">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </a>
        </div>
    </div>

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

    <form method="POST" action="{{ route('user.email-filters.store') }}" id="filterForm">
        @csrf

        <!-- Basic Information -->
        <div class="bg-white rounded-lg shadow-lg mb-6 p-6">
            <h2 class="text-xl font-semibold text-gray-900 mb-6">Basic Information</h2>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label for="email" class="block text-sm font-medium text-gray-700 mb-2">Email Account</label>
                    <select name="email" id="email" required class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                        <option value="">Select email account...</option>
                        @foreach($emailAccounts as $emailAccount)
                            <option value="{{ $emailAccount }}" {{ old('email', $selectedEmail) === $emailAccount ? 'selected' : '' }}>
                                {{ $emailAccount }}
                            </option>
                        @endforeach
                    </select>
                    @error('email')
                        <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="name" class="block text-sm font-medium text-gray-700 mb-2">Filter Name</label>
                    <input type="text" name="name" id="name" value="{{ old('name') }}" required class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500" placeholder="e.g., Move spam to Junk">
                    @error('name')
                        <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            <div class="mt-4">
                <label for="description" class="block text-sm font-medium text-gray-700 mb-2">Description (Optional)</label>
                <textarea name="description" id="description" rows="2" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500" placeholder="Describe what this filter does...">{{ old('description') }}</textarea>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mt-4">
                <div>
                    <label for="priority" class="block text-sm font-medium text-gray-700 mb-2">Priority</label>
                    <input type="number" name="priority" id="priority" value="{{ old('priority', 100) }}" min="1" max="999" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                    <p class="text-xs text-gray-500 mt-1">Lower numbers = higher priority</p>
                </div>

                <div>
                    <label for="match_type" class="block text-sm font-medium text-gray-700 mb-2">Match Type</label>
                    <select name="match_type" id="match_type" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                        <option value="all" {{ old('match_type', 'all') === 'all' ? 'selected' : '' }}>Match ALL conditions</option>
                        <option value="any" {{ old('match_type') === 'any' ? 'selected' : '' }}>Match ANY condition</option>
                    </select>
                </div>

                <div class="flex items-center pt-8">
                    <input type="checkbox" name="is_active" id="is_active" value="1" {{ old('is_active', true) ? 'checked' : '' }} class="w-4 h-4 text-blue-600 border-gray-300 rounded focus:ring-blue-500">
                    <label for="is_active" class="ml-2 text-sm font-medium text-gray-700">Active</label>
                </div>
            </div>
        </div>

        <!-- Conditions -->
        <div class="bg-white rounded-lg shadow-lg mb-6 p-6">
            <div class="flex items-center justify-between mb-6">
                <h2 class="text-xl font-semibold text-gray-900">IF Conditions</h2>
                <button type="button" onclick="addCondition()" class="bg-green-600 text-white px-4 py-2 rounded-lg hover:bg-green-700 transition text-sm font-medium">
                    + Add Condition
                </button>
            </div>

            <div id="conditions-container" class="space-y-4">
                <!-- Conditions will be added here dynamically -->
            </div>
        </div>

        <!-- Actions -->
        <div class="bg-white rounded-lg shadow-lg mb-6 p-6">
            <div class="flex items-center justify-between mb-6">
                <h2 class="text-xl font-semibold text-gray-900">THEN Actions</h2>
                <button type="button" onclick="addAction()" class="bg-green-600 text-white px-4 py-2 rounded-lg hover:bg-green-700 transition text-sm font-medium">
                    + Add Action
                </button>
            </div>

            <div id="actions-container" class="space-y-4">
                <!-- Actions will be added here dynamically -->
            </div>
        </div>

        <!-- Submit Buttons -->
        <div class="flex justify-end space-x-4">
            <a href="{{ route('user.email-filters.index', ['email' => $selectedEmail]) }}" class="px-6 py-3 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50 transition font-medium">
                Cancel
            </a>
            <button type="submit" class="px-6 py-3 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition font-medium">
                Create Filter
            </button>
        </div>
    </form>
</div>

<script>
let conditionIndex = 0;
let actionIndex = 0;

const availableFields = @json($availableFields);
const availableOperators = @json($availableOperators);
const availableActions = @json($availableActions);

// Add initial condition and action
document.addEventListener('DOMContentLoaded', function() {
    addCondition();
    addAction();
});

function addCondition() {
    const container = document.getElementById('conditions-container');
    const conditionHtml = `
        <div class="border border-gray-300 rounded-lg p-4 condition-item" data-index="${conditionIndex}">
            <div class="flex items-start justify-between mb-4">
                <h3 class="text-sm font-semibold text-gray-700">Condition ${conditionIndex + 1}</h3>
                <button type="button" onclick="removeCondition(this)" class="text-red-600 hover:text-red-800">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <label class="block text-xs font-medium text-gray-700 mb-1">Field</label>
                    <select name="conditions[${conditionIndex}][field]" onchange="updateConditionUI(this)" required class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
                        ${Object.entries(availableFields).map(([key, value]) => `<option value="${key}">${value}</option>`).join('')}
                    </select>
                </div>
                <div class="header-name-field hidden">
                    <label class="block text-xs font-medium text-gray-700 mb-1">Header Name</label>
                    <input type="text" name="conditions[${conditionIndex}][header_name]" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm" placeholder="X-Custom-Header">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-700 mb-1">Operator</label>
                    <select name="conditions[${conditionIndex}][operator]" required class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
                        ${Object.entries(availableOperators).map(([key, value]) => `<option value="${key}">${value}</option>`).join('')}
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-700 mb-1">Value</label>
                    <input type="text" name="conditions[${conditionIndex}][value]" required class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm" placeholder="Enter value...">
                </div>
            </div>
            <div class="mt-3">
                <label class="flex items-center">
                    <input type="checkbox" name="conditions[${conditionIndex}][case_sensitive]" value="1" class="w-4 h-4 text-blue-600 border-gray-300 rounded">
                    <span class="ml-2 text-sm text-gray-700">Case Sensitive</span>
                </label>
            </div>
        </div>
    `;
    container.insertAdjacentHTML('beforeend', conditionHtml);
    conditionIndex++;
}

function removeCondition(button) {
    const container = document.getElementById('conditions-container');
    if (container.children.length > 1) {
        button.closest('.condition-item').remove();
    } else {
        alert('You must have at least one condition');
    }
}

function updateConditionUI(select) {
    const conditionItem = select.closest('.condition-item');
    const headerNameField = conditionItem.querySelector('.header-name-field');

    if (select.value === 'header') {
        headerNameField.classList.remove('hidden');
    } else {
        headerNameField.classList.add('hidden');
    }
}

function addAction() {
    const container = document.getElementById('actions-container');
    const actionHtml = `
        <div class="border border-gray-300 rounded-lg p-4 action-item" data-index="${actionIndex}">
            <div class="flex items-start justify-between mb-4">
                <h3 class="text-sm font-semibold text-gray-700">Action ${actionIndex + 1}</h3>
                <button type="button" onclick="removeAction(this)" class="text-red-600 hover:text-red-800">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-medium text-gray-700 mb-1">Action Type</label>
                    <select name="actions[${actionIndex}][action_type]" onchange="updateActionUI(this)" required class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
                        ${Object.entries(availableActions).map(([key, value]) => `<option value="${key}">${value}</option>`).join('')}
                    </select>
                </div>
                <div class="action-value-field">
                    <label class="block text-xs font-medium text-gray-700 mb-1">Value</label>
                    <input type="text" name="actions[${actionIndex}][action_value]" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm" placeholder="Enter value...">
                </div>
            </div>
            <div class="vacation-fields hidden mt-4">
                <div class="grid grid-cols-1 gap-4">
                    <div>
                        <label class="block text-xs font-medium text-gray-700 mb-1">Vacation Subject</label>
                        <input type="text" name="actions[${actionIndex}][vacation_subject]" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm" placeholder="Out of Office">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-700 mb-1">Vacation Message</label>
                        <textarea name="actions[${actionIndex}][vacation_message]" rows="3" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm" placeholder="I am currently out of office..."></textarea>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-700 mb-1">Days Between Responses</label>
                        <input type="number" name="actions[${actionIndex}][vacation_days]" value="7" min="1" max="30" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
                    </div>
                </div>
            </div>
        </div>
    `;
    container.insertAdjacentHTML('beforeend', actionHtml);
    actionIndex++;
}

function removeAction(button) {
    const container = document.getElementById('actions-container');
    if (container.children.length > 1) {
        button.closest('.action-item').remove();
    } else {
        alert('You must have at least one action');
    }
}

function updateActionUI(select) {
    const actionItem = select.closest('.action-item');
    const valueField = actionItem.querySelector('.action-value-field');
    const vacationFields = actionItem.querySelector('.vacation-fields');
    const actionType = select.value;

    // Actions that require a value
    const requiresValue = ['move_to_folder', 'forward', 'redirect', 'reject', 'pipe_to_program'];

    if (actionType === 'vacation') {
        valueField.classList.add('hidden');
        vacationFields.classList.remove('hidden');
    } else {
        vacationFields.classList.add('hidden');
        if (requiresValue.includes(actionType)) {
            valueField.classList.remove('hidden');
        } else {
            valueField.classList.add('hidden');
        }
    }
}
</script>
@endsection
