@extends('ecommerce::layouts.app')

@section('content')
    @include('ecommerce::partials.page-banner', [
        'title' => __('Privacy Policy'),
        'crumbs' => [
            ['label' => __('Home'), 'url' => route('ecommerce.home')],
            ['label' => __('Privacy Policy'), 'active' => true],
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
                        {{ __('Welcome to :store. We respect your privacy and are committed to protecting your personal information. This Privacy Policy explains how we collect, use, and safeguard data when you use our online store.', ['store' => $siteName]) }}
                    </p>
                    <p class="text-slate-500">
                        {{ __('Update this page from Ecommerce CMS → Site Settings using the Privacy Policy editor.') }}
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
