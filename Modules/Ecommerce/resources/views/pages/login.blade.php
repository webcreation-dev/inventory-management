@extends('ecommerce::layouts.app')

@section('content')
    <section class="mx-auto max-w-xl px-4 py-14 sm:px-6 lg:px-8">
        <div class="ec-panel overflow-hidden">
            <div class="ec-offer-shell !rounded-none !border-0 !shadow-none px-8 py-8">
                <p class="relative z-[1] text-[11px] font-bold uppercase tracking-[0.18em] text-[var(--accent-strong)]">{{ __('Welcome back') }}</p>
                <h2 class="ec-display relative z-[1] mt-2 text-2xl font-bold text-slate-900">{{ __('Login to your account') }}</h2>
            </div>
            <div class="border-t border-[rgba(var(--accent-rgb),0.18)] p-8 sm:p-10">
                @if(session('success'))
                    <p class="mb-4 rounded-xl bg-emerald-50 px-3 py-2 text-sm text-emerald-700">{{ session('success') }}</p>
                @endif

                @php
                    $demoCustomerEmail = config('app.is_demo') ? 'customer.doorsoft@gmail.com' : '';
                    $demoCustomerPassword = config('app.is_demo') ? '11111111' : '';
                @endphp
                <form method="POST" action="{{ route('ecommerce.login.submit') }}" class="space-y-4" novalidate>
                    @csrf
                    <div>
                        <label class="ec-label">{{ __('Email Address') }}</label>
                        <input type="email" name="email" value="{{ old('email', session('resend_email', $demoCustomerEmail)) }}" placeholder="you@example.com"
                               class="ec-input @error('email') border-rose-400 @enderror">
                        @error('email')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="ec-label">{{ __('Password') }}</label>
                        <div class="ec-password-field">
                            <input type="password" name="password" value="{{ old('password', $demoCustomerPassword) }}" placeholder="{{ __('Enter password') }}"
                                   class="ec-input @error('password') border-rose-400 @enderror">
                            <button type="button" class="ec-password-toggle" data-ec-password-toggle aria-label="{{ __('Show password') }}">
                                <svg class="ec-eye-open" viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M2.5 12s3.5-7 9.5-7 9.5 7 9.5 7-3.5 7-9.5 7-9.5-7-9.5-7Z"/><circle cx="12" cy="12" r="3" stroke-width="1.8"/></svg>
                                <svg class="ec-eye-closed hidden" viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M3 3l18 18M10.6 10.6a3 3 0 0 0 4.2 4.2M9.9 5.1A10.4 10.4 0 0 1 12 5c6 0 9.5 7 9.5 7a16.6 16.6 0 0 1-3.1 3.9M6.1 6.1A16.6 16.6 0 0 0 2.5 12S6 19 12 19c1.1 0 2.1-.2 3.1-.5"/></svg>
                            </button>
                        </div>
                        @error('password')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
                    </div>
                    <div class="flex items-center justify-end text-sm">
                        <a href="{{ route('ecommerce.forgot-password') }}" class="font-semibold text-[var(--accent-strong)] hover:opacity-80">{{ __('Forgot password?') }}</a>
                    </div>
                    <button type="submit" class="ec-btn ec-btn-primary ec-btn-block !py-3.5">
                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4M10 17l5-5-5-5M15 12H3"/></svg>
                        {{ __('Login') }}
                    </button>
                </form>

                @php
                    $showResend = session('show_resend_verification')
                        || ($errors->has('email') && str_contains(strtolower($errors->first('email')), 'verify'));
                @endphp
                @if($showResend)
                    <div class="mt-5 rounded-xl border border-[rgba(var(--accent-rgb),0.22)] bg-[rgba(var(--accent-rgb),0.06)] p-4">
                        <p class="text-sm font-semibold text-slate-800">{{ __('Did not get the email?') }}</p>
                        <p class="mt-1 text-xs text-slate-600">{{ __('Enter your email and we will send a new activation link.') }}</p>
                        <form method="POST" action="{{ route('ecommerce.resend-verification') }}" class="mt-3 flex flex-col gap-2 sm:flex-row" novalidate>
                            @csrf
                            <input type="email" name="email" value="{{ old('email', session('resend_email')) }}" placeholder="you@example.com" class="ec-input flex-1">
                            <button type="submit" class="ec-btn ec-btn-primary !px-4 !py-2.5 whitespace-nowrap">
                                {{ __('Resend verification') }}
                            </button>
                        </form>
                    </div>
                @endif

                <p class="mt-5 text-center text-sm text-slate-600">
                    {{ __("Don't have an account?") }}
                    <a href="{{ route('ecommerce.register') }}" class="font-bold text-[var(--accent-strong)]">{{ __('Register') }}</a>
                </p>
            </div>
        </div>
    </section>
@endsection
