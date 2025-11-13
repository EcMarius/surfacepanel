@extends('layouts.app')

@section('title', 'Backup Codes')

@section('content')
<div class="container mx-auto px-4 py-8">
    <div class="max-w-2xl mx-auto">
        <h1 class="text-3xl font-bold mb-6">Two-Factor Backup Codes</h1>

        @if(session('success'))
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
                {{ session('success') }}
            </div>
        @endif

        @if(session('warning'))
            <div class="bg-yellow-100 border border-yellow-400 text-yellow-700 px-4 py-3 rounded mb-4">
                {{ session('warning') }}
            </div>
        @endif

        @if(session('error'))
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
                {{ session('error') }}
            </div>
        @endif

        @if(session('backup_codes'))
            <!-- Display newly generated backup codes -->
            <div class="bg-yellow-50 border-2 border-yellow-400 rounded-lg p-6 mb-6">
                <div class="flex items-start mb-4">
                    <svg class="w-6 h-6 text-yellow-600 mr-3 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                    </svg>
                    <div>
                        <h2 class="text-lg font-semibold text-yellow-900 mb-2">Save These Codes Now!</h2>
                        <p class="text-sm text-yellow-800">
                            These backup codes will only be shown once. Store them in a safe place. Each code can only be used once.
                        </p>
                    </div>
                </div>

                <div class="bg-white rounded-lg p-4 mb-4">
                    <div class="grid grid-cols-2 gap-3">
                        @foreach(session('backup_codes') as $code)
                            <code class="block bg-gray-50 px-3 py-2 rounded border border-gray-300 text-center font-mono select-all">
                                {{ $code }}
                            </code>
                        @endforeach
                    </div>
                </div>

                <div class="flex space-x-3">
                    <button onclick="printCodes()" class="flex-1 bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">
                        <svg class="w-4 h-4 inline mr-2" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M5 4v3H4a2 2 0 00-2 2v3a2 2 0 002 2h1v2a2 2 0 002 2h6a2 2 0 002-2v-2h1a2 2 0 002-2V9a2 2 0 00-2-2h-1V4a2 2 0 00-2-2H7a2 2 0 00-2 2zm8 0H7v3h6V4zm0 8H7v4h6v-4z" clip-rule="evenodd"/>
                        </svg>
                        Print Codes
                    </button>
                    <button onclick="downloadCodes()" class="flex-1 bg-gray-600 text-white px-4 py-2 rounded hover:bg-gray-700">
                        <svg class="w-4 h-4 inline mr-2" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M3 17a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zm3.293-7.707a1 1 0 011.414 0L9 10.586V3a1 1 0 112 0v7.586l1.293-1.293a1 1 0 111.414 1.414l-3 3a1 1 0 01-1.414 0l-3-3a1 1 0 010-1.414z" clip-rule="evenodd"/>
                        </svg>
                        Download as Text
                    </button>
                </div>
            </div>
        @endif

        <!-- Backup Codes Status -->
        <div class="bg-white shadow-md rounded-lg p-6 mb-6">
            <h2 class="text-xl font-semibold mb-4">Backup Codes Status</h2>

            <div class="flex items-center justify-between mb-4">
                <div>
                    <p class="text-gray-600">
                        You have <strong class="text-lg">{{ $backupCodesCount }}</strong> unused backup codes remaining.
                    </p>
                    @if($backupCodesCount <= 2)
                        <p class="text-orange-600 text-sm mt-1">
                            Warning: You're running low on backup codes. Consider generating new ones.
                        </p>
                    @endif
                </div>

                @if($backupCodesCount <= 2)
                    <svg class="w-12 h-12 text-orange-500" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                    </svg>
                @else
                    <svg class="w-12 h-12 text-green-500" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                    </svg>
                @endif
            </div>

            <div class="border-t pt-4">
                <button onclick="showRegenerateModal()" class="bg-orange-600 text-white px-4 py-2 rounded hover:bg-orange-700">
                    Regenerate Backup Codes
                </button>
                <p class="text-sm text-gray-500 mt-2">
                    Regenerating codes will invalidate all existing unused codes.
                </p>
            </div>
        </div>

        <!-- Information -->
        <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
            <h3 class="text-sm font-semibold text-blue-900 mb-2">About Backup Codes</h3>
            <ul class="text-sm text-blue-800 space-y-1">
                <li>• Each backup code can only be used once</li>
                <li>• Use them if you lose access to your authenticator app</li>
                <li>• Store them securely (e.g., in a password manager)</li>
                <li>• You can regenerate new codes at any time</li>
            </ul>
        </div>

        <div class="mt-4">
            <a href="{{ route('user.two-factor.index') }}" class="text-blue-600 hover:text-blue-800">
                ← Back to Two-Factor Settings
            </a>
        </div>
    </div>
</div>

<!-- Regenerate Codes Modal -->
<div id="regenerateModal" class="hidden fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50">
    <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
        <div class="mt-3">
            <h3 class="text-lg font-medium leading-6 text-gray-900 mb-4">Regenerate Backup Codes</h3>
            <p class="text-sm text-gray-500 mb-4">
                This will invalidate all your existing unused backup codes and generate 10 new ones. Please enter your password to confirm.
            </p>

            <form method="POST" action="{{ route('user.two-factor.regenerate-codes') }}">
                @csrf

                <div class="mb-4">
                    <label for="password" class="block text-sm font-medium text-gray-700 mb-2">Password</label>
                    <input type="password" name="password" id="password" required
                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>

                <div class="flex justify-end space-x-3">
                    <button type="button" onclick="hideRegenerateModal()"
                            class="px-4 py-2 bg-gray-200 text-gray-800 rounded hover:bg-gray-300">
                        Cancel
                    </button>
                    <button type="submit"
                            class="px-4 py-2 bg-orange-600 text-white rounded hover:bg-orange-700">
                        Regenerate Codes
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    function showRegenerateModal() {
        document.getElementById('regenerateModal').classList.remove('hidden');
    }

    function hideRegenerateModal() {
        document.getElementById('regenerateModal').classList.add('hidden');
    }

    function printCodes() {
        const codes = @json(session('backup_codes', []));
        const printWindow = window.open('', '', 'height=600,width=800');
        printWindow.document.write('<html><head><title>VirPanel Backup Codes</title>');
        printWindow.document.write('<style>body{font-family:monospace;padding:20px;}h1{font-size:18px;}code{display:block;margin:10px 0;}</style>');
        printWindow.document.write('</head><body>');
        printWindow.document.write('<h1>VirPanel Two-Factor Backup Codes</h1>');
        printWindow.document.write('<p>Generated: ' + new Date().toLocaleString() + '</p>');
        codes.forEach(code => {
            printWindow.document.write('<code>' + code + '</code>');
        });
        printWindow.document.write('</body></html>');
        printWindow.document.close();
        printWindow.print();
    }

    function downloadCodes() {
        const codes = @json(session('backup_codes', []));
        const content = 'VirPanel Two-Factor Backup Codes\n' +
                       'Generated: ' + new Date().toLocaleString() + '\n\n' +
                       codes.join('\n');

        const blob = new Blob([content], { type: 'text/plain' });
        const url = window.URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = 'virpanel-backup-codes.txt';
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        window.URL.revokeObjectURL(url);
    }
</script>
@endsection
