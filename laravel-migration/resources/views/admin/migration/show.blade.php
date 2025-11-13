@extends('layouts.admin')

@section('title', 'Migration Details - WHM')

@section('content')
<div class="container mx-auto px-4 py-8 max-w-6xl">
    <!-- Header -->
    <div class="flex justify-between items-center mb-8">
        <div>
            <h1 class="text-3xl font-bold text-gray-900">Migration #{{ $migration->id }}</h1>
            <p class="text-gray-600 mt-2">Review migration summary and start the migration process</p>
        </div>
        <div class="flex gap-3">
            <a href="{{ route('admin.migration.index') }}"
               class="px-4 py-2 border border-gray-300 rounded-lg hover:bg-gray-50 transition">
                Back to List
            </a>
            @if($migration->status === 'pending')
                <form action="{{ route('admin.migration.start', $migration->id) }}" method="POST">
                    @csrf
                    <button type="submit"
                            onclick="return confirm('Are you sure you want to start this migration?')"
                            class="bg-green-600 text-white px-6 py-2 rounded-lg hover:bg-green-700 transition">
                        Start Migration
                    </button>
                </form>
            @endif
        </div>
    </div>

    <!-- Alerts -->
    @if(session('success'))
    <div class="bg-green-50 border-l-4 border-green-500 p-4 mb-6">
        <p class="text-green-700">{{ session('success') }}</p>
    </div>
    @endif

    @if(session('error'))
    <div class="bg-red-50 border-l-4 border-red-500 p-4 mb-6">
        <p class="text-red-700">{{ session('error') }}</p>
    </div>
    @endif

    <!-- Status Card -->
    <div class="bg-white rounded-lg shadow p-6 mb-6">
        <h2 class="text-xl font-semibold text-gray-900 mb-4">Migration Status</h2>
        <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
            <div>
                <p class="text-sm text-gray-600 mb-1">Status</p>
                @if($migration->status === 'completed')
                    <span class="px-3 py-1 text-sm font-semibold rounded-full bg-green-100 text-green-800">
                        Completed
                    </span>
                @elseif($migration->status === 'failed')
                    <span class="px-3 py-1 text-sm font-semibold rounded-full bg-red-100 text-red-800">
                        Failed
                    </span>
                @elseif($migration->status === 'pending')
                    <span class="px-3 py-1 text-sm font-semibold rounded-full bg-yellow-100 text-yellow-800">
                        Pending
                    </span>
                @else
                    <span class="px-3 py-1 text-sm font-semibold rounded-full bg-blue-100 text-blue-800">
                        {{ ucfirst($migration->status) }}
                    </span>
                @endif
            </div>
            <div>
                <p class="text-sm text-gray-600 mb-1">Progress</p>
                <div class="flex items-center">
                    <div class="w-24 bg-gray-200 rounded-full h-2 mr-2">
                        <div class="bg-blue-600 h-2 rounded-full" style="width: {{ $migration->progress_percentage }}%"></div>
                    </div>
                    <span class="text-sm font-medium">{{ $migration->progress_percentage }}%</span>
                </div>
            </div>
            <div>
                <p class="text-sm text-gray-600 mb-1">Backup Size</p>
                <p class="text-lg font-semibold">{{ $migration->formatted_backup_size }}</p>
            </div>
            <div>
                <p class="text-sm text-gray-600 mb-1">Created</p>
                <p class="text-lg font-semibold">{{ $migration->created_at->format('M d, Y') }}</p>
            </div>
        </div>

        @if($migration->current_step)
        <div class="mt-4 pt-4 border-t">
            <p class="text-sm text-gray-600">Current Step:</p>
            <p class="text-lg font-medium text-blue-600">{{ $migration->current_step }}</p>
        </div>
        @endif

        @if($migration->error_message)
        <div class="mt-4 pt-4 border-t">
            <p class="text-sm text-gray-600 mb-2">Error Message:</p>
            <div class="bg-red-50 border border-red-200 rounded p-3">
                <p class="text-sm text-red-700">{{ $migration->error_message }}</p>
            </div>
        </div>
        @endif
    </div>

    <!-- Backup Information -->
    <div class="bg-white rounded-lg shadow p-6 mb-6">
        <h2 class="text-xl font-semibold text-gray-900 mb-4">Backup Information</h2>
        <dl class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <dt class="text-sm font-medium text-gray-600">Filename</dt>
                <dd class="mt-1 text-sm text-gray-900">{{ $migration->backup_filename }}</dd>
            </div>
            <div>
                <dt class="text-sm font-medium text-gray-600">Source Type</dt>
                <dd class="mt-1 text-sm text-gray-900">{{ strtoupper($migration->source_type) }}</dd>
            </div>
            <div>
                <dt class="text-sm font-medium text-gray-600">Uploaded By</dt>
                <dd class="mt-1 text-sm text-gray-900">{{ $migration->creator->username ?? 'N/A' }}</dd>
            </div>
            <div>
                <dt class="text-sm font-medium text-gray-600">Upload Date</dt>
                <dd class="mt-1 text-sm text-gray-900">{{ $migration->created_at->format('F d, Y H:i:s') }}</dd>
            </div>
            @if($migration->started_at)
            <div>
                <dt class="text-sm font-medium text-gray-600">Started At</dt>
                <dd class="mt-1 text-sm text-gray-900">{{ $migration->started_at->format('F d, Y H:i:s') }}</dd>
            </div>
            @endif
            @if($migration->completed_at)
            <div>
                <dt class="text-sm font-medium text-gray-600">Completed At</dt>
                <dd class="mt-1 text-sm text-gray-900">{{ $migration->completed_at->format('F d, Y H:i:s') }}</dd>
            </div>
            @endif
            @if($migration->duration)
            <div>
                <dt class="text-sm font-medium text-gray-600">Duration</dt>
                <dd class="mt-1 text-sm text-gray-900">{{ $migration->duration }}</dd>
            </div>
            @endif
        </dl>
    </div>

    <!-- Migration Summary -->
    @if($migration->migration_summary)
    <div class="bg-white rounded-lg shadow p-6 mb-6">
        <h2 class="text-xl font-semibold text-gray-900 mb-4">Migration Summary</h2>

        <!-- Account Info -->
        <div class="bg-blue-50 border-l-4 border-blue-500 p-4 mb-6">
            <h3 class="font-semibold text-blue-900 mb-2">Account Information</h3>
            <dl class="grid grid-cols-2 gap-4">
                <div>
                    <dt class="text-sm text-blue-700">Username</dt>
                    <dd class="font-medium text-blue-900">{{ $migration->migration_summary['account'] ?? 'N/A' }}</dd>
                </div>
                <div>
                    <dt class="text-sm text-blue-700">Domain</dt>
                    <dd class="font-medium text-blue-900">{{ $migration->migration_summary['domain'] ?? 'N/A' }}</dd>
                </div>
                @if(isset($migration->migration_summary['email']))
                <div>
                    <dt class="text-sm text-blue-700">Email</dt>
                    <dd class="font-medium text-blue-900">{{ $migration->migration_summary['email'] }}</dd>
                </div>
                @endif
                @if(isset($migration->migration_summary['plan']))
                <div>
                    <dt class="text-sm text-blue-700">Plan</dt>
                    <dd class="font-medium text-blue-900">{{ $migration->migration_summary['plan'] }}</dd>
                </div>
                @endif
            </dl>
        </div>

        <!-- Items to Migrate -->
        <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4">
            <div class="border rounded-lg p-4 text-center">
                <div class="text-3xl font-bold text-blue-600">{{ $migration->migration_summary['addon_domains'] ?? 0 }}</div>
                <div class="text-sm text-gray-600 mt-1">Addon Domains</div>
            </div>
            <div class="border rounded-lg p-4 text-center">
                <div class="text-3xl font-bold text-blue-600">{{ $migration->migration_summary['subdomains'] ?? 0 }}</div>
                <div class="text-sm text-gray-600 mt-1">Subdomains</div>
            </div>
            <div class="border rounded-lg p-4 text-center">
                <div class="text-3xl font-bold text-blue-600">{{ $migration->migration_summary['parked_domains'] ?? 0 }}</div>
                <div class="text-sm text-gray-600 mt-1">Parked Domains</div>
            </div>
            <div class="border rounded-lg p-4 text-center">
                <div class="text-3xl font-bold text-green-600">{{ $migration->migration_summary['email_accounts'] ?? 0 }}</div>
                <div class="text-sm text-gray-600 mt-1">Email Accounts</div>
            </div>
            <div class="border rounded-lg p-4 text-center">
                <div class="text-3xl font-bold text-green-600">{{ $migration->migration_summary['email_forwarders'] ?? 0 }}</div>
                <div class="text-sm text-gray-600 mt-1">Email Forwarders</div>
            </div>
            <div class="border rounded-lg p-4 text-center">
                <div class="text-3xl font-bold text-purple-600">{{ $migration->migration_summary['databases'] ?? 0 }}</div>
                <div class="text-sm text-gray-600 mt-1">Databases</div>
            </div>
            <div class="border rounded-lg p-4 text-center">
                <div class="text-3xl font-bold text-purple-600">{{ $migration->migration_summary['database_users'] ?? 0 }}</div>
                <div class="text-sm text-gray-600 mt-1">Database Users</div>
            </div>
            <div class="border rounded-lg p-4 text-center">
                <div class="text-3xl font-bold text-orange-600">{{ $migration->migration_summary['dns_zones'] ?? 0 }}</div>
                <div class="text-sm text-gray-600 mt-1">DNS Zones</div>
            </div>
            <div class="border rounded-lg p-4 text-center">
                <div class="text-3xl font-bold text-red-600">{{ $migration->migration_summary['ssl_certificates'] ?? 0 }}</div>
                <div class="text-sm text-gray-600 mt-1">SSL Certificates</div>
            </div>
            <div class="border rounded-lg p-4 text-center">
                <div class="text-3xl font-bold text-yellow-600">{{ $migration->migration_summary['cron_jobs'] ?? 0 }}</div>
                <div class="text-sm text-gray-600 mt-1">Cron Jobs</div>
            </div>
            <div class="border rounded-lg p-4 text-center">
                <div class="text-3xl font-bold text-teal-600">{{ $migration->migration_summary['ftp_accounts'] ?? 0 }}</div>
                <div class="text-sm text-gray-600 mt-1">FTP Accounts</div>
            </div>
        </div>
    </div>
    @endif

    <!-- Validation Errors -->
    @if($migration->validation_errors && count($migration->validation_errors) > 0)
    <div class="bg-red-50 border-l-4 border-red-500 p-6 mb-6">
        <h3 class="text-lg font-semibold text-red-900 mb-3">Validation Errors</h3>
        <ul class="list-disc list-inside space-y-1 text-red-700">
            @foreach($migration->validation_errors as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
    @endif

    <!-- Recent Logs -->
    @if($migration->logs->count() > 0)
    <div class="bg-white rounded-lg shadow p-6">
        <h2 class="text-xl font-semibold text-gray-900 mb-4">Recent Activity</h2>
        <div class="space-y-2">
            @foreach($migration->logs()->orderBy('created_at', 'desc')->limit(10)->get() as $log)
            <div class="flex items-start py-2 border-b last:border-b-0">
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
        @if($migration->logs->count() > 10)
        <div class="mt-4 text-center">
            <a href="{{ route('admin.migration.report', $migration->id) }}"
               class="text-blue-600 hover:text-blue-800 text-sm font-medium">
                View Full Report
            </a>
        </div>
        @endif
    </div>
    @endif
</div>
@endsection
