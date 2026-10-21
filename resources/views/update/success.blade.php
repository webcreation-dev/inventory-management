@extends('install.layout')

@section('title', __('Update Successful'))

@section('content')
    <div class="text-center py-2">
        <div class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-emerald-100 text-emerald-600 mb-4">
            <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
            </svg>
        </div>
        <h2 class="text-xl font-semibold text-slate-800 mb-2">{{ __('Update installed successfully') }}</h2>
        <p class="text-slate-600 text-sm mb-6 max-w-md mx-auto">
            {{ $successMessage ?? __('Installed successfully.') }}
        </p>
    </div>

    <div class="rounded-2xl border border-amber-200 bg-amber-50 p-4 mb-6 text-sm text-left">
        <p class="font-semibold text-amber-900 mb-2">{{ __('Important: update vendor files') }}</p>
        <p class="text-amber-800 mb-3">
            {{ __('New modules need an updated vendor folder. Download vendor.zip, extract it, and replace your existing vendor directory. Do this before using the app normally.') }}
        </p>
        <ol class="list-decimal list-inside space-y-1.5 text-amber-900/90 mb-4">
            <li>{{ __('Download vendor.zip using the button below.') }}</li>
            <li>{{ __('Extract the zip on your computer.') }}</li>
            <li>{{ __('Replace the project vendor folder with the extracted vendor folder.') }}</li>
            <li>{{ __('Click “I have updated vendor” to continue.') }}</li>
        </ol>

        <div class="flex flex-col sm:flex-row gap-3">
            <a href="{{ $vendorDownloadUrl }}"
               class="inline-flex items-center justify-center px-5 py-2.5 rounded-xl bg-indigo-600 text-white text-sm font-medium hover:bg-indigo-700 focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                {{ __('Download Vendor') }}
            </a>
            <form action="{{ url('/update/vendor-done') }}" method="POST" class="inline">
                @csrf
                <button type="submit"
                        class="w-full sm:w-auto inline-flex items-center justify-center px-5 py-2.5 rounded-xl bg-emerald-600 text-white text-sm font-medium hover:bg-emerald-700 focus:ring-2 focus:ring-offset-2 focus:ring-emerald-500">
                    {{ __('I have updated vendor') }}
                </button>
            </form>
        </div>
    </div>

    <p class="text-slate-500 text-xs text-center">
        <a href="{{ url('/') }}" class="text-indigo-600 hover:underline">← {{ __('Back to application') }}</a>
        @if($lpIsBd ?? false)
            &nbsp;|&nbsp;
            <a href="{{ url('/update/upgrade-license') }}" class="text-indigo-600 hover:underline">{{ __('Upgrade License') }}</a>
        @endif
    </p>
@endsection
