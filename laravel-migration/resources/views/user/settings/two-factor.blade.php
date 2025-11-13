@extends('layouts.app')

@section('title', 'Two-Factor Authentication')

@section('content')
<div class="container mx-auto px-4 py-8">
    <div class="max-w-4xl mx-auto">
        <h1 class="text-3xl font-bold mb-6">Two-Factor Authentication</h1>

        @if(session('success'))
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
                {{ session('success') }}
            </div>
        @endif

        @if(session('error'))
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
                {{ session('error') }}
            </div>
        @endif

        <div class="bg-white shadow-md rounded-lg p-6 mb-6">
            <div class="flex items-start justify-between">
                <div class="flex-1">
                    <h2 class="text-xl font-semibold mb-2">
                        Status:
                        @if($user->hasTwoFactorEnabled())
                            <span class="text-green-600">Enabled</span>
                        @else
                            <span class="text-red-600">Disabled</span>
                        @endif
                    </h2>

                    <p class="text-gray-600 mb-4">
                        Two-factor authentication adds an additional layer of security to your account by requiring more than just a password to sign in.
                    </p>

                    @if($user->hasTwoFactorEnabled())
                        <div class="mb-4">
                            <p class="text-sm text-gray-600">
                                <strong>Enabled on:</strong> {{ $user->two_factor_confirmed_at->format('M d, Y H:i') }}
                            </p>
                            @if($twoFactorAuth && $twoFactorAuth->last_used_at)
                                <p class="text-sm text-gray-600">
                                    <strong>Last used:</strong> {{ $twoFactorAuth->last_used_at->diffForHumans() }}
                                </p>
                            @endif
                            <p class="text-sm text-gray-600">
                                <strong>Backup codes remaining:</strong> {{ $backupCodesCount }}
                            </p>
                        </div>
                    @endif
                </div>

                <div>
                    @if($user->hasTwoFactorEnabled())
                        <span class="inline-flex items-center px-4 py-2 bg-green-100 text-green-800 rounded-full">
                            <svg class="w-5 h-5 mr-2" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                            </svg>
                            Active
                        </span>
                    @else
                        <span class="inline-flex items-center px-4 py-2 bg-gray-100 text-gray-800 rounded-full">
                            <svg class="w-5 h-5 mr-2" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/>
                            </svg>
                            Inactive
                        </span>
                    @endif
                </div>
            </div>
        </div>

        @if(!$user->hasTwoFactorEnabled())
            <!-- Enable 2FA Section -->
            <div class="bg-white shadow-md rounded-lg p-6">
                <h2 class="text-xl font-semibold mb-4">Enable Two-Factor Authentication</h2>
                <p class="text-gray-600 mb-4">
                    To enable two-factor authentication, you'll need to scan a QR code with your authenticator app (Google Authenticator, Authy, or similar).
                </p>
                <a href="{{ route('user.two-factor.setup') }}" class="inline-block bg-blue-600 text-white px-6 py-2 rounded hover:bg-blue-700">
                    Get Started
                </a>
            </div>
        @else
            <!-- Manage 2FA Section -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <!-- Backup Codes -->
                <div class="bg-white shadow-md rounded-lg p-6">
                    <h3 class="text-lg font-semibold mb-3">Backup Codes</h3>
                    <p class="text-gray-600 text-sm mb-4">
                        Backup codes can be used to access your account if you lose access to your authenticator device.
                    </p>
                    <a href="{{ route('user.two-factor.backup-codes') }}" class="inline-block bg-gray-600 text-white px-4 py-2 rounded hover:bg-gray-700 text-sm">
                        View Backup Codes
                    </a>
                </div>

                <!-- Disable 2FA -->
                <div class="bg-white shadow-md rounded-lg p-6">
                    <h3 class="text-lg font-semibold mb-3">Disable 2FA</h3>
                    <p class="text-gray-600 text-sm mb-4">
                        Remove two-factor authentication from your account. This will make your account less secure.
                    </p>
                    <button onclick="showDisableModal()" class="inline-block bg-red-600 text-white px-4 py-2 rounded hover:bg-red-700 text-sm">
                        Disable 2FA
                    </button>
                </div>
            </div>
        @endif
    </div>
</div>

<!-- Disable 2FA Modal -->
@if($user->hasTwoFactorEnabled())
<div id="disableModal" class="hidden fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50">
    <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
        <div class="mt-3">
            <h3 class="text-lg font-medium leading-6 text-gray-900 mb-4">Disable Two-Factor Authentication</h3>
            <p class="text-sm text-gray-500 mb-4">
                Please enter your password to confirm that you want to disable two-factor authentication.
            </p>

            <form method="POST" action="{{ route('user.two-factor.disable') }}">
                @csrf
                @method('DELETE')

                <div class="mb-4">
                    <label for="password" class="block text-sm font-medium text-gray-700 mb-2">Password</label>
                    <input type="password" name="password" id="password" required
                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>

                <div class="flex justify-end space-x-3">
                    <button type="button" onclick="hideDisableModal()"
                            class="px-4 py-2 bg-gray-200 text-gray-800 rounded hover:bg-gray-300">
                        Cancel
                    </button>
                    <button type="submit"
                            class="px-4 py-2 bg-red-600 text-white rounded hover:bg-red-700">
                        Disable 2FA
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    function showDisableModal() {
        document.getElementById('disableModal').classList.remove('hidden');
    }

    function hideDisableModal() {
        document.getElementById('disableModal').classList.add('hidden');
    }
</script>
@endif
@endsection
