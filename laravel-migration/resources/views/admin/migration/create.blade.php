@extends('layouts.admin')

@section('title', 'Upload cPanel Backup - WHM')

@section('content')
<div class="container mx-auto px-4 py-8 max-w-4xl">
    <!-- Header -->
    <div class="mb-8">
        <h1 class="text-3xl font-bold text-gray-900">Upload cPanel Backup</h1>
        <p class="text-gray-600 mt-2">Upload a cPanel/WHM backup file to migrate to VirPanel</p>
    </div>

    <!-- Alerts -->
    @if($errors->any())
    <div class="bg-red-50 border-l-4 border-red-500 p-4 mb-6">
        <div class="flex">
            <div class="flex-shrink-0">
                <svg class="h-5 w-5 text-red-400" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/>
                </svg>
            </div>
            <div class="ml-3">
                <h3 class="text-sm font-medium text-red-800">There were errors with your submission</h3>
                <ul class="mt-2 text-sm text-red-700 list-disc list-inside">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        </div>
    </div>
    @endif

    <!-- Information Box -->
    <div class="bg-blue-50 border-l-4 border-blue-500 p-6 mb-8">
        <div class="flex">
            <div class="flex-shrink-0">
                <svg class="h-5 w-5 text-blue-500" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/>
                </svg>
            </div>
            <div class="ml-3">
                <h3 class="text-sm font-medium text-blue-800">Important Information</h3>
                <div class="mt-2 text-sm text-blue-700">
                    <ul class="list-disc list-inside space-y-1">
                        <li>Supported formats: .tar.gz, .tgz (cPanel backup format)</li>
                        <li>Maximum file size: 10GB</li>
                        <li>The backup will be parsed to show a summary before migration</li>
                        <li>Email passwords will be preserved during migration</li>
                        <li>DNS zones, SSL certificates, and databases will be imported</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>

    <!-- Upload Form -->
    <div class="bg-white rounded-lg shadow p-8">
        <form action="{{ route('admin.migration.store') }}" method="POST" enctype="multipart/form-data" id="uploadForm">
            @csrf

            <!-- Source Type -->
            <div class="mb-6">
                <label class="block text-sm font-medium text-gray-700 mb-2">
                    Source Type
                </label>
                <select name="source_type" required
                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                    <option value="cpanel">cPanel</option>
                    <option value="whm">WHM (Full Account)</option>
                    <option value="plesk">Plesk (Experimental)</option>
                    <option value="directadmin">DirectAdmin (Experimental)</option>
                </select>
                <p class="mt-2 text-sm text-gray-500">
                    Select the source control panel type
                </p>
            </div>

            <!-- File Upload -->
            <div class="mb-6">
                <label class="block text-sm font-medium text-gray-700 mb-2">
                    Backup File
                </label>
                <div class="mt-1 flex justify-center px-6 pt-5 pb-6 border-2 border-gray-300 border-dashed rounded-lg hover:border-blue-400 transition">
                    <div class="space-y-1 text-center">
                        <svg class="mx-auto h-12 w-12 text-gray-400" stroke="currentColor" fill="none" viewBox="0 0 48 48">
                            <path d="M28 8H12a4 4 0 00-4 4v20m32-12v8m0 0v8a4 4 0 01-4 4H12a4 4 0 01-4-4v-4m32-4l-3.172-3.172a4 4 0 00-5.656 0L28 28M8 32l9.172-9.172a4 4 0 015.656 0L28 28m0 0l4 4m4-24h8m-4-4v8m-12 4h.02" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                        <div class="flex text-sm text-gray-600">
                            <label for="backup_file" class="relative cursor-pointer bg-white rounded-md font-medium text-blue-600 hover:text-blue-500 focus-within:outline-none focus-within:ring-2 focus-within:ring-offset-2 focus-within:ring-blue-500">
                                <span>Upload a file</span>
                                <input id="backup_file" name="backup_file" type="file" class="sr-only" required
                                       accept=".tar.gz,.tgz,.gz" onchange="updateFileName(this)">
                            </label>
                            <p class="pl-1">or drag and drop</p>
                        </div>
                        <p class="text-xs text-gray-500">
                            TAR.GZ, TGZ up to 10GB
                        </p>
                        <p id="file-name" class="text-sm font-medium text-gray-900 mt-2"></p>
                    </div>
                </div>
            </div>

            <!-- Submit Button -->
            <div class="flex items-center justify-between">
                <a href="{{ route('admin.migration.index') }}"
                   class="text-gray-600 hover:text-gray-900">
                    Cancel
                </a>
                <button type="submit"
                        class="bg-green-600 text-white px-8 py-3 rounded-lg hover:bg-green-700 transition font-medium">
                    Upload and Parse Backup
                </button>
            </div>
        </form>
    </div>

    <!-- What Happens Next -->
    <div class="mt-8 bg-gray-50 rounded-lg p-6">
        <h3 class="text-lg font-semibold text-gray-900 mb-4">What happens next?</h3>
        <ol class="space-y-3">
            <li class="flex items-start">
                <span class="flex-shrink-0 w-8 h-8 flex items-center justify-center rounded-full bg-blue-100 text-blue-600 font-semibold mr-3">1</span>
                <div>
                    <p class="font-medium text-gray-900">Backup Upload</p>
                    <p class="text-sm text-gray-600">Your backup file will be uploaded to the server</p>
                </div>
            </li>
            <li class="flex items-start">
                <span class="flex-shrink-0 w-8 h-8 flex items-center justify-center rounded-full bg-blue-100 text-blue-600 font-semibold mr-3">2</span>
                <div>
                    <p class="font-medium text-gray-900">Parsing & Validation</p>
                    <p class="text-sm text-gray-600">The backup will be extracted and parsed to generate a summary</p>
                </div>
            </li>
            <li class="flex items-start">
                <span class="flex-shrink-0 w-8 h-8 flex items-center justify-center rounded-full bg-blue-100 text-blue-600 font-semibold mr-3">3</span>
                <div>
                    <p class="font-medium text-gray-900">Review Summary</p>
                    <p class="text-sm text-gray-600">Review what will be migrated and start the migration process</p>
                </div>
            </li>
            <li class="flex items-start">
                <span class="flex-shrink-0 w-8 h-8 flex items-center justify-center rounded-full bg-blue-100 text-blue-600 font-semibold mr-3">4</span>
                <div>
                    <p class="font-medium text-gray-900">Migration</p>
                    <p class="text-sm text-gray-600">All data will be imported into VirPanel automatically</p>
                </div>
            </li>
        </ol>
    </div>
</div>

<script>
function updateFileName(input) {
    const fileName = document.getElementById('file-name');
    if (input.files.length > 0) {
        const file = input.files[0];
        const sizeMB = (file.size / 1024 / 1024).toFixed(2);
        fileName.textContent = `Selected: ${file.name} (${sizeMB} MB)`;
    } else {
        fileName.textContent = '';
    }
}
</script>
@endsection
