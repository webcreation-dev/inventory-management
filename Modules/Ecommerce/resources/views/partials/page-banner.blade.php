@php
    $title = $title ?? '';
    $crumbs = $crumbs ?? [];
@endphp
<section class="ec-page-banner" aria-label="{{ $title }}">
    <div class="ec-page-banner__glow" aria-hidden="true"></div>
    <div class="ec-page-banner__inner">
        @if(!empty($crumbs))
            <nav class="ec-page-banner__crumbs" aria-label="{{ __('Breadcrumb') }}">
                <ol>
                    @foreach($crumbs as $index => $crumb)
                        <li>
                            @if(!empty($crumb['url']) && empty($crumb['active']))
                                <a href="{{ $crumb['url'] }}">
                                    @if($index === 0)
                                        <svg class="ec-page-banner__home" viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M3 10.5 12 3l9 7.5V20a1 1 0 0 1-1 1h-5v-6H9v6H4a1 1 0 0 1-1-1v-9.5Z"/>
                                        </svg>
                                    @endif
                                    <span>{{ $crumb['label'] }}</span>
                                </a>
                            @else
                                <span aria-current="page">{{ $crumb['label'] }}</span>
                            @endif
                        </li>
                    @endforeach
                </ol>
            </nav>
        @endif
        <h1 class="ec-page-banner__title">{{ $title }}</h1>
        @if(!empty($subtitle))
            <p class="ec-page-banner__subtitle">{{ $subtitle }}</p>
        @endif
    </div>
</section>
