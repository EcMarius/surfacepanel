@extends('layouts.admin')

@section('title', '2FA Management')

@section('content')
<div class="container mx-auto px-4 py-8">
    <h1 class="text-3xl font-bold mb-6">Two-Factor Authentication Management</h1>

    <!-- Statistics Cards -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
        <!-- Total Users -->
        <div class="bg-white shadow-md rounded-lg p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-gray-500 text-sm">Total Users</p>
                    <p class="text-3xl font-bold text-gray-900">{{ $stats['total_users'] }}</p>
                </div>
                <div class="bg-blue-100 p-3 rounded-full">
                    <svg class="w-8 h-8 text-blue-600" fill="currentColor" viewBox="0 0 20 20">
                        <path d="M9 6a3 3 0 11-6 0 3 3 0 016 0zM17 6a3 3 0 11-6 0 3 3 0 016 0zM12.93 17c.046-.327.07-.66.07-1a6.97 6.97 0 00-1.5-4.33A5 5 0 0119 16v1h-6.07zM6 11a5 5 0 015 5v1H1v-1a5 5 0 015-5z"/>
                    </svg>
                </div>
            </div>
        </div>

        <!-- Users with 2FA -->
        <div class="bg-white shadow-md rounded-lg p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-gray-500 text-sm">2FA Enabled</p>
                    <p class="text-3xl font-bold text-green-600">{{ $stats['users_with_2fa'] }}</p>
                    <p class="text-xs text-gray-500 mt-1">
                        {{ $stats['total_users'] > 0 ? round(($stats['users_with_2fa'] / $stats['total_users']) * 100, 1) : 0 }}% adoption
                    </p>
                </div>
                <div class="bg-green-100 p-3 rounded-full">
                    <svg class="w-8 h-8 text-green-600" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z" clip-rule="evenodd"/>
                    </svg>
                </div>
            </div>
        </div>

        <!-- Pending Recoveries -->
        <div class="bg-white shadow-md rounded-lg p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-gray-500 text-sm">Pending Recoveries</p>
                    <p class="text-3xl font-bold text-orange-600">{{ $stats['pending_recoveries'] }}</p>
                    @if($stats['pending_recoveries'] > 0)
                        <p class="text-xs text-orange-500 mt-1">Requires attention</p>
                    @endif
                </div>
                <div class="bg-orange-100 p-3 rounded-full">
                    <svg class="w-8 h-8 text-orange-600" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                    </svg>
                </div>
            </div>
        </div>
    </div>

    <!-- Quick Actions -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
        <div class="bg-white shadow-md rounded-lg p-6">
            <h2 class="text-xl font-semibold mb-4">Quick Actions</h2>
            <div class="space-y-3">
                <a href="{{ route('admin.two-factor.users') }}"
                   class="block w-full text-left px-4 py-3 bg-blue-50 hover:bg-blue-100 rounded-lg transition">
                    <div class="flex items-center">
                        <svg class="w-5 h-5 text-blue-600 mr-3" fill="currentColor" viewBox="0 0 20 20">
                            <path d="M9 6a3 3 0 11-6 0 3 3 0 016 0zM17 6a3 3 0 11-6 0 3 3 0 016 0zM12.93 17c.046-.327.07-.66.07-1a6.97 6.97 0 00-1.5-4.33A5 5 0 0119 16v1h-6.07zM6 11a5 5 0 015 5v1H1v-1a5 5 0 015-5z"/>
                        </svg>
                        <span class="font-medium text-gray-900">Manage Users</span>
                    </div>
                </a>

                <a href="{{ route('admin.two-factor.recovery-requests') }}"
                   class="block w-full text-left px-4 py-3 bg-orange-50 hover:bg-orange-100 rounded-lg transition">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center">
                            <svg class="w-5 h-5 text-orange-600 mr-3" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                            </svg>
                            <span class="font-medium text-gray-900">Recovery Requests</span>
                        </div>
                        @if($stats['pending_recoveries'] > 0)
                            <span class="bg-orange-600 text-white text-xs font-bold px-2 py-1 rounded-full">
                                {{ $stats['pending_recoveries'] }}
                            </span>
                        @endif
                    </div>
                </a>

                <a href="{{ route('admin.two-factor.statistics') }}"
                   class="block w-full text-left px-4 py-3 bg-green-50 hover:bg-green-100 rounded-lg transition">
                    <div class="flex items-center">
                        <svg class="w-5 h-5 text-green-600 mr-3" fill="currentColor" viewBox="0 0 20 20">
                            <path d="M2 11a1 1 0 011-1h2a1 1 0 011 1v5a1 1 0 01-1 1H3a1 1 0 01-1-1v-5zM8 7a1 1 0 011-1h2a1 1 0 011 1v9a1 1 0 01-1 1H9a1 1 0 01-1-1V7zM14 4a1 1 0 011-1h2a1 1 0 011 1v12a1 1 0 01-1 1h-2a1 1 0 01-1-1V4z"/>
                        </svg>
                        <span class="font-medium text-gray-900">View Statistics</span>
                    </div>
                </a>
            </div>
        </div>

        <!-- Recent Recovery Requests -->
        <div class="bg-white shadow-md rounded-lg p-6">
            <h2 class="text-xl font-semibold mb-4">Recent Recovery Requests</h2>
            @if($recentRecoveries->count() > 0)
                <div class="space-y-3">
                    @foreach($recentRecoveries->take(5) as $recovery)
                        <div class="border-l-4 pl-4 py-2
                            @if($recovery->status === 'pending') border-orange-500
                            @elseif($recovery->status === 'approved') border-green-500
                            @elseif($recovery->status === 'denied') border-red-500
                            @else border-gray-500
                            @endif">
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="font-medium text-gray-900">{{ $recovery->user->email }}</p>
                                    <p class="text-xs text-gray-500">{{ $recovery->created_at->diffForHumans() }}</p>
                                </div>
                                <span class="px-2 py-1 text-xs font-semibold rounded-full
                                    @if($recovery->status === 'pending') bg-orange-100 text-orange-800
                                    @elseif($recovery->status === 'approved') bg-green-100 text-green-800
                                    @elseif($recovery->status === 'denied') bg-red-100 text-red-800
                                    @else bg-gray-100 text-gray-800
                                    @endif">
                                    {{ ucfirst($recovery->status) }}
                                </span>
                            </div>
                        </div>
                    @endforeach
                </div>

                @if($recentRecoveries->count() > 5)
                    <a href="{{ route('admin.two-factor.recovery-requests') }}"
                       class="block text-center text-blue-600 hover:text-blue-800 text-sm mt-4">
                        View all requests →
                    </a>
                @endif
            @else
                <p class="text-gray-500 text-center py-8">No recovery requests</p>
            @endif
        </div>
    </div>

    <!-- 2FA Policy Settings -->
    <div class="bg-white shadow-md rounded-lg p-6">
        <h2 class="text-xl font-semibold mb-4">2FA Policy Settings</h2>
        <p class="text-gray-600 mb-4">Configure which user roles are required to have two-factor authentication enabled.</p>

        <form method="POST" action="{{ route('admin.two-factor.enforce-policy') }}">
            @csrf

            <div class="space-y-3 mb-6">
                <label class="flex items-center">
                    <input type="checkbox" name="roles[]" value="root" class="rounded text-blue-600">
                    <span class="ml-2 text-gray-900">Root Users</span>
                    <span class="ml-2 text-xs text-gray-500">(Highest privilege level)</span>
                </label>

                <label class="flex items-center">
                    <input type="checkbox" name="roles[]" value="admin" class="rounded text-blue-600">
                    <span class="ml-2 text-gray-900">Admin Users</span>
                    <span class="ml-2 text-xs text-gray-500">(Administrative access)</span>
                </label>

                <label class="flex items-center">
                    <input type="checkbox" name="roles[]" value="reseller" class="rounded text-blue-600">
                    <span class="ml-2 text-gray-900">Reseller Users</span>
                </label>

                <label class="flex items-center">
                    <input type="checkbox" name="roles[]" value="user" class="rounded text-blue-600">
                    <span class="ml-2 text-gray-900">Regular Users</span>
                </label>
            </div>

            <button type="submit" class="bg-blue-600 text-white px-6 py-2 rounded hover:bg-blue-700">
                Update Policy
            </button>
        </form>
    </div>
</div>
@endsection
