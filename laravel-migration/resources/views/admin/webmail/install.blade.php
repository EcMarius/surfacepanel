@extends('layouts.admin')

@section('title', 'Install Roundcube Webmail - WHM')

@section('content')
<div class="container mx-auto px-4 py-8 max-w-4xl">
    <!-- Header -->
    <div class="mb-8">
        <h1 class="text-3xl font-bold text-gray-900">Install Roundcube Webmail</h1>
        <p class="text-gray-600 mt-2">Set up webmail access for your users</p>
    </div>

    <!-- Installation Wizard -->
    <div class="bg-white rounded-lg shadow-lg p-8">
        <div class="mb-8">
            <div class="flex items-center mb-4">
                <div class="flex items-center justify-center w-12 h-12 rounded-full bg-blue-100 text-blue-600">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                    </svg>
                </div>
                <div class="ml-4">
                    <h2 class="text-xl font-semibold text-gray-900">Roundcube Webmail Installation</h2>
                    <p class="text-sm text-gray-600">Configure your webmail settings</p>
                </div>
            </div>
        </div>

        <!-- Installation Info -->
        <div class="bg-blue-50 border-l-4 border-blue-400 p-4 mb-8">
            <div class="flex">
                <svg class="w-5 h-5 text-blue-600 mr-3 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                <div class="text-sm text-blue-700">
                    <p class="font-semibold mb-2">What will be installed:</p>
                    <ul class="list-disc list-inside space-y-1">
                        <li>Roundcube Webmail (latest stable version)</li>
                        <li>Database and configuration files</li>
                        <li>SSO integration for seamless login</li>
                        <li>Essential plugins and themes</li>
                    </ul>
                </div>
            </div>
        </div>

        <!-- Installation Form -->
        <form id="installForm" class="space-y-6">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">
                    Installation Path
                    <span class="text-red-500">*</span>
                </label>
                <input type="text" name="installation_path" value="{{ $config->installation_path }}" required
                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                <p class="text-sm text-gray-500 mt-1">Directory where Roundcube will be installed</p>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">
                    Database Name
                    <span class="text-red-500">*</span>
                </label>
                <input type="text" name="database_name" value="{{ $config->database_name }}" required
                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                <p class="text-sm text-gray-500 mt-1">Database will be created automatically</p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        IMAP Host
                        <span class="text-red-500">*</span>
                    </label>
                    <input type="text" name="imap_host" value="{{ $config->imap_host }}" required
                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                    <p class="text-sm text-gray-500 mt-1">e.g., localhost:143</p>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        SMTP Host
                        <span class="text-red-500">*</span>
                    </label>
                    <input type="text" name="smtp_host" value="{{ $config->smtp_host }}" required
                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                    <p class="text-sm text-gray-500 mt-1">e.g., localhost:587</p>
                </div>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">
                    Product Name
                </label>
                <input type="text" name="product_name" value="{{ $config->product_name }}"
                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                <p class="text-sm text-gray-500 mt-1">Branding name for the webmail interface</p>
            </div>

            <!-- Installation Progress (hidden initially) -->
            <div id="installProgress" class="hidden">
                <div class="bg-gray-50 rounded-lg p-6">
                    <div class="flex items-center mb-4">
                        <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600 mr-4"></div>
                        <div>
                            <h3 class="font-semibold text-gray-900">Installing Roundcube...</h3>
                            <p class="text-sm text-gray-600" id="progressText">Preparing installation</p>
                        </div>
                    </div>
                    <div class="w-full bg-gray-200 rounded-full h-2">
                        <div id="progressBar" class="bg-blue-600 h-2 rounded-full transition-all duration-500" style="width: 0%"></div>
                    </div>
                </div>
            </div>

            <!-- Installation Output (hidden initially) -->
            <div id="installOutput" class="hidden bg-gray-900 rounded-lg p-4 text-white font-mono text-sm max-h-96 overflow-y-auto">
            </div>

            <!-- Action Buttons -->
            <div class="flex justify-between pt-6 border-t">
                <a href="{{ route('admin.webmail.index') }}" class="px-6 py-2 bg-gray-300 text-gray-700 rounded-lg hover:bg-gray-400 transition">
                    Cancel
                </a>
                <button type="submit" id="installBtn" class="px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition flex items-center">
                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                    </svg>
                    Install Roundcube
                </button>
            </div>
        </form>
    </div>

    <!-- Requirements Check -->
    <div class="mt-8 bg-white rounded-lg shadow p-6">
        <h3 class="text-lg font-semibold text-gray-900 mb-4">System Requirements</h3>
        <div class="space-y-2">
            <div class="flex items-center text-sm">
                <svg class="w-5 h-5 text-green-600 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                </svg>
                <span class="text-gray-700">PHP 7.4 or higher</span>
            </div>
            <div class="flex items-center text-sm">
                <svg class="w-5 h-5 text-green-600 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                </svg>
                <span class="text-gray-700">MySQL/MariaDB database</span>
            </div>
            <div class="flex items-center text-sm">
                <svg class="w-5 h-5 text-green-600 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                </svg>
                <span class="text-gray-700">IMAP/SMTP server configured</span>
            </div>
            <div class="flex items-center text-sm">
                <svg class="w-5 h-5 text-green-600 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                </svg>
                <span class="text-gray-700">Web server (Nginx/Apache)</span>
            </div>
        </div>
    </div>
</div>

<script>
document.getElementById('installForm').addEventListener('submit', function(e) {
    e.preventDefault();

    const formData = new FormData(this);
    const data = Object.fromEntries(formData);

    // Show progress
    document.getElementById('installBtn').disabled = true;
    document.getElementById('installProgress').classList.remove('hidden');

    const progressBar = document.getElementById('progressBar');
    const progressText = document.getElementById('progressText');
    const outputDiv = document.getElementById('installOutput');

    // Simulate progress
    let progress = 0;
    const progressInterval = setInterval(() => {
        progress += 5;
        if (progress <= 90) {
            progressBar.style.width = progress + '%';
        }
    }, 500);

    // Update progress text
    setTimeout(() => progressText.textContent = 'Downloading Roundcube...', 1000);
    setTimeout(() => progressText.textContent = 'Extracting files...', 3000);
    setTimeout(() => progressText.textContent = 'Creating database...', 5000);
    setTimeout(() => progressText.textContent = 'Configuring Roundcube...', 7000);

    fetch('{{ route("admin.webmail.install") }}', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': '{{ csrf_token() }}'
        },
        body: JSON.stringify(data)
    })
    .then(response => response.json())
    .then(result => {
        clearInterval(progressInterval);
        progressBar.style.width = '100%';

        if (result.success) {
            progressText.textContent = 'Installation completed successfully!';

            // Show output
            if (result.output) {
                outputDiv.classList.remove('hidden');
                outputDiv.textContent = result.output;
            }

            // Redirect after 3 seconds
            setTimeout(() => {
                window.location.href = '{{ route("admin.webmail.index") }}';
            }, 3000);
        } else {
            progressText.textContent = 'Installation failed!';
            progressBar.classList.remove('bg-blue-600');
            progressBar.classList.add('bg-red-600');

            // Show error output
            outputDiv.classList.remove('hidden');
            outputDiv.textContent = result.output || result.message;

            document.getElementById('installBtn').disabled = false;

            alert('Installation failed: ' + result.message);
        }
    })
    .catch(error => {
        clearInterval(progressInterval);
        progressText.textContent = 'Installation failed!';
        progressBar.classList.remove('bg-blue-600');
        progressBar.classList.add('bg-red-600');

        alert('Error: ' + error.message);
        document.getElementById('installBtn').disabled = false;
    });
});
</script>
@endsection
