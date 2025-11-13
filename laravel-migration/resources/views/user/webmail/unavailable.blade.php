@extends('layouts.user')

@section('title', 'Webmail Unavailable')

@section('content')
<div class="container mx-auto px-4 py-8">
    <div class="max-w-2xl mx-auto">
        <!-- Unavailable Message -->
        <div class="bg-red-50 border-l-4 border-red-400 rounded-lg p-8 text-center">
            <div class="flex justify-center mb-6">
                <div class="bg-red-100 rounded-full p-6">
                    <svg class="w-16 h-16 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </div>
            </div>
            <h2 class="text-2xl font-bold text-gray-900 mb-4">Webmail Unavailable</h2>
            <p class="text-lg text-gray-700 mb-6">{{ $message }}</p>
            <a href="{{ route('user.dashboard') }}" class="inline-flex items-center bg-blue-600 text-white px-6 py-3 rounded-lg hover:bg-blue-700 transition">
                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                </svg>
                Back to Dashboard
            </a>
        </div>
    </div>
</div>
@endsection
