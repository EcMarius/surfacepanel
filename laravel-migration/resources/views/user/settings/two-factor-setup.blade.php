@extends('layouts.app')

@section('title', 'Setup Two-Factor Authentication')

@section('content')
<div class="container mx-auto px-4 py-8">
    <div class="max-w-2xl mx-auto">
        <h1 class="text-3xl font-bold mb-6">Setup Two-Factor Authentication</h1>

        @if(session('error'))
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
                {{ session('error') }}
            </div>
        @endif

        <div class="bg-white shadow-md rounded-lg p-6">
            <div class="mb-6">
                <h2 class="text-xl font-semibold mb-4">Step 1: Scan QR Code</h2>
                <p class="text-gray-600 mb-4">
                    Scan this QR code with your authenticator app (Google Authenticator, Authy, Microsoft Authenticator, etc.).
                </p>

                <div class="flex justify-center mb-6">
                    <img src="https://api.qrserver.com/v1/create-qr-code/?size=250x250&data={{ urlencode($qrCodeUrl) }}"
                         alt="2FA QR Code"
                         class="border-4 border-gray-300 rounded-lg">
                </div>

                <div class="bg-gray-100 p-4 rounded">
                    <p class="text-sm text-gray-700 mb-2"><strong>Can't scan the code?</strong></p>
                    <p class="text-sm text-gray-600 mb-2">Enter this key manually in your app:</p>
                    <code class="block bg-white px-3 py-2 rounded border border-gray-300 text-center font-mono select-all">
                        {{ $secret }}
                    </code>
                </div>
            </div>

            <div class="border-t pt-6">
                <h2 class="text-xl font-semibold mb-4">Step 2: Verify Setup</h2>
                <p class="text-gray-600 mb-4">
                    Enter the 6-digit code from your authenticator app to verify the setup.
                </p>

                <form method="POST" action="{{ route('user.two-factor.enable') }}">
                    @csrf

                    <div class="mb-4">
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
                               class="w-full px-4 py-3 text-center text-2xl font-mono border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 @error('code') border-red-500 @enderror"
                               placeholder="000000">
                        @error('code')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="flex justify-between items-center">
                        <a href="{{ route('user.two-factor.index') }}"
                           class="text-gray-600 hover:text-gray-800">
                            Cancel
                        </a>
                        <button type="submit"
                                class="bg-blue-600 text-white px-6 py-2 rounded hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500">
                            Verify & Enable
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <div class="mt-6 bg-blue-50 border border-blue-200 rounded-lg p-4">
            <h3 class="text-sm font-semibold text-blue-900 mb-2">Important Notes</h3>
            <ul class="text-sm text-blue-800 space-y-1">
                <li>• Make sure your device's time is accurate (codes are time-based)</li>
                <li>• Save your backup codes after enabling 2FA</li>
                <li>• Keep your authenticator app secure</li>
                <li>• Don't share your secret key with anyone</li>
            </ul>
        </div>
    </div>
</div>

<script>
    // Auto-submit when 6 digits are entered
    document.getElementById('code').addEventListener('input', function(e) {
        if (e.target.value.length === 6) {
            e.target.form.submit();
        }
    });

    // Only allow numbers
    document.getElementById('code').addEventListener('keypress', function(e) {
        if (!/[0-9]/.test(e.key)) {
            e.preventDefault();
        }
    });
</script>
@endsection
