@extends('layouts.admin')

@section('title', 'Migration Progress - WHM')

@section('content')
<div class="container mx-auto px-4 py-8 max-w-5xl">
    <!-- Header -->
    <div class="flex justify-between items-center mb-8">
        <div>
            <h1 class="text-3xl font-bold text-gray-900">Migration Progress</h1>
            <p class="text-gray-600 mt-2">Migration #{{ $migration->id }} - {{ $migration->migration_summary['account'] ?? 'Unknown' }}</p>
        </div>
        <a href="{{ route('admin.migration.show', $migration->id) }}"
           class="px-4 py-2 border border-gray-300 rounded-lg hover:bg-gray-50 transition">
            View Details
        </a>
    </div>

    <!-- Progress Card -->
    <div class="bg-white rounded-lg shadow p-8 mb-6">
        <!-- Status -->
        <div class="mb-6 text-center">
            <div id="status-badge" class="inline-flex items-center px-4 py-2 rounded-full text-lg font-semibold
                {{ $migration->status === 'completed' ? 'bg-green-100 text-green-800' : '' }}
                {{ $migration->status === 'failed' ? 'bg-red-100 text-red-800' : '' }}
                {{ in_array($migration->status, ['uploading', 'parsing', 'processing']) ? 'bg-blue-100 text-blue-800' : '' }}">
                <span id="status-text">{{ ucfirst($migration->status) }}</span>
            </div>
        </div>

        <!-- Progress Bar -->
        <div class="mb-6">
            <div class="flex justify-between mb-2">
                <span class="text-sm font-medium text-gray-700" id="current-step">{{ $migration->current_step ?? 'Initializing...' }}</span>
                <span class="text-sm font-medium text-gray-700"><span id="progress-percentage">{{ $migration->progress_percentage }}</span>%</span>
            </div>
            <div class="w-full bg-gray-200 rounded-full h-4">
                <div id="progress-bar" class="bg-blue-600 h-4 rounded-full transition-all duration-500" style="width: {{ $migration->progress_percentage }}%"></div>
            </div>
        </div>

        <!-- Spinner (shown during progress) -->
        <div id="spinner" class="flex justify-center mb-4 {{ $migration->isInProgress() ? '' : 'hidden' }}">
            <svg class="animate-spin h-8 w-8 text-blue-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
            </svg>
        </div>

        <!-- Completion Message -->
        <div id="completion-message" class="{{ $migration->isCompleted() ? '' : 'hidden' }}">
            <div class="bg-green-50 border-l-4 border-green-500 p-4 mb-4">
                <div class="flex items-center">
                    <svg class="h-6 w-6 text-green-500 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    <p class="text-green-700 font-medium">Migration completed successfully!</p>
                </div>
            </div>
            <div class="text-center">
                <a href="{{ route('admin.migration.report', $migration->id) }}"
                   class="inline-block bg-green-600 text-white px-6 py-3 rounded-lg hover:bg-green-700 transition">
                    View Migration Report
                </a>
            </div>
        </div>

        <!-- Error Message -->
        <div id="error-message" class="{{ $migration->hasFailed() ? '' : 'hidden' }}">
            <div class="bg-red-50 border-l-4 border-red-500 p-4">
                <div class="flex items-center">
                    <svg class="h-6 w-6 text-red-500 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    <div>
                        <p class="text-red-700 font-medium">Migration failed</p>
                        <p id="error-text" class="text-red-600 text-sm mt-1">{{ $migration->error_message }}</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Recent Logs -->
    <div class="bg-white rounded-lg shadow p-6">
        <h2 class="text-xl font-semibold text-gray-900 mb-4">Live Activity Log</h2>
        <div id="logs-container" class="space-y-2 max-h-96 overflow-y-auto">
            @foreach($migration->logs()->orderBy('created_at', 'desc')->limit(20)->get() as $log)
            <div class="flex items-start py-2 border-b last:border-b-0 log-entry">
                <span class="flex-shrink-0 w-20 text-xs text-gray-500">{{ $log->created_at->format('H:i:s') }}</span>
                <span class="flex-shrink-0 px-2 py-1 text-xs font-semibold rounded
                    {{ $log->level === 'error' ? 'bg-red-100 text-red-800' : '' }}
                    {{ $log->level === 'warning' ? 'bg-yellow-100 text-yellow-800' : '' }}
                    {{ $log->level === 'success' ? 'bg-green-100 text-green-800' : '' }}
                    {{ $log->level === 'info' ? 'bg-blue-100 text-blue-800' : '' }}">
                    {{ ucfirst($log->level) }}
                </span>
                @if($log->category)
                <span class="ml-2 text-xs text-gray-500">[{{ $log->category }}]</span>
                @endif
                @if($log->item_name)
                <span class="ml-2 text-xs font-medium text-gray-700">{{ $log->item_name }}</span>
                @endif
                <span class="ml-2 text-sm text-gray-900">{{ $log->message }}</span>
            </div>
            @endforeach
        </div>
    </div>
</div>

<script>
let migrationId = {{ $migration->id }};
let updateInterval;
let isCompleted = {{ $migration->isCompleted() ? 'true' : 'false' }};
let hasFailed = {{ $migration->hasFailed() ? 'true' : 'false' }};

function updateProgress() {
    fetch(`/admin/migration/${migrationId}/status`)
        .then(response => response.json())
        .then(data => {
            // Update progress bar
            document.getElementById('progress-percentage').textContent = data.progress_percentage;
            document.getElementById('progress-bar').style.width = data.progress_percentage + '%';

            // Update current step
            if (data.current_step) {
                document.getElementById('current-step').textContent = data.current_step;
            }

            // Update status
            const statusText = document.getElementById('status-text');
            const statusBadge = document.getElementById('status-badge');
            statusText.textContent = data.status.charAt(0).toUpperCase() + data.status.slice(1);

            // Update status badge color
            statusBadge.className = 'inline-flex items-center px-4 py-2 rounded-full text-lg font-semibold';
            if (data.status === 'completed') {
                statusBadge.classList.add('bg-green-100', 'text-green-800');
            } else if (data.status === 'failed') {
                statusBadge.classList.add('bg-red-100', 'text-red-800');
            } else {
                statusBadge.classList.add('bg-blue-100', 'text-blue-800');
            }

            // Handle completion
            if (data.is_completed && !isCompleted) {
                isCompleted = true;
                document.getElementById('spinner').classList.add('hidden');
                document.getElementById('completion-message').classList.remove('hidden');
                clearInterval(updateInterval);
            }

            // Handle failure
            if (data.has_failed && !hasFailed) {
                hasFailed = true;
                document.getElementById('spinner').classList.add('hidden');
                document.getElementById('error-message').classList.remove('hidden');
                if (data.error_message) {
                    document.getElementById('error-text').textContent = data.error_message;
                }
                clearInterval(updateInterval);
            }

            // Update logs
            if (data.recent_logs && data.recent_logs.length > 0) {
                updateLogs(data.recent_logs);
            }
        })
        .catch(error => {
            console.error('Error fetching migration status:', error);
        });
}

function updateLogs(logs) {
    const logsContainer = document.getElementById('logs-container');
    const existingLogs = logsContainer.querySelectorAll('.log-entry');
    const existingLogCount = existingLogs.length;

    // Only add new logs
    if (logs.length > 0) {
        const newLogs = logs.slice(0, Math.max(0, logs.length - existingLogCount));

        newLogs.reverse().forEach(log => {
            const logEntry = document.createElement('div');
            logEntry.className = 'flex items-start py-2 border-b last:border-b-0 log-entry';

            const time = new Date(log.created_at).toLocaleTimeString('en-US', { hour12: false });

            let levelClass = 'bg-gray-100 text-gray-800';
            if (log.level === 'error') levelClass = 'bg-red-100 text-red-800';
            if (log.level === 'warning') levelClass = 'bg-yellow-100 text-yellow-800';
            if (log.level === 'success') levelClass = 'bg-green-100 text-green-800';
            if (log.level === 'info') levelClass = 'bg-blue-100 text-blue-800';

            logEntry.innerHTML = `
                <span class="flex-shrink-0 w-20 text-xs text-gray-500">${time}</span>
                <span class="flex-shrink-0 px-2 py-1 text-xs font-semibold rounded ${levelClass}">
                    ${log.level.charAt(0).toUpperCase() + log.level.slice(1)}
                </span>
                ${log.category ? `<span class="ml-2 text-xs text-gray-500">[${log.category}]</span>` : ''}
                ${log.item_name ? `<span class="ml-2 text-xs font-medium text-gray-700">${log.item_name}</span>` : ''}
                <span class="ml-2 text-sm text-gray-900">${log.message}</span>
            `;

            logsContainer.insertBefore(logEntry, logsContainer.firstChild);
        });

        // Remove old logs to keep max 20
        while (logsContainer.children.length > 20) {
            logsContainer.removeChild(logsContainer.lastChild);
        }
    }
}

// Start polling if migration is in progress
if (!isCompleted && !hasFailed) {
    updateInterval = setInterval(updateProgress, 2000); // Update every 2 seconds
}

// Stop polling when page is hidden
document.addEventListener('visibilitychange', function() {
    if (document.hidden) {
        if (updateInterval) {
            clearInterval(updateInterval);
        }
    } else {
        if (!isCompleted && !hasFailed) {
            updateProgress();
            updateInterval = setInterval(updateProgress, 2000);
        }
    }
});
</script>
@endsection
