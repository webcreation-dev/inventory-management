@extends('ecommerce::layouts.app')

@section('content')
    <section class="mx-auto max-w-xl px-4 py-14 sm:px-6 lg:px-8">
        <div class="ec-panel overflow-hidden">
            <div class="ec-offer-shell !rounded-none !border-0 !shadow-none px-8 py-8">
                <p class="relative z-[1] text-[11px] font-bold uppercase tracking-[0.18em] text-[var(--accent-strong)]">{{ __('Account recovery') }}</p>
                <h2 class="ec-display relative z-[1] mt-2 text-2xl font-bold text-slate-900">{{ __('Forgot Password') }}</h2>
                <p class="relative z-[1] mt-2 text-sm text-slate-600">{{ __('Enter your account email and we will send a reset link') }}</p>
            </div>
            <div class="border-t border-[rgba(var(--accent-rgb),0.18)] p-8 sm:p-10">
                @if(session('success'))
                    <p class="mb-4 rounded-xl bg-emerald-50 px-3 py-2 text-sm text-emerald-700">{{ session('success') }}</p>
                @endif
                @if(session('error'))
                    <p class="mb-4 rounded-xl bg-rose-50 px-3 py-2 text-sm text-rose-700">{{ session('error') }}</p>
                @endif

                <form method="POST" action="{{ route('ecommerce.forgot-password.submit') }}" class="space-y-4" novalidate>
                    @csrf
                    <div>
                        <label class="ec-label">{{ __('Email Address') }}</label>
                        <input type="email" name="email" value="{{ old('email') }}" placeholder="you@example.com"
                               class="ec-input @error('email') border-rose-400 @enderror">
                        @error('email')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
                    </div>
                    <button type="submit" class="ec-btn ec-btn-primary ec-btn-block !py-3.5">
                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16v12H4zM4 8l8 5 8-5"/></svg>
                        {{ __('Send Reset Link') }}
                    </button>
                </form>
                <p class="mt-5 text-center text-sm text-slate-600">
                    {{ __('Remember your password?') }}
                    <a href="{{ route('ecommerce.login') }}" class="font-bold text-[var(--accent-strong)]">{{ __('Back to login') }}</a>
                </p>
            </div>
        </div>
    </section>
@endsection
