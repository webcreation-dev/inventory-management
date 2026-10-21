@extends('ecommerce::layouts.app')

@section('content')
    @include('ecommerce::partials.page-banner', [
        'title' => __('Contact Us'),
        'subtitle' => __('We typically reply within one business day.'),
        'crumbs' => [
            ['label' => __('Home'), 'url' => route('ecommerce.home')],
            ['label' => __('Contact Us'), 'active' => true],
        ],
    ])

    <section class="mx-auto max-w-7xl px-4 py-10 sm:px-6 lg:px-8 lg:py-12">
        <div class="grid gap-6 lg:grid-cols-12 lg:gap-8">
            <aside class="lg:col-span-4">
                <div class="ec-panel overflow-hidden h-full">
                    <div class="ec-offer-shell !rounded-none !border-0 !shadow-none px-6 py-6">
                        <h2 class="ec-display relative z-[1] text-xl font-bold text-slate-900">{{ __('Support details') }}</h2>
                        <p class="relative z-[1] mt-2 text-sm text-slate-600">
                            {{ __('Reach us through the channels below, or use the form.') }}
                        </p>
                    </div>
                    <ul class="space-y-4 border-t border-[rgba(var(--accent-rgb),0.14)] p-6 text-sm text-slate-700">
                        @if(!empty($contactEmail))
                            <li class="flex gap-3">
                                <span class="mt-0.5 inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-[rgba(var(--accent-rgb),0.1)] text-[var(--accent-strong)]">
                                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M4 6h16v12H4V6Zm0 0 8 7 8-7"/></svg>
                                </span>
                                <div>
                                    <p class="text-xs font-bold uppercase tracking-wide text-slate-500">{{ __('Email') }}</p>
                                    <a href="mailto:{{ $contactEmail }}" class="mt-0.5 font-semibold text-slate-900 hover:text-[var(--accent-strong)]">{{ $contactEmail }}</a>
                                </div>
                            </li>
                        @endif
                        @if(!empty($contactPhone))
                            <li class="flex gap-3">
                                <span class="mt-0.5 inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-[rgba(var(--accent-rgb),0.1)] text-[var(--accent-strong)]">
                                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M3.5 5.5c0 8 6.5 14.5 14.5 14.5h1.2a1.8 1.8 0 0 0 1.8-1.7l.1-2.1a1.5 1.5 0 0 0-1.2-1.5l-2.8-.7a1.5 1.5 0 0 0-1.5.6l-.9 1.1a11.5 11.5 0 0 1-5-5l1.1-.9a1.5 1.5 0 0 0 .6-1.5l-.7-2.8A1.5 1.5 0 0 0 7.4 4H5.3A1.8 1.8 0 0 0 3.5 5.5Z"/></svg>
                                </span>
                                <div>
                                    <p class="text-xs font-bold uppercase tracking-wide text-slate-500">{{ __('Phone') }}</p>
                                    <a href="tel:{{ preg_replace('/\s+/', '', $contactPhone) }}" class="mt-0.5 font-semibold text-slate-900 hover:text-[var(--accent-strong)]">{{ $contactPhone }}</a>
                                </div>
                            </li>
                        @endif
                        @if(!empty($contactAddress))
                            <li class="flex gap-3">
                                <span class="mt-0.5 inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-[rgba(var(--accent-rgb),0.1)] text-[var(--accent-strong)]">
                                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 21s7-5.4 7-11a7 7 0 1 0-14 0c0 5.6 7 11 7 11Zm0-8.5a2.5 2.5 0 1 0 0-5 2.5 2.5 0 0 0 0 5Z"/></svg>
                                </span>
                                <div>
                                    <p class="text-xs font-bold uppercase tracking-wide text-slate-500">{{ __('Address') }}</p>
                                    <p class="mt-0.5 font-semibold text-slate-900">{{ $contactAddress }}</p>
                                </div>
                            </li>
                        @endif
                        @if(empty($contactEmail) && empty($contactPhone) && empty($contactAddress))
                            <li class="rounded-xl bg-slate-50 px-4 py-3 text-slate-600">
                                {{ __('Contact details will appear here once configured in Site Settings.') }}
                            </li>
                        @endif
                    </ul>
                </div>
            </aside>

            <div class="lg:col-span-8">
                <div class="ec-panel p-6 sm:p-8">
                    @if(session('success'))
                        <p class="mb-5 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{{ session('success') }}</p>
                    @endif
                    @if(session('error'))
                        <p class="mb-5 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">{{ session('error') }}</p>
                    @endif

                    <form method="POST" action="{{ route('ecommerce.contact.submit') }}" class="grid gap-4 sm:grid-cols-2">
                        @csrf
                        <div>
                            <label class="ec-label" for="contact_name">{{ __('Full Name') }}</label>
                            <input id="contact_name" type="text" name="name" value="{{ old('name') }}" required maxlength="120"
                                   placeholder="{{ __('Your name') }}"
                                   class="ec-input @error('name') border-rose-400 @enderror">
                            @error('name')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label class="ec-label" for="contact_email">{{ __('Email Address') }}</label>
                            <input id="contact_email" type="email" name="email" value="{{ old('email') }}" required maxlength="150"
                                   placeholder="you@example.com"
                                   class="ec-input @error('email') border-rose-400 @enderror">
                            @error('email')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label class="ec-label" for="contact_phone">{{ __('Phone') }} <span class="font-normal text-slate-400">({{ __('optional') }})</span></label>
                            <input id="contact_phone" type="text" name="phone" value="{{ old('phone') }}" maxlength="50"
                                   placeholder="{{ __('Phone number') }}"
                                   class="ec-input @error('phone') border-rose-400 @enderror">
                            @error('phone')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label class="ec-label" for="contact_subject">{{ __('Subject') }}</label>
                            <input id="contact_subject" type="text" name="subject" value="{{ old('subject') }}" required maxlength="255"
                                   placeholder="{{ __('How can we help?') }}"
                                   class="ec-input @error('subject') border-rose-400 @enderror">
                            @error('subject')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
                        </div>
                        <div class="sm:col-span-2">
                            <label class="ec-label" for="contact_message">{{ __('Message') }}</label>
                            <textarea id="contact_message" name="message" rows="6" required maxlength="5000"
                                      placeholder="{{ __('Write your message here...') }}"
                                      class="ec-input min-h-[140px] resize-y @error('message') border-rose-400 @enderror">{{ old('message') }}</textarea>
                            @error('message')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
                        </div>
                        <div class="sm:col-span-2 flex flex-wrap items-center justify-between gap-3 pt-1">
                            <p class="text-xs text-slate-500">
                                {{ __('By sending, you agree to our') }}
                                <a href="{{ route('ecommerce.privacy') }}" class="font-semibold text-[var(--accent-strong)] hover:opacity-80">{{ __('Privacy Policy') }}</a>.
                            </p>
                            <button type="submit" class="ec-btn ec-btn-primary !px-6 !py-3">
                                {{ __('Send Message') }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </section>
@endsection
