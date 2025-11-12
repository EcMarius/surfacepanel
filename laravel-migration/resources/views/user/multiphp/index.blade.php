@extends('layouts.user')

@section('title', 'MultiPHP Manager')

@section('content')
<div class="container mx-auto px-4 py-8">
    <!-- Header -->
    <div class="mb-8">
        <h1 class="text-3xl font-bold text-gray-900">MultiPHP Manager</h1>
        <p class="text-gray-600 mt-2">Select PHP versions for your domains</p>
    </div>

    <!-- Info Notice -->
    <div class="bg-blue-50 border-l-4 border-blue-500 p-6 mb-8">
        <div class="flex items-start">
            <svg class="w-6 h-6 text-blue-500 mr-3 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
            <div>
                <h3 class="text-lg font-semibold text-blue-800 mb-2">PHP Version Selection</h3>
                <p class="text-blue-700">
                    Choose a PHP version for each domain. Different applications may require specific PHP versions.
                    @if($defaultVersion)
                    <br><strong>Default:</strong> PHP {{ $defaultVersion->version }}
                    @endif
                </p>
            </div>
        </div>
    </div>

    <!-- Domains Table -->
    <div class="bg-white rounded-lg shadow overflow-hidden">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                        Domain
                    </th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                        Type
                    </th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                        Current PHP Version
                    </th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                        Actions
                    </th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                @forelse($domains as $domainInfo)
                @php
                    $domain = $domainInfo['domain'];
                    $currentSetting = $domainSettings[$domain] ?? null;
                    $currentVersion = $currentSetting ? $currentSetting->phpVersion : $defaultVersion;
                @endphp
                <tr>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <div class="flex items-center">
                            <svg class="w-5 h-5 text-gray-400 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 01-9 9m9-9a9 9 0 00-9-9m9 9H3m9 9a9 9 0 01-9-9m9 9c1.657 0 3-4.03 3-9s-1.343-9-3-9m0 18c-1.657 0-3-4.03-3-9s1.343-9 3-9m-9 9a9 9 0 019-9"/>
                            </svg>
                            <div>
                                <div class="text-sm font-medium text-gray-900">{{ $domain }}</div>
                                <div class="text-xs text-gray-500 font-mono">{{ $domainInfo['document_root'] }}</div>
                            </div>
                        </div>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        @if($domainInfo['type'] === 'main')
                        <span class="px-2 py-1 inline-flex text-xs leading-5 font-semibold rounded-full bg-blue-100 text-blue-800">
                            Main Domain
                        </span>
                        @elseif($domainInfo['type'] === 'addon')
                        <span class="px-2 py-1 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">
                            Addon Domain
                        </span>
                        @else
                        <span class="px-2 py-1 inline-flex text-xs leading-5 font-semibold rounded-full bg-purple-100 text-purple-800">
                            Subdomain
                        </span>
                        @endif
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <div class="flex items-center">
                            <select id="php-version-{{ $loop->index }}"
                                    class="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                    onchange="changeVersion('{{ $domain }}', this.value, {{ $loop->index }})">
                                @foreach($phpVersions as $phpVersion)
                                <option value="{{ $phpVersion->id }}"
                                        {{ $currentVersion && $currentVersion->id === $phpVersion->id ? 'selected' : '' }}>
                                    PHP {{ $phpVersion->version }}
                                    @if($phpVersion->is_default) (Default) @endif
                                </option>
                                @endforeach
                            </select>
                            <div id="loading-{{ $loop->index }}" class="ml-3 hidden">
                                <svg class="animate-spin h-5 w-5 text-blue-600" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                            </div>
                        </div>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                        <a href="{{ route('user.multiphp.ini-editor', ['domain' => $domain]) }}"
                           class="text-blue-600 hover:text-blue-900 mr-4">
                            PHP.ini Editor
                        </a>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="4" class="px-6 py-12 text-center text-gray-500">
                        <svg class="w-16 h-16 mx-auto text-gray-400 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.172 16.172a4 4 0 015.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        <p class="text-lg font-semibold mb-2">No domains found</p>
                        <p class="text-sm">Add domains to manage PHP versions</p>
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <!-- PHP Version Information -->
    <div class="mt-8 bg-white rounded-lg shadow p-6">
        <h2 class="text-xl font-semibold text-gray-900 mb-4">Available PHP Versions</h2>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
            @foreach($phpVersions as $phpVersion)
            <div class="border rounded-lg p-4 {{ $phpVersion->is_default ? 'border-blue-300 bg-blue-50' : 'border-gray-200' }}">
                <h3 class="font-semibold text-gray-900 mb-2">
                    PHP {{ $phpVersion->version }}
                    @if($phpVersion->is_default)
                    <span class="text-xs bg-blue-600 text-white px-2 py-1 rounded ml-2">DEFAULT</span>
                    @endif
                </h3>
                <p class="text-sm text-gray-600 mb-2">{{ count($phpVersion->extensions ?? []) }} extensions loaded</p>
                <div class="text-xs text-gray-500">
                    Common for:
                    @if(version_compare($phpVersion->version, '8.0', '>='))
                        <span class="font-medium">Laravel 10+, WordPress 6.x, Modern apps</span>
                    @elseif(version_compare($phpVersion->version, '7.4', '>='))
                        <span class="font-medium">WordPress 5.x, Laravel 8-9, Joomla 4</span>
                    @else
                        <span class="font-medium">Legacy applications</span>
                    @endif
                </div>
            </div>
            @endforeach
        </div>
    </div>
</div>

<script>
function changeVersion(domain, phpVersionId, index) {
    const loadingEl = document.getElementById(`loading-${index}`);
    const selectEl = document.getElementById(`php-version-${index}`);

    loadingEl.classList.remove('hidden');
    selectEl.disabled = true;

    fetch('{{ route('user.multiphp.set-version') }}', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
        },
        body: JSON.stringify({
            domain: domain,
            php_version_id: phpVersionId
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert(`✓ ${data.message}\n\nPHP-FPM has been reloaded. Your domain is now using ${data.php_version}.`);
        } else {
            alert('Error: ' + data.message);
            // Revert selection
            location.reload();
        }
    })
    .catch(error => {
        alert('Error changing PHP version. Please try again.');
        location.reload();
    })
    .finally(() => {
        loadingEl.classList.add('hidden');
        selectEl.disabled = false;
    });
}
</script>
@endsection
