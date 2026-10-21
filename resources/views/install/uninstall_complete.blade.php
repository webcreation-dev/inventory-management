@extends('install.layout')

@section('title', 'Uninstall complete')

@section('steps')
    <nav class="flex items-center justify-center gap-2 mb-8" aria-label="Uninstall">
        <span class="text-center px-3 py-1 rounded-full bg-emerald-100 text-emerald-700 text-xs font-medium">Uninstall complete</span>
    </nav>
@endsection

@section('content')
    <div class="text-center py-4">
        <div class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-emerald-100 text-emerald-600 mb-4">
            <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
            </svg>
        </div>
        <h2 class="text-xl font-semibold text-slate-800 mb-2">Uninstall Successfully</h2>
        <p class="text-slate-600 text-sm mb-6 max-w-md mx-auto">
            The license has been removed from this installation. You can run the installer again when you are ready.
        </p>

        <a href="{{ $install_again_url }}" class="inline-flex items-center px-6 py-3 rounded-xl bg-indigo-600 text-white font-medium hover:bg-indigo-700 focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
            Install Again →
        </a>
    </div>
@endsection
