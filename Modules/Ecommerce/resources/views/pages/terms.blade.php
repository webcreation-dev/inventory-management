@extends('ecommerce::layouts.app')

@section('content')
    @include('ecommerce::partials.page-banner', [
        'title' => __('Terms and Conditions'),
        'crumbs' => [
            ['label' => __('Home'), 'url' => route('ecommerce.home')],
            ['label' => __('Terms and Conditions'), 'active' => true],
        ],
    ])

    <section class="mx-auto max-w-7xl px-4 py-10 sm:px-6 lg:px-8 lg:py-12">
        <article class="ec-panel p-6 sm:p-10">
            @if(!empty($content))
                <div class="ec-legal-content">
                    {!! $content !!}
                </div>
            @else
                <div class="space-y-6 text-sm leading-relaxed text-slate-700">
                    <p>
                        {{ __('By accessing or using :store, you agree to these Terms and Conditions. If you do not agree, please do not use our website or place orders.', ['store' => $siteName]) }}
                    </p>
                    <p class="text-slate-500">
                        {{ __('Update this page from Ecommerce CMS → Site Settings using the Terms and Conditions editor.') }}
                        @if(!empty($contactEmail))
                            {{ __('Questions?') }}
                            <a href="mailto:{{ $contactEmail }}" class="font-semibold text-[var(--accent-strong)]">{{ $contactEmail }}</a>
                        @endif
                    </p>
                </div>
            @endif
        </article>
    </section>
@endsection
