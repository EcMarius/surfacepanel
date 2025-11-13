@extends('layouts.app')

@section('title', 'Two-Factor Authentication')

@section('content')
<div class="min-h-screen flex items-center justify-center bg-gray-100 px-4">
    <div class="max-w-md w-full">
        <div class="text-center mb-8">
            <h1 class="text-3xl font-bold text-gray-900 mb-2">Two-Factor Authentication</h1>
            <p class="text-gray-600">Enter your authentication code to continue</p>
        </div>

        @if($errors->any())
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
                @foreach($errors->all() as $error)
                    <p>{{ $error }}</p>
                @endforeach
            </div>
        @endif

        @if(session('error'))
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
                {{ session('error') }}
            </div>
        @endif

        <div class="bg-white shadow-lg rounded-lg p-8">
            <!-- Authenticator Code Form -->
            <div id="authenticatorForm">
                <form method="POST" action="{{ route('two-factor.verify') }}">
                    @csrf

                    <div class="mb-6">
                        <label for="code" class="block text-sm font-medium text-gray-700 mb-2">
                            Authentication Code
                        </label>
                        <input type="text"
                               name="code"
                               id="code"
                               maxlength="6"
                               pattern="[0-9]{6}"
                               required
                               autofocus
                               class="w-full px-4 py-3 text-center text-2xl font-mono border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                               placeholder="000000">
                        <p class="text-xs text-gray-500 mt-2">Enter the 6-digit code from your authenticator app</p>
                    </div>

                    <button type="submit"
                            class="w-full bg-blue-600 text-white py-3 px-4 rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 font-medium">
                        Verify
                    </button>
                </form>

                <div class="mt-6 text-center">
                    <button onclick="showBackupCodeForm()"
                            class="text-sm text-blue-600 hover:text-blue-800">
                        Use a backup code instead
                    </button>
                </div>
            </div>

            <!-- Backup Code Form (hidden by default) -->
            <div id="backupCodeForm" class="hidden">
                <form method="POST" action="{{ route('two-factor.verify') }}">
                    @csrf

                    <div class="mb-6">
                        <label for="backup_code" class="block text-sm font-medium text-gray-700 mb-2">
                            Backup Code
                        </label>
                        <input type="text"
                               name="code"
                               id="backup_code"
                               required
                               class="w-full px-4 py-3 text-center font-mono border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                               placeholder="XXXX-XXXX">
                        <p class="text-xs text-gray-500 mt-2">Enter one of your backup codes</p>
                    </div>

                    <button type="submit"
                            class="w-full bg-blue-600 text-white py-3 px-4 rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 font-medium">
                        Verify Backup Code
                    </button>
                </form>

                <div class="mt-6 text-center">
                    <button onclick="showAuthenticatorForm()"
                            class="text-sm text-blue-600 hover:text-blue-800">
                        Use authenticator code instead
                    </button>
                </div>
            </div>

            <div class="mt-6 pt-6 border-t border-gray-200">
                <div class="text-center">
                    <p class="text-sm text-gray-600 mb-2">Lost access to your authenticator?</p>
                    <a href="{{ route('two-factor.recovery-request') }}"
                       class="text-sm text-blue-600 hover:text-blue-800">
                        Request account recovery
                    </a>
                </div>
            </div>
        </div>

        <div class="mt-6 text-center">
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="text-sm text-gray-600 hover:text-gray-800">
                    Sign out
                </button>
            </form>
        </div>

        <!-- Information Box -->
        <div class="mt-6 bg-blue-50 border border-blue-200 rounded-lg p-4">
            <div class="flex items-start">
                <svg class="w-5 h-5 text-blue-600 mr-3 flex-shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/>
                </svg>
                <div class="text-sm text-blue-800">
                    <p class="font-semibold mb-1">Security Notice</p>
                    <p>Two-factor authentication adds an extra layer of security to your account. Never share your authentication codes with anyone.</p>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    // Auto-submit when 6 digits are entered for authenticator code
    const codeInput = document.getElementById('code');
    if (codeInput) {
        codeInput.addEventListener('input', function(e) {
            if (e.target.value.length === 6) {
                e.target.form.submit();
            }
        });

        // Only allow numbers
        codeInput.addEventListener('keypress', function(e) {
            if (!/[0-9]/.test(e.key)) {
                e.preventDefault();
            }
        });
    }

    function showBackupCodeForm() {
        document.getElementById('authenticatorForm').classList.add('hidden');
        document.getElementById('backupCodeForm').classList.remove('hidden');
        document.getElementById('backup_code').focus();
    }

    function showAuthenticatorForm() {
        document.getElementById('backupCodeForm').classList.add('hidden');
        document.getElementById('authenticatorForm').classList.remove('hidden');
        document.getElementById('code').focus();
    }
</script>
@endsection
