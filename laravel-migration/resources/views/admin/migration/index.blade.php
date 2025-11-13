@extends('layouts.admin')

@section('title', 'cPanel/WHM Migrations - WHM')

@section('content')
<div class="container mx-auto px-4 py-8">
    <!-- Header -->
    <div class="flex justify-between items-center mb-8">
        <div>
            <h1 class="text-3xl font-bold text-gray-900">cPanel/WHM Migrations</h1>
            <p class="text-gray-600 mt-2">Import cPanel backups to VirPanel</p>
        </div>
        <a href="{{ route('admin.migration.create') }}"
           class="bg-green-600 text-white px-6 py-2 rounded-lg hover:bg-green-700 transition">
            Upload New Backup
        </a>
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

    <!-- Migrations Table -->
    <div class="bg-white rounded-lg shadow overflow-hidden">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                        ID
                    </th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                        Backup File
                    </th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                        Account
                    </th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                        Status
                    </th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                        Progress
                    </th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                        Created
                    </th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                        Actions
                    </th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                @forelse($migrations as $migration)
                <tr>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                        #{{ $migration->id }}
                    </td>
                    <td class="px-6 py-4 text-sm text-gray-900">
                        <div>{{ $migration->backup_filename }}</div>
                        <div class="text-xs text-gray-500">{{ $migration->formatted_backup_size }}</div>
                    </td>
                    <td class="px-6 py-4 text-sm text-gray-900">
                        @if($migration->migration_summary)
                            <div class="font-medium">{{ $migration->migration_summary['account'] ?? 'N/A' }}</div>
                            <div class="text-xs text-gray-500">{{ $migration->migration_summary['domain'] ?? 'N/A' }}</div>
                        @else
                            <span class="text-gray-400">Not parsed</span>
                        @endif
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        @if($migration->status === 'completed')
                            <span class="px-2 py-1 text-xs font-semibold rounded-full bg-green-100 text-green-800">
                                Completed
                            </span>
                        @elseif($migration->status === 'failed')
                            <span class="px-2 py-1 text-xs font-semibold rounded-full bg-red-100 text-red-800">
                                Failed
                            </span>
                        @elseif($migration->status === 'pending')
                            <span class="px-2 py-1 text-xs font-semibold rounded-full bg-yellow-100 text-yellow-800">
                                Pending
                            </span>
                        @elseif(in_array($migration->status, ['uploading', 'parsing', 'processing']))
                            <span class="px-2 py-1 text-xs font-semibold rounded-full bg-blue-100 text-blue-800">
                                In Progress
                            </span>
                        @elseif($migration->status === 'rolled_back')
                            <span class="px-2 py-1 text-xs font-semibold rounded-full bg-gray-100 text-gray-800">
                                Rolled Back
                            </span>
                        @endif
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <div class="flex items-center">
                            <div class="w-32 bg-gray-200 rounded-full h-2 mr-2">
                                <div class="bg-blue-600 h-2 rounded-full" style="width: {{ $migration->progress_percentage }}%"></div>
                            </div>
                            <span class="text-sm text-gray-700">{{ $migration->progress_percentage }}%</span>
                        </div>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                        {{ $migration->created_at->format('Y-m-d H:i') }}
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                        <a href="{{ route('admin.migration.show', $migration->id) }}"
                           class="text-blue-600 hover:text-blue-900 mr-3">View</a>

                        @if($migration->status === 'completed')
                            <a href="{{ route('admin.migration.report', $migration->id) }}"
                               class="text-green-600 hover:text-green-900 mr-3">Report</a>
                        @endif

                        @if($migration->status === 'pending')
                            <form action="{{ route('admin.migration.start', $migration->id) }}" method="POST" class="inline">
                                @csrf
                                <button type="submit" class="text-green-600 hover:text-green-900 mr-3">Start</button>
                            </form>
                        @endif

                        @if(in_array($migration->status, ['uploading', 'parsing', 'processing']))
                            <a href="{{ route('admin.migration.progress', $migration->id) }}"
                               class="text-purple-600 hover:text-purple-900 mr-3">Progress</a>
                        @endif

                        <form action="{{ route('admin.migration.destroy', $migration->id) }}" method="POST" class="inline"
                              onsubmit="return confirm('Are you sure you want to delete this migration?')">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="text-red-600 hover:text-red-900">Delete</button>
                        </form>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="7" class="px-6 py-12 text-center text-gray-500">
                        <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/>
                        </svg>
                        <p class="mt-4 text-lg">No migrations found</p>
                        <p class="mt-2 text-sm">Upload a cPanel backup to get started</p>
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <!-- Pagination -->
    @if($migrations->hasPages())
    <div class="mt-6">
        {{ $migrations->links() }}
    </div>
    @endif
</div>
@endsection
