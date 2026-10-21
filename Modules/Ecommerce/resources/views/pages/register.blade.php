@extends('ecommerce::layouts.app')

@section('content')
    <section class="mx-auto max-w-xl px-4 py-14 sm:px-6 lg:px-8">
        <div class="ec-panel overflow-hidden">
            <div class="ec-offer-shell !rounded-none !border-0 !shadow-none px-8 py-8">
                <p class="relative z-[1] text-[11px] font-bold uppercase tracking-[0.18em] text-[var(--accent-strong)]">{{ __('Get started') }}</p>
                <h2 class="ec-display relative z-[1] mt-2 text-2xl font-bold text-slate-900">{{ __('Create your account') }}</h2>
            </div>
            <div class="border-t border-[rgba(var(--accent-rgb),0.18)] p-8 sm:p-10">
                @if($errors->any())
                    <div class="mb-4 rounded-xl bg-rose-50 px-3 py-2 text-sm text-rose-700">
                        <ul class="list-disc space-y-1 pl-4">
                            @foreach($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <form method="POST" action="{{ route('ecommerce.register.submit') }}" class="grid gap-4 sm:grid-cols-2" novalidate>
                    @csrf
                    <div>
                        <label class="ec-label">{{ __('First Name') }}</label>
                        <input type="text" name="first_name" value="{{ old('first_name') }}" placeholder="{{ __('First Name') }}" class="ec-input @error('first_name') border-rose-400 @enderror">
                    </div>
                    <div>
                        <label class="ec-label">{{ __('Last Name') }}</label>
                        <input type="text" name="last_name" value="{{ old('last_name') }}" placeholder="{{ __('Last Name') }}" class="ec-input @error('last_name') border-rose-400 @enderror">
                    </div>
                    <div class="sm:col-span-2">
                        <label class="ec-label">{{ __('Email Address') }}</label>
                        <input type="email" name="email" value="{{ old('email') }}" placeholder="you@example.com" class="ec-input @error('email') border-rose-400 @enderror">
                    </div>
                    <div class="sm:col-span-2">
                        <label class="ec-label">{{ __('Phone Number') }}</label>
                        <input type="text" name="phone" value="{{ old('phone') }}" placeholder="+880..." class="ec-input @error('phone') border-rose-400 @enderror">
                    </div>
                    <div>
                        <label class="ec-label">{{ __('Password') }}</label>
                        <div class="ec-password-field">
                            <input type="password" name="password" placeholder="{{ __('Create Password') }}" class="ec-input @error('password') border-rose-400 @enderror">
                            <button type="button" class="ec-password-toggle" data-ec-password-toggle aria-label="{{ __('Show password') }}">
                                <svg class="ec-eye-open" viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M2.5 12s3.5-7 9.5-7 9.5 7 9.5 7-3.5 7-9.5 7-9.5-7-9.5-7Z"/><circle cx="12" cy="12" r="3" stroke-width="1.8"/></svg>
                                <svg class="ec-eye-closed hidden" viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M3 3l18 18M10.6 10.6a3 3 0 0 0 4.2 4.2M9.9 5.1A10.4 10.4 0 0 1 12 5c6 0 9.5 7 9.5 7a16.6 16.6 0 0 1-3.1 3.9M6.1 6.1A16.6 16.6 0 0 0 2.5 12S6 19 12 19c1.1 0 2.1-.2 3.1-.5"/></svg>
                            </button>
                        </div>
                    </div>
                    <div>
                        <label class="ec-label">{{ __('Confirm Password') }}</label>
                        <div class="ec-password-field">
                            <input type="password" name="password_confirmation" placeholder="{{ __('Re-enter password') }}" class="ec-input">
                            <button type="button" class="ec-password-toggle" data-ec-password-toggle aria-label="{{ __('Show password') }}">
                                <svg class="ec-eye-open" viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M2.5 12s3.5-7 9.5-7 9.5 7 9.5 7-3.5 7-9.5 7-9.5-7-9.5-7Z"/><circle cx="12" cy="12" r="3" stroke-width="1.8"/></svg>
                                <svg class="ec-eye-closed hidden" viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M3 3l18 18M10.6 10.6a3 3 0 0 0 4.2 4.2M9.9 5.1A10.4 10.4 0 0 1 12 5c6 0 9.5 7 9.5 7a16.6 16.6 0 0 1-3.1 3.9M6.1 6.1A16.6 16.6 0 0 0 2.5 12S6 19 12 19c1.1 0 2.1-.2 3.1-.5"/></svg>
                            </button>
                        </div>
                    </div>
                    <label class="sm:col-span-2 inline-flex items-start gap-2 text-sm text-slate-600">
                        <input type="checkbox" name="terms" value="1" {{ old('terms') ? 'checked' : '' }} class="mt-1 rounded border-slate-300 text-[var(--accent-strong)] focus:ring-[var(--accent-strong)]">
                        <span>
                            {{ __('I agree with') }}
                            <a href="{{ route('ecommerce.terms') }}" target="_blank" class="font-semibold text-[var(--accent-strong)] hover:opacity-80">{{ __('Terms and Conditions') }}</a>
                            {{ __('and') }}
                            <a href="{{ route('ecommerce.privacy') }}" target="_blank" class="font-semibold text-[var(--accent-strong)] hover:opacity-80">{{ __('Privacy Policy') }}</a>.
                        </span>
                    </label>
                    <button type="submit" class="ec-btn ec-btn-primary ec-btn-block sm:col-span-2 !py-3.5">
                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 12a4 4 0 1 0 0-8 4 4 0 0 0 0 8Zm-7 9a7 7 0 0 1 14 0M16 11l2 2 4-4"/></svg>
                        {{ __('Register') }}
                    </button>
                </form>
                <p class="mt-5 text-center text-sm text-slate-600">
                    {{ __('Already have an account?') }}
                    <a href="{{ route('ecommerce.login') }}" class="font-bold text-[var(--accent-strong)]">{{ __('Login') }}</a>
                </p>
            </div>
        </div>
    </section>
@endsection
