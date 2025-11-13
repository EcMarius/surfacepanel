@extends('layouts.admin')

@section('title', 'Migration Report - WHM')

@section('content')
<div class="container mx-auto px-4 py-8 max-w-6xl">
    <!-- Header -->
    <div class="flex justify-between items-center mb-8">
        <div>
            <h1 class="text-3xl font-bold text-gray-900">Migration Report</h1>
            <p class="text-gray-600 mt-2">Migration #{{ $migration->id }} - {{ $migration->migration_summary['account'] ?? 'Unknown' }}</p>
        </div>
        <div class="flex gap-3">
            <a href="{{ route('admin.migration.download-logs', $migration->id) }}"
               class="px-4 py-2 border border-gray-300 rounded-lg hover:bg-gray-50 transition">
                Download Logs
            </a>
            <a href="{{ route('admin.migration.index') }}"
               class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition">
                Back to List
            </a>
        </div>
    </div>

    <!-- Summary Cards -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
        <div class="bg-white rounded-lg shadow p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-600 mb-1">Status</p>
                    @if($migration->status === 'completed')
                        <p class="text-2xl font-bold text-green-600">Completed</p>
                    @elseif($migration->status === 'failed')
                        <p class="text-2xl font-bold text-red-600">Failed</p>
                    @else
                        <p class="text-2xl font-bold text-blue-600">{{ ucfirst($migration->status) }}</p>
                    @endif
                </div>
                <svg class="w-12 h-12 {{ $migration->isCompleted() ? 'text-green-500' : 'text-red-500' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    @if($migration->isCompleted())
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    @else
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    @endif
                </svg>
            </div>
        </div>

        <div class="bg-white rounded-lg shadow p-6">
            <p class="text-sm text-gray-600 mb-1">Duration</p>
            <p class="text-2xl font-bold text-gray-900">{{ $migration->duration ?? 'N/A' }}</p>
        </div>

        <div class="bg-white rounded-lg shadow p-6">
            <p class="text-sm text-gray-600 mb-1">Total Logs</p>
            <p class="text-2xl font-bold text-gray-900">{{ $migration->logs->count() }}</p>
        </div>

        <div class="bg-white rounded-lg shadow p-6">
            <p class="text-sm text-gray-600 mb-1">Errors</p>
            <p class="text-2xl font-bold {{ $errorLogs->count() > 0 ? 'text-red-600' : 'text-green-600' }}">
                {{ $errorLogs->count() }}
            </p>
        </div>
    </div>

    <!-- Migration Results -->
    @if($migration->migration_results)
    <div class="bg-white rounded-lg shadow p-6 mb-8">
        <h2 class="text-xl font-semibold text-gray-900 mb-6">Migration Results</h2>
        <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4">
            <div class="border-l-4 border-green-500 pl-4">
                <p class="text-sm text-gray-600">Account</p>
                <p class="text-2xl font-bold text-green-600">{{ $migration->migration_results['account'] ?? 0 }}</p>
            </div>
            <div class="border-l-4 border-blue-500 pl-4">
                <p class="text-sm text-gray-600">Addon Domains</p>
                <p class="text-2xl font-bold text-blue-600">{{ $migration->migration_results['addon_domains'] ?? 0 }}</p>
            </div>
            <div class="border-l-4 border-blue-500 pl-4">
                <p class="text-sm text-gray-600">Subdomains</p>
                <p class="text-2xl font-bold text-blue-600">{{ $migration->migration_results['subdomains'] ?? 0 }}</p>
            </div>
            <div class="border-l-4 border-blue-500 pl-4">
                <p class="text-sm text-gray-600">Parked Domains</p>
                <p class="text-2xl font-bold text-blue-600">{{ $migration->migration_results['parked_domains'] ?? 0 }}</p>
            </div>
            <div class="border-l-4 border-green-500 pl-4">
                <p class="text-sm text-gray-600">Email Accounts</p>
                <p class="text-2xl font-bold text-green-600">{{ $migration->migration_results['email_accounts'] ?? 0 }}</p>
            </div>
            <div class="border-l-4 border-green-500 pl-4">
                <p class="text-sm text-gray-600">Email Forwarders</p>
                <p class="text-2xl font-bold text-green-600">{{ $migration->migration_results['email_forwarders'] ?? 0 }}</p>
            </div>
            <div class="border-l-4 border-purple-500 pl-4">
                <p class="text-sm text-gray-600">Databases</p>
                <p class="text-2xl font-bold text-purple-600">{{ $migration->migration_results['databases'] ?? 0 }}</p>
            </div>
            <div class="border-l-4 border-purple-500 pl-4">
                <p class="text-sm text-gray-600">Database Users</p>
                <p class="text-2xl font-bold text-purple-600">{{ $migration->migration_results['database_users'] ?? 0 }}</p>
            </div>
            <div class="border-l-4 border-orange-500 pl-4">
                <p class="text-sm text-gray-600">DNS Zones</p>
                <p class="text-2xl font-bold text-orange-600">{{ $migration->migration_results['dns_zones'] ?? 0 }}</p>
            </div>
            <div class="border-l-4 border-red-500 pl-4">
                <p class="text-sm text-gray-600">SSL Certificates</p>
                <p class="text-2xl font-bold text-red-600">{{ $migration->migration_results['ssl_certificates'] ?? 0 }}</p>
            </div>
            <div class="border-l-4 border-yellow-500 pl-4">
                <p class="text-sm text-gray-600">Cron Jobs</p>
                <p class="text-2xl font-bold text-yellow-600">{{ $migration->migration_results['cron_jobs'] ?? 0 }}</p>
            </div>
            <div class="border-l-4 border-teal-500 pl-4">
                <p class="text-sm text-gray-600">FTP Accounts</p>
                <p class="text-2xl font-bold text-teal-600">{{ $migration->migration_results['ftp_accounts'] ?? 0 }}</p>
            </div>
        </div>
    </div>
    @endif

    <!-- Errors -->
    @if($errorLogs->count() > 0)
    <div class="bg-white rounded-lg shadow p-6 mb-8">
        <h2 class="text-xl font-semibold text-gray-900 mb-4">Errors ({{ $errorLogs->count() }})</h2>
        <div class="space-y-2">
            @foreach($errorLogs as $log)
            <div class="bg-red-50 border-l-4 border-red-500 p-4">
                <div class="flex items-start">
                    <svg class="h-5 w-5 text-red-500 mr-3 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    <div class="flex-1">
                        <div class="flex items-center justify-between mb-1">
                            <div class="flex items-center">
                                @if($log->category)
                                <span class="text-xs font-semibold text-red-800 mr-2">[{{ strtoupper($log->category) }}]</span>
                                @endif
                                @if($log->item_name)
                                <span class="text-sm font-medium text-red-900">{{ $log->item_name }}</span>
                                @endif
                            </div>
                            <span class="text-xs text-red-600">{{ $log->created_at->format('Y-m-d H:i:s') }}</span>
                        </div>
                        <p class="text-sm text-red-700">{{ $log->message }}</p>
                        @if($log->details)
                        <details class="mt-2">
                            <summary class="text-xs text-red-600 cursor-pointer">View Details</summary>
                            <pre class="mt-2 text-xs bg-red-100 p-2 rounded overflow-x-auto">{{ json_encode($log->details, JSON_PRETTY_PRINT) }}</pre>
                        </details>
                        @endif
                    </div>
                </div>
            </div>
            @endforeach
        </div>
    </div>
    @endif

    <!-- Warnings -->
    @if($warningLogs->count() > 0)
    <div class="bg-white rounded-lg shadow p-6 mb-8">
        <h2 class="text-xl font-semibold text-gray-900 mb-4">Warnings ({{ $warningLogs->count() }})</h2>
        <div class="space-y-2">
            @foreach($warningLogs as $log)
            <div class="bg-yellow-50 border-l-4 border-yellow-500 p-4">
                <div class="flex items-start">
                    <svg class="h-5 w-5 text-yellow-500 mr-3 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                    </svg>
                    <div class="flex-1">
                        <div class="flex items-center justify-between mb-1">
                            <div class="flex items-center">
                                @if($log->category)
                                <span class="text-xs font-semibold text-yellow-800 mr-2">[{{ strtoupper($log->category) }}]</span>
                                @endif
                                @if($log->item_name)
                                <span class="text-sm font-medium text-yellow-900">{{ $log->item_name }}</span>
                                @endif
                            </div>
                            <span class="text-xs text-yellow-600">{{ $log->created_at->format('Y-m-d H:i:s') }}</span>
                        </div>
                        <p class="text-sm text-yellow-700">{{ $log->message }}</p>
                    </div>
                </div>
            </div>
            @endforeach
        </div>
    </div>
    @endif

    <!-- Full Log by Category -->
    <div class="bg-white rounded-lg shadow p-6">
        <h2 class="text-xl font-semibold text-gray-900 mb-4">Complete Log</h2>

        <!-- Category Tabs -->
        <div class="border-b border-gray-200 mb-4">
            <nav class="-mb-px flex space-x-4" id="log-tabs">
                <button onclick="showCategory('all')" class="log-tab active border-b-2 border-blue-500 py-2 px-3 text-sm font-medium text-blue-600">
                    All ({{ $migration->logs->count() }})
                </button>
                @foreach($logsByCategory as $category => $logs)
                    @if($category)
                    <button onclick="showCategory('{{ $category }}')" class="log-tab border-b-2 border-transparent py-2 px-3 text-sm font-medium text-gray-500 hover:text-gray-700 hover:border-gray-300">
                        {{ ucfirst($category) }} ({{ $logs->count() }})
                    </button>
                    @endif
                @endforeach
            </nav>
        </div>

        <!-- Logs -->
        <div id="log-content" class="space-y-1 max-h-96 overflow-y-auto">
            @foreach($migration->logs()->orderBy('created_at', 'asc')->get() as $log)
            <div class="log-entry py-2 border-b flex items-start text-sm" data-category="{{ $log->category ?? 'none' }}">
                <span class="flex-shrink-0 w-32 text-xs text-gray-500">{{ $log->created_at->format('Y-m-d H:i:s') }}</span>
                <span class="flex-shrink-0 px-2 py-1 text-xs font-semibold rounded mr-2
                    {{ $log->level === 'error' ? 'bg-red-100 text-red-800' : '' }}
                    {{ $log->level === 'warning' ? 'bg-yellow-100 text-yellow-800' : '' }}
                    {{ $log->level === 'success' ? 'bg-green-100 text-green-800' : '' }}
                    {{ $log->level === 'info' ? 'bg-blue-100 text-blue-800' : '' }}">
                    {{ ucfirst($log->level) }}
                </span>
                @if($log->category)
                <span class="flex-shrink-0 text-xs text-gray-500 mr-2">[{{ $log->category }}]</span>
                @endif
                @if($log->item_name)
                <span class="flex-shrink-0 text-xs font-medium text-gray-700 mr-2">{{ $log->item_name }}</span>
                @endif
                <span class="text-gray-900">{{ $log->message }}</span>
            </div>
            @endforeach
        </div>
    </div>
</div>

<script>
function showCategory(category) {
    const logEntries = document.querySelectorAll('.log-entry');
    const tabs = document.querySelectorAll('.log-tab');

    // Update tabs
    tabs.forEach(tab => {
        tab.classList.remove('active', 'border-blue-500', 'text-blue-600');
        tab.classList.add('border-transparent', 'text-gray-500');
    });

    event.target.classList.add('active', 'border-blue-500', 'text-blue-600');
    event.target.classList.remove('border-transparent', 'text-gray-500');

    // Filter logs
    logEntries.forEach(entry => {
        if (category === 'all') {
            entry.style.display = 'flex';
        } else {
            const entryCategory = entry.getAttribute('data-category');
            entry.style.display = entryCategory === category ? 'flex' : 'none';
        }
    });
}
</script>
@endsection
