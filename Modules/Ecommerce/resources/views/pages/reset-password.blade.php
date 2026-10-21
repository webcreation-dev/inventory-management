@extends('ecommerce::layouts.app')

@section('content')
    <section class="mx-auto max-w-xl px-4 py-14 sm:px-6 lg:px-8">
        <div class="ec-panel overflow-hidden">
            <div class="ec-offer-shell !rounded-none !border-0 !shadow-none px-8 py-8">
                <p class="relative z-[1] text-[11px] font-bold uppercase tracking-[0.18em] text-[var(--accent-strong)]">{{ __('Account recovery') }}</p>
                <h2 class="ec-display relative z-[1] mt-2 text-2xl font-bold text-slate-900">{{ __('Reset Password') }}</h2>
                <p class="relative z-[1] mt-2 text-sm text-slate-600">{{ __('Choose a new password for your account.') }}</p>
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

                <form method="POST" action="{{ route('ecommerce.reset-password.submit', ['customer' => $customerId]) }}?{{ $signatureQuery }}" class="space-y-4" novalidate>
                    @csrf
                    <div>
                        <label class="ec-label">{{ __('New Password') }}</label>
                        <div class="ec-password-field">
                            <input type="password" name="password" placeholder="{{ __('Enter new password') }}"
                                   class="ec-input @error('password') border-rose-400 @enderror">
                            <button type="button" class="ec-password-toggle" data-ec-password-toggle aria-label="{{ __('Show password') }}">
                                <svg class="ec-eye-open" viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M2.5 12s3.5-7 9.5-7 9.5 7 9.5 7-3.5 7-9.5 7-9.5-7-9.5-7Z"/><circle cx="12" cy="12" r="3" stroke-width="1.8"/></svg>
                                <svg class="ec-eye-closed hidden" viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M3 3l18 18M10.6 10.6a3 3 0 0 0 4.2 4.2M9.9 5.1A10.4 10.4 0 0 1 12 5c6 0 9.5 7 9.5 7a16.6 16.6 0 0 1-3.1 3.9M6.1 6.1A16.6 16.6 0 0 0 2.5 12S6 19 12 19c1.1 0 2.1-.2 3.1-.5"/></svg>
                            </button>
                        </div>
                    </div>
                    <div>
                        <label class="ec-label">{{ __('Confirm Password') }}</label>
                        <div class="ec-password-field">
                            <input type="password" name="password_confirmation" placeholder="{{ __('Re-enter new password') }}" class="ec-input">
                            <button type="button" class="ec-password-toggle" data-ec-password-toggle aria-label="{{ __('Show password') }}">
                                <svg class="ec-eye-open" viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M2.5 12s3.5-7 9.5-7 9.5 7 9.5 7-3.5 7-9.5 7-9.5-7-9.5-7Z"/><circle cx="12" cy="12" r="3" stroke-width="1.8"/></svg>
                                <svg class="ec-eye-closed hidden" viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M3 3l18 18M10.6 10.6a3 3 0 0 0 4.2 4.2M9.9 5.1A10.4 10.4 0 0 1 12 5c6 0 9.5 7 9.5 7a16.6 16.6 0 0 1-3.1 3.9M6.1 6.1A16.6 16.6 0 0 0 2.5 12S6 19 12 19c1.1 0 2.1-.2 3.1-.5"/></svg>
                            </button>
                        </div>
                    </div>
                    <button type="submit" class="ec-btn ec-btn-primary ec-btn-block !py-3.5">
                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 0 0 2-2v-6a2 2 0 0 0-2-2H6a2 2 0 0 0-2 2v6a2 2 0 0 0 2 2Zm10-10V7a4 4 0 1 0-8 0v2"/></svg>
                        {{ __('Update Password') }}
                    </button>
                </form>
            </div>
        </div>
    </section>
@endsection
