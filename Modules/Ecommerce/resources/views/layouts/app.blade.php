<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" @if(in_array(app()->getLocale(), ['ar', 'ur'], true)) dir="rtl" @endif>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? ($siteName ?? 'Ecommerce') }} | {{ $siteName ?? 'NexaShop' }}</title>
    @if(!empty($siteFavicon))
        <link rel="icon" href="{{ $siteFavicon }}">
    @endif
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Figtree:wght@400;500;600;700;800&family=Outfit:wght@500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('public/ecommerce_assets/css/storefront.css') }}?v={{ @filemtime(public_path('ecommerce_assets/css/storefront.css')) ?: time() }}">
    @stack('styles')
    @php
        $brandPalette = $brandPalette ?? \Modules\Ecommerce\Support\BrandColor::palette($brandColor ?? null);
        $brandHex = $brandPalette['hex'] ?? \Modules\Ecommerce\Support\BrandColor::DEFAULT;
    @endphp
    <style>:root{ {{ $brandPalette['css'] ?? '' }} }</style>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        brand: {
                            50: '{{ $brandPalette['soft'] ?? '#ccfbf1' }}',
                            100: '{{ $brandPalette['soft'] ?? '#ccfbf1' }}',
                            200: '{{ $brandPalette['soft'] ?? '#ccfbf1' }}',
                            300: '{{ $brandPalette['strong'] ?? $brandHex }}',
                            500: '{{ $brandPalette['strong'] ?? $brandHex }}',
                            600: '{{ $brandPalette['strong'] ?? $brandHex }}',
                            700: '{{ $brandPalette['dark'] ?? $brandHex }}',
                        },
                    },
                    fontFamily: {
                        display: ['Outfit', 'Figtree', 'sans-serif'],
                        sans: ['Figtree', 'sans-serif'],
                    },
                },
            },
        };
    </script>
</head>
<body class="ec-body min-h-screen text-slate-800">
    @php
        $megaCategories = [];
        if (!empty($navCategories) && count($navCategories)) {
            foreach ($navCategories as $category) {
                $megaCategories[$category->name] = $category->subCategories->map(fn ($sub) => [
                    'name' => $sub->name,
                    'slug' => \Illuminate\Support\Str::slug($sub->name),
                    'category_slug' => \Illuminate\Support\Str::slug($category->name),
                ])->values()->all();
            }
        }
        // Empty mega menu when no Header Categories are selected in Ecommerce CMS.
        $searchSuggestions = [];
        $storeName = $siteName ?? 'NexaShop';
    @endphp
    <div class="ec-announce d-none" style="display: none;">
        Free express shipping on selected orders · Premium curated drops every week
    </div>
    <header class="ec-header">
        <div class="mx-auto flex max-w-7xl flex-wrap items-center gap-3 px-4 py-3.5 sm:px-6 lg:px-8">
            <a href="{{ route('ecommerce.home') }}" class="mr-2 inline-flex items-center gap-2.5">
                @if(!empty($siteLogo))
                    <img src="{{ $siteLogo }}" alt="{{ $storeName }}" class="h-9 w-auto object-contain">
                @else
                    <span class="ec-logo-text">{{ $storeName }}</span>
                @endif
            </a>

            <button id="openMobileSearch" type="button" class="ec-icon-btn lg:hidden" aria-label="Open Search">
                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m21 21-4.35-4.35M16 10.5A5.5 5.5 0 1 1 5 10.5a5.5 5.5 0 0 1 11 0Z"/>
                </svg>
            </button>

            <nav class="hidden items-center gap-6 text-sm lg:flex">
                <a class="ec-nav-link" href="{{ route('ecommerce.home') }}">
                    {{ __('Home') }}
                </a>
                <div class="ec-mega group relative">
                    <a href="{{ route('ecommerce.category', ['slug' => 'all']) }}" class="ec-nav-link inline-flex items-center gap-1">
                        {{ __('Category') }}
                        <svg class="h-3.5 w-3.5 opacity-60" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m6 9 6 6 6-6"/>
                        </svg>
                    </a>
                    <div class="ec-mega-panel">
                        <div class="ec-mega-card">
                            <div class="grid grid-cols-2 gap-6">
                                @foreach($megaCategories as $category => $subcategories)
                                    <div>
                                        <a href="{{ route('ecommerce.category', ['slug' => \Illuminate\Support\Str::slug($category)]) }}" class="ec-display inline-flex items-center gap-2 text-sm font-bold text-slate-900 hover:text-[var(--accent-strong)]">
                                            <span class="ec-mega-dot" aria-hidden="true"></span>
                                            {{ $category }}
                                        </a>
                                        <ul class="mt-3 space-y-1.5 text-sm text-slate-600">
                                            @forelse($subcategories as $subcategory)
                                                @php
                                                    $subName = is_array($subcategory) ? $subcategory['name'] : $subcategory;
                                                    $subSlug = is_array($subcategory) ? $subcategory['slug'] : \Illuminate\Support\Str::slug($subcategory);
                                                    $parentSlug = is_array($subcategory) && !empty($subcategory['category_slug'])
                                                        ? $subcategory['category_slug']
                                                        : \Illuminate\Support\Str::slug($category);
                                                @endphp
                                                <li>
                                                    <a href="{{ route('ecommerce.category.sub', ['slug' => $parentSlug, 'sub' => $subSlug]) }}" class="ec-mega-link">{{ $subName }}</a>
                                                </li>
                                            @empty
                                                <li class="text-slate-400">{{ __('No sub categories') }}</li>
                                            @endforelse
                                        </ul>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </div>
                </div>
                <a class="ec-nav-link" href="{{ route('ecommerce.offers') }}">
                    <svg class="ec-nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M20 12v8a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1v-8"/>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M4 8h16v4H4V8Z"/>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 21V8"/>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 8H8.5a2.5 2.5 0 1 1 0-5C11 3 12 8 12 8Z"/>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 8h3.5a2.5 2.5 0 1 0 0-5C13 3 12 8 12 8Z"/>
                    </svg>
                    {{ __('Offers') }}
                </a>
            </nav>

            <div class="relative hidden w-full lg:ml-auto lg:mr-2 lg:block lg:max-w-[360px]">
                <form method="GET" action="{{ route('ecommerce.category', ['slug' => 'all']) }}" class="group relative" data-product-search>
                    <input type="search" name="q" data-search-input autocomplete="off" placeholder="{{ __('Search products') }}" class="ec-search w-full py-2.5 pl-5 pr-11 text-sm text-slate-700 outline-none">
                    <button type="submit" class="absolute right-2 top-1.5 rounded-full p-1.5 text-slate-400 hover:text-[var(--accent-strong)]" aria-label="Search">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m21 21-4.35-4.35M16 10.5A5.5 5.5 0 1 1 5 10.5a5.5 5.5 0 0 1 11 0Z"/>
                        </svg>
                    </button>
                    <div data-search-dropdown class="absolute left-0 top-full z-40 mt-2 hidden w-full rounded-2xl border border-slate-200 bg-white/95 p-3 shadow-xl backdrop-blur-xl">
                        <p class="mb-2 text-[11px] font-bold uppercase tracking-[0.14em] text-slate-400">Suggestions</p>
                        <ul data-search-results class="space-y-1 text-sm"></ul>
                        <p data-search-empty class="hidden px-2 py-2 text-sm text-slate-400">{{ __('No products found') }}</p>
                    </div>
                </form>
            </div>

            <div class="ml-auto flex items-center gap-2">
                @php
                    $ecLocales = collect($storefrontLanguages ?? ['en'])
                        ->filter(fn ($code) => in_array($code, config('app.available_locales', ['en']), true))
                        ->values();
                    $currentLocale = app()->getLocale();
                    if (! $ecLocales->contains($currentLocale)) {
                        $currentLocale = $ecLocales->first() ?? 'en';
                    }
                    $currentLocaleName = config('app.locale_names.' . $currentLocale, strtoupper($currentLocale));
                @endphp
                @if($ecLocales->count() > 1)
                    <div class="ec-lang" data-ec-lang>
                        <button type="button" class="ec-lang-btn" aria-expanded="false" aria-haspopup="listbox" aria-label="{{ __('Language') }}">
                            <svg class="ec-lang-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true">
                                <circle cx="12" cy="12" r="9" stroke-width="1.8"/>
                                <path stroke-linecap="round" stroke-width="1.8" d="M3 12h18M12 3c2.5 2.8 3.8 5.8 3.8 9s-1.3 6.2-3.8 9c-2.5-2.8-3.8-5.8-3.8-9S9.5 5.8 12 3Z"/>
                            </svg>
                            <span class="ec-lang-name hidden sm:inline">{{ $currentLocaleName }}</span>
                            <svg class="ec-lang-caret" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                <path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 0 1 1.06.02L10 10.94l3.71-3.71a.75.75 0 1 1 1.06 1.06l-4.24 4.24a.75.75 0 0 1-1.06 0L5.21 8.29a.75.75 0 0 1 .02-1.08Z" clip-rule="evenodd"/>
                            </svg>
                        </button>
                        <div class="ec-lang-menu" role="listbox" hidden>
                            @foreach($ecLocales as $locale)
                                <a
                                    href="{{ route('ecommerce.language.switch', $locale) }}"
                                    class="ec-lang-option {{ $locale === $currentLocale ? 'is-active' : '' }}"
                                    role="option"
                                    aria-selected="{{ $locale === $currentLocale ? 'true' : 'false' }}"
                                >
                                    <span>{{ config('app.locale_names.' . $locale, strtoupper($locale)) }}</span>
                                </a>
                            @endforeach
                        </div>
                    </div>
                @endif

                <div class="hidden items-center gap-2 lg:flex">
                <a href="{{ route('ecommerce.wishlist') }}" class="ec-icon-btn relative" aria-label="{{ __('Wishlist') }}">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m21.752 7.516c0 4.653-4.807 8.19-9.033 12.068a1.1 1.1 0 0 1-1.438 0C7.056 15.706 2.248 12.169 2.248 7.516A5.392 5.392 0 0 1 7.64 2.124c1.46 0 2.86.595 3.893 1.649a5.47 5.47 0 0 1 3.894-1.649 5.392 5.392 0 0 1 5.325 5.392Z"/></svg>
                    <span data-wishlist-count class="absolute -right-1 -top-1 rounded-full bg-[var(--accent-strong)] px-1.5 py-0.5 text-[10px] font-bold text-white {{ ($wishlistCount ?? 0) > 0 ? '' : 'hidden' }}">{{ $wishlistCount ?? 0 }}</span>
                </a>

                @if(!empty($isEcommerceCustomer))
                    @php
                        $ecUser = $ecommerceCustomer ?? [];
                        $ecUserName = $ecUser['name'] ?? __('Customer');
                        $ecUserEmail = $ecUser['email'] ?? '';
                        $ecUserPhoto = $ecUser['photo_url'] ?? null;
                        $ecUserInitial = strtoupper(mb_substr(trim($ecUserName) !== '' ? $ecUserName : 'U', 0, 1));
                    @endphp
                    <div class="ec-user-menu">
                        <button type="button" class="ec-icon-btn ec-user-trigger" aria-label="{{ __('Account') }}" aria-haspopup="true">
                            @if($ecUserPhoto)
                                <img src="{{ $ecUserPhoto }}" alt="" class="h-full w-full rounded-full object-cover">
                            @else
                                <span class="ec-user-avatar" aria-hidden="true">{{ $ecUserInitial }}</span>
                            @endif
                        </button>
                        <div class="ec-user-dropdown">
                            <div class="ec-user-dropdown-panel">
                                <div class="ec-user-dropdown-head">
                                    @if($ecUserPhoto)
                                        <img src="{{ $ecUserPhoto }}" alt="" class="ec-user-avatar ec-user-avatar-lg object-cover p-0">
                                    @else
                                        <span class="ec-user-avatar ec-user-avatar-lg" aria-hidden="true">{{ $ecUserInitial }}</span>
                                    @endif
                                    <div class="min-w-0">
                                        <p class="truncate text-sm font-bold text-slate-900">{{ $ecUserName }}</p>
                                        <p class="truncate text-xs text-slate-500">{{ $ecUserEmail }}</p>
                                    </div>
                                </div>
                                <div class="ec-user-dropdown-links">
                                    <a href="{{ route('ecommerce.customer-panel', ['tab' => 'dashboard']) }}">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M3 12l9-9 9 9M5 10v10h14V10"/></svg>
                                        {{ __('Dashboard') }}
                                    </a>
                                    <a href="{{ route('ecommerce.customer-panel', ['tab' => 'orders']) }}">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M9 5h12M9 12h12M9 19h12M4 5h.01M4 12h.01M4 19h.01"/></svg>
                                        {{ __('Order') }}
                                    </a>
                                    <a href="{{ route('ecommerce.customer-panel', ['tab' => 'profile']) }}">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 12a4 4 0 1 0 0-8 4 4 0 0 0 0 8Zm-7 9a7 7 0 0 1 14 0"/></svg>
                                        {{ __('Profile Setting') }}
                                    </a>
                                    <form method="POST" action="{{ route('ecommerce.logout') }}">
                                        @csrf
                                        <button type="submit" class="ec-user-logout">
                                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M10 17l5-5-5-5M15 12H3m12-7h4a1 1 0 0 1 1 1v14a1 1 0 0 1-1 1h-4"/></svg>
                                            {{ __('Logout') }}
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                @else
                    @if(!empty($showAdminLoginUrl))
                        <div class="ec-user-menu">
                            <button type="button" class="ec-icon-btn" aria-label="{{ __('Login') }}" aria-haspopup="true">
                                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 12a4 4 0 1 0 0-8 4 4 0 0 0 0 8Zm-7 9a7 7 0 0 1 14 0"/></svg>
                            </button>
                            <div class="ec-user-dropdown">
                                <div class="ec-user-dropdown-panel">
                                    <div class="ec-user-dropdown-links">
                                        <a href="{{ route('ecommerce.login') }}">
                                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 12a4 4 0 1 0 0-8 4 4 0 0 0 0 8Zm-7 9a7 7 0 0 1 14 0"/></svg>
                                            {{ __('Login as Customer') }}
                                        </a>
                                        <a href="{{ route('login') }}">
                                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 3 4 6v6c0 5 3.4 8.4 8 9 4.6-.6 8-4 8-9V6l-8-3Z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="m9 12 2 2 4-4"/></svg>
                                            {{ __('Login as Admin') }}
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    @else
                        <a href="{{ route('ecommerce.login') }}" class="ec-icon-btn" aria-label="{{ __('Login') }}">
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 12a4 4 0 1 0 0-8 4 4 0 0 0 0 8Zm-7 9a7 7 0 0 1 14 0"/></svg>
                        </a>
                    @endif
                @endif

                <button id="openCartOffcanvas" type="button" class="ec-cart-btn" aria-label="{{ __('Shopping Cart') }}">
                    <span class="ec-cart-btn-icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.9" d="M3 3h2l1.6 9.2a2 2 0 0 0 2 1.6h7.8a2 2 0 0 0 2-1.5L21 6H7"/>
                            <circle cx="9" cy="19" r="1.2"/>
                            <circle cx="18" cy="19" r="1.2"/>
                        </svg>
                    </span>
                    <span class="ec-cart-btn-label">{{ __('Cart') }}</span>
                    <span id="headerCartCount" class="ec-cart-count {{ ($cartCount ?? 0) > 0 ? '' : 'is-empty' }}">{{ $cartCount ?? 0 }}</span>
                </button>
                </div>
            </div>
        </div>
    </header>

    <div id="mobileSearchBackdrop" class="fixed inset-0 z-40 hidden bg-slate-900/50 lg:hidden"></div>
    <div id="mobileSearchPanel" class="fixed left-0 top-0 z-50 w-full -translate-y-full bg-white p-4 shadow-xl transition-transform duration-300 lg:hidden">
        <div class="flex items-center gap-2">
            <form method="GET" action="{{ route('ecommerce.category', ['slug' => 'all']) }}" class="group relative flex-1" data-product-search>
                <input type="search" name="q" data-search-input autocomplete="off" placeholder="{{ __('Search products') }}" class="w-full rounded-full border border-slate-300 bg-white py-2.5 pl-4 pr-10 text-sm text-slate-700 shadow-sm outline-none ring-brand-500 transition focus:border-brand-500 focus:ring">
                <button type="submit" class="absolute right-2 top-1.5 rounded-full p-1 text-slate-400 hover:text-brand-700" aria-label="Search">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m21 21-4.35-4.35M16 10.5A5.5 5.5 0 1 1 5 10.5a5.5 5.5 0 0 1 11 0Z"/>
                    </svg>
                </button>
                <div data-search-dropdown class="absolute left-0 top-full z-40 mt-2 hidden w-full rounded-xl border border-slate-200 bg-white p-3 shadow-lg">
                    <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-slate-500">Product Suggest</p>
                    <ul data-search-results class="space-y-2 text-sm"></ul>
                    <p data-search-empty class="hidden px-2 py-2 text-sm text-slate-400">{{ __('No products found') }}</p>
                </div>
            </form>
            <button id="closeMobileSearch" type="button" class="rounded-full border border-slate-300 px-3  text-sm font-semibold text-slate-700 hover:border-brand-300 hover:text-brand-700 h-11 w-11">
                 <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m6 6 12 12M18 6 6 18"/></svg>
            </button>
        </div>
    </div>

    <div id="categoryBackdrop" class="fixed inset-0 z-40 hidden bg-slate-900/50 lg:hidden"></div>
    <aside id="categoryPanel" class="fixed left-0 top-0 z-50 flex h-full w-full max-w-xs -translate-x-full flex-col overflow-hidden bg-white shadow-2xl transition-transform duration-300 lg:hidden">
        <div class="flex shrink-0 items-center justify-between border-b border-slate-200 px-5 py-4">
            <h3 class="text-lg font-semibold text-slate-900">{{ __('Categories') }}</h3>
            <button id="closeCategoryPanel" type="button" class="rounded-md p-2 text-slate-500 hover:bg-slate-100 hover:text-slate-700">
                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m6 6 12 12M18 6 6 18"/></svg>
            </button>
        </div>
        <div class="min-h-0 flex-1 space-y-5 overflow-y-auto overscroll-contain p-5" style="-webkit-overflow-scrolling: touch;">
            @foreach($megaCategories as $category => $subcategories)
                <div>
                    <a href="{{ route('ecommerce.category', ['slug' => \Illuminate\Support\Str::slug($category)]) }}" class="text-sm font-semibold uppercase tracking-wide text-slate-800 hover:text-brand-700">{{ $category }}</a>
                    <ul class="mt-2 space-y-1">
                        @forelse($subcategories as $subcategory)
                            @php
                                $subName = is_array($subcategory) ? $subcategory['name'] : $subcategory;
                                $subSlug = is_array($subcategory) ? $subcategory['slug'] : \Illuminate\Support\Str::slug($subcategory);
                                $parentSlug = is_array($subcategory) && !empty($subcategory['category_slug'])
                                    ? $subcategory['category_slug']
                                    : \Illuminate\Support\Str::slug($category);
                            @endphp
                            <li>
                                <a href="{{ route('ecommerce.category.sub', ['slug' => $parentSlug, 'sub' => $subSlug]) }}" class="block rounded-md px-2 py-2 text-sm text-slate-600 hover:bg-slate-100 hover:text-brand-700">{{ $subName }}</a>
                            </li>
                        @empty
                            <li class="px-2 py-2 text-sm text-slate-400">{{ __('No sub categories') }}</li>
                        @endforelse
                    </ul>
                </div>
            @endforeach
        </div>
    </aside>

    <div id="profileBackdrop" class="fixed inset-0 z-40 hidden bg-slate-900/50 lg:hidden"></div>
    <aside id="profilePanel" class="fixed right-0 top-0 z-50 h-full w-full max-w-xs translate-x-full bg-white shadow-2xl transition-transform duration-300 lg:hidden">
        <div class="flex items-center justify-between border-b border-slate-200 px-5 py-4">
            <h3 class="text-lg font-semibold text-slate-900">{{ __('Profile') }}</h3>
            <button id="closeProfilePanel" type="button" class="rounded-md p-2 text-slate-500 hover:bg-slate-100 hover:text-slate-700">
                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m6 6 12 12M18 6 6 18"/></svg>
            </button>
        </div>
        <nav class="space-y-2 p-5 text-sm font-medium text-slate-700">
            @if(!empty($showAdminLoginUrl) && empty($isEcommerceCustomer))
                <a href="{{ route('ecommerce.login') }}" class="block rounded-lg px-3 py-2 hover:bg-slate-100 hover:text-brand-700">{{ __('Login as Customer') }}</a>
                <a href="{{ route('login') }}" class="block rounded-lg px-3 py-2 hover:bg-slate-100 hover:text-brand-700">{{ __('Login as Admin') }}</a>
            @else
                <a href="{{ route('ecommerce.login') }}" class="block rounded-lg px-3 py-2 hover:bg-slate-100 hover:text-brand-700">{{ __('Login') }}</a>
            @endif
            <a href="{{ route('ecommerce.register') }}" class="block rounded-lg px-3 py-2 hover:bg-slate-100 hover:text-brand-700">{{ __('Register') }}</a>
            <a href="{{ route('ecommerce.customer-panel') }}" class="block rounded-lg px-3 py-2 hover:bg-slate-100 hover:text-brand-700">{{ __('My Account') }}</a>
            <a href="{{ route('ecommerce.wishlist') }}" class="block rounded-lg px-3 py-2 hover:bg-slate-100 hover:text-brand-700">{{ __('My Wishlist') }}</a>
            <a href="{{ route('ecommerce.contact') }}" class="block rounded-lg px-3 py-2 hover:bg-slate-100 hover:text-brand-700">{{ __('Support') }}</a>
        </nav>
    </aside>

    <div id="cartBackdrop" class="fixed inset-0 z-40 hidden bg-slate-900/50"></div>
    <div id="ecToastHost" class="pointer-events-none fixed top-4 right-4 z-[80] flex w-full max-w-sm flex-col items-end gap-2 px-4 sm:px-0" aria-live="polite"></div>

    <div id="ratingBackdrop" class="fixed inset-0 z-[75] hidden bg-slate-900/50"></div>
    <div id="ratingModal" class="fixed inset-0 z-[76] hidden items-center justify-center p-4" role="dialog" aria-modal="true" aria-labelledby="ratingModalTitle">
        <div class="ec-rating-modal w-full max-w-md">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <h3 id="ratingModalTitle" class="ec-display text-lg font-bold text-slate-900">{{ __('Rate this product') }}</h3>
                    <p class="mt-1 text-sm text-slate-500">{{ __('Share your experience to help other shoppers.') }}</p>
                </div>
                <button type="button" id="closeRatingModal" class="ec-icon-btn !h-9 !w-9" aria-label="{{ __('Close') }}">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m6 6 12 12M18 6 6 18"/></svg>
                </button>
            </div>
            <form id="ratingForm" class="mt-5 space-y-4">
                <input type="hidden" name="product_id" id="ratingProductId" value="">
                <input type="hidden" name="rating" id="ratingValue" value="5">
                <div>
                    <p class="ec-label">{{ __('Your rating') }}</p>
                    <div id="ratingStarPicker" class="ec-star-picker mt-2" role="radiogroup" aria-label="{{ __('Rating') }}">
                        @for($i = 1; $i <= 5; $i++)
                            <button type="button" class="ec-star-picker__btn is-on" data-star="{{ $i }}" aria-label="{{ $i }} {{ __('stars') }}">
                                <svg viewBox="0 0 24 24" fill="currentColor"><path d="m12 3.4 2.5 5.1 5.6.8-4 3.9.9 5.6L12 16.2l-5 2.6.9-5.6-4-3.9 5.6-.8L12 3.4Z"/></svg>
                            </button>
                        @endfor
                    </div>
                </div>
                <div>
                    <label class="ec-label" for="ratingComment">{{ __('Comment') }}</label>
                    <textarea id="ratingComment" name="comment" rows="4" class="ec-input" placeholder="{{ __('What did you like or dislike?') }}"></textarea>
                </div>
                <label class="ec-choice !min-h-0">
                    <input type="checkbox" id="ratingRecommend" name="recommend" class="ec-choice__input" checked>
                    <span class="ec-choice__control ec-choice__control--check" aria-hidden="true"></span>
                    <span class="ec-choice__label">{{ __('I recommend this product') }}</span>
                </label>
                <p id="ratingFormError" class="hidden text-sm font-semibold text-rose-600"></p>
                <div class="flex gap-2 pt-1">
                    <button type="button" id="cancelRatingModal" class="ec-btn ec-btn-ghost flex-1">
                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m6 6 12 12M18 6 6 18"/></svg>
                        {{ __('Cancel') }}
                    </button>
                    <button type="submit" id="submitRatingBtn" class="ec-btn ec-btn-primary flex-1">
                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                        {{ __('Submit Review') }}
                    </button>
                </div>
            </form>
        </div>
    </div>

    <aside id="cartOffcanvas" class="ec-drawer fixed right-0 top-0 z-50 h-full w-full max-w-md translate-x-full shadow-2xl transition-transform duration-300">
        <div class="flex items-center justify-between border-b border-slate-200/80 px-5 py-4">
            <h3 class="ec-display text-lg font-bold text-slate-900">{{ __('Shopping Cart') }}</h3>
            <button id="closeCartOffcanvas" type="button" class="ec-icon-btn !h-9 !w-9">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m6 6 12 12M18 6 6 18"/></svg>
            </button>
        </div>
        <div id="cartItemsContainer" class="flex h-[calc(100%-72px)] flex-col">
            <div id="cartItemsList" class="flex-1 space-y-4 overflow-y-auto p-5">
                @forelse(($cartItems ?? []) as $item)
                    @php
                        $img = !empty($item['image_is_url'])
                            ? $item['image']
                            : route('ecommerce.image', ['file' => $item['image']]);
                        $lineTotal = (float) $item['price'] * (int) $item['qty'];
                    @endphp
                    <div class="cart-item flex gap-3 rounded-xl border border-slate-200 p-3.5" data-product-id="{{ $item['id'] }}" data-unit-price="{{ $item['price'] }}">
                        <img src="{{ $img }}" alt="{{ $item['name'] }}" class="h-16 w-16 rounded-md object-cover">
                        <div class="flex-1 min-w-0">
                            <p class="ec-cart-item-title">{{ $item['name'] }}</p>
                            <div class="ec-qty-stepper" data-qty-stepper>
                                <button type="button" class="ec-qty-btn" data-qty-dec data-product-id="{{ $item['id'] }}" aria-label="{{ __('Decrease') }}">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.2" d="M5 12h14"/></svg>
                                </button>
                                <input type="number" min="1" class="ec-qty-input" data-qty-input data-product-id="{{ $item['id'] }}" value="{{ (int) $item['qty'] }}">
                                <button type="button" class="ec-qty-btn" data-qty-inc data-product-id="{{ $item['id'] }}" aria-label="{{ __('Increase') }}">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.2" d="M12 5v14M5 12h14"/></svg>
                                </button>
                            </div>
                            <p class="ec-cart-item-price mt-1.5">{{ function_exists('formatAmount') ? formatAmount($lineTotal) : ('$' . number_format($lineTotal, 2)) }}</p>
                        </div>
                        <button type="button" class="remove-cart-item h-fit rounded-full p-2 text-slate-400 hover:bg-rose-50 hover:text-rose-600" data-product-id="{{ $item['id'] }}" aria-label="{{ __('Remove item') }}">
                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-trash">
                            <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                            <path d="M4 7l16 0" />
                            <path d="M10 11l0 6" />
                            <path d="M14 11l0 6" />
                            <path d="M5 7l1 12a2 2 0 0 0 2 2h8a2 2 0 0 0 2 -2l1 -12" />
                            <path d="M9 7v-3a1 1 0 0 1 1 -1h4a1 1 0 0 1 1 1v3" />
                        </svg>
                        </button>
                    </div>
                @empty
                @endforelse
                <p id="emptyCartMessage" class="{{ !empty($cartItems) ? 'hidden' : '' }} rounded-lg border border-dashed border-slate-300 p-4 text-center text-[0.95rem] text-slate-500">{{ __('Your cart is empty') }}</p>
            </div>
            <div class="border-t border-slate-200 bg-white p-5">
                <div class="ec-cart-summary-row mb-2"><span>{{ __('Subtotal') }}</span><span id="cartSubtotal">${{ number_format($cartSubtotal ?? 0, 2) }}</span></div>
                <div class="ec-cart-summary-row mb-2">
                    <span>{{ __('Tax') }} <span id="cartTaxInclusiveNote" class="{{ (($cartTaxInclusive ?? 0) > 0) ? '' : 'hidden' }} text-slate-400">({{ __('Incl.') }} <span id="cartTaxInclusive">{{ function_exists('formatAmount') ? formatAmount($cartTaxInclusive ?? 0) : ('$' . number_format($cartTaxInclusive ?? 0, 2)) }}</span>)</span></span>
                    <span id="cartTax">{{ function_exists('formatAmount') ? formatAmount($cartTax ?? 0) : ('$' . number_format($cartTax ?? 0, 2)) }}</span>
                </div>
                <div class="ec-cart-summary-total"><span>{{ __('Total') }}</span><span id="cartTotal">${{ number_format($cartTotal ?? 0, 2) }}</span></div>
                <button id="proceedToCheckoutBtn" type="button"
                        data-logged-in="{{ !empty($isEcommerceCustomer) ? '1' : '0' }}"
                        data-is-admin="{{ !empty($isBackendAdmin) ? '1' : '0' }}"
                        data-checkout-url="{{ route('ecommerce.checkout') }}"
                        class="ec-btn ec-btn-primary ec-btn-block !py-3.5">
                    {{ __('Proceed to Checkout') }}
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 12h14m-6-6 6 6-6 6"/></svg>
                </button>
            </div>
        </div>
    </aside>

    {{-- Customer login modal --}}
    <div id="customerLoginBackdrop" class="fixed inset-0 z-[60] hidden bg-slate-900/50"></div>
    <div id="customerLoginModal" class="fixed inset-0 z-[70] hidden items-center justify-center p-4">
        <div class="w-full max-w-md rounded-2xl bg-white p-6 shadow-2xl ring-1 ring-slate-200">
            <div class="mb-4 flex items-center justify-between">
                <h3 class="text-lg font-semibold text-slate-900">{{ __('Login as Customer') }}</h3>
                <button id="closeCustomerLoginModal" type="button" class="rounded-full p-2 text-slate-500 bg-slate-100 hover:bg-slate-200">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m6 6 12 12M18 6 6 18"/></svg>
                </button>
            </div>
            <p class="mb-4 text-sm text-slate-500">{{ __('Please login to continue checkout') }}</p>
            @php
                $demoCustomerEmail = config('app.is_demo') ? 'customer.doorsoft@gmail.com' : '';
                $demoCustomerPassword = config('app.is_demo') ? '11111111' : '';
            @endphp
            <form id="customerLoginForm" class="space-y-4" novalidate>
                <div>
                    <label class="text-xs font-semibold uppercase tracking-wide text-slate-600">{{ __('Email') }}</label>
                    <input type="email" name="email" class="ec-input mt-1" placeholder="{{ __('Enter your email') }}"
                           value="{{ $demoCustomerEmail }}">
                </div>
                <div>
                    <label class="text-xs font-semibold uppercase tracking-wide text-slate-600">{{ __('Password') }}</label>
                    <div class="ec-password-field mt-1">
                        <input type="password" name="password" class="ec-input" placeholder="{{ __('Enter your password') }}"
                               value="{{ $demoCustomerPassword }}">
                        <button type="button" class="ec-password-toggle" data-ec-password-toggle aria-label="{{ __('Show password') }}">
                            <svg class="ec-eye-open" viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M2.5 12s3.5-7 9.5-7 9.5 7 9.5 7-3.5 7-9.5 7-9.5-7-9.5-7Z"/><circle cx="12" cy="12" r="3" stroke-width="1.8"/></svg>
                            <svg class="ec-eye-closed hidden" viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M3 3l18 18M10.6 10.6a3 3 0 0 0 4.2 4.2M9.9 5.1A10.4 10.4 0 0 1 12 5c6 0 9.5 7 9.5 7a16.6 16.6 0 0 1-3.1 3.9M6.1 6.1A16.6 16.6 0 0 0 2.5 12S6 19 12 19c1.1 0 2.1-.2 3.1-.5"/></svg>
                        </button>
                    </div>
                </div>
                <p id="customerLoginError" class="hidden text-sm text-rose-600"></p>
                <button type="submit" class="ec-btn ec-btn-primary ec-btn-block !py-3.5">
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4M10 17l5-5-5-5M15 12H3"/></svg>
                    {{ __('Login') }}
                </button>
            </form>
            <p class="mt-4 text-center text-sm text-slate-600">
                {{ __("Don't have an account?") }}
                <a href="{{ route('ecommerce.register') }}" class="font-semibold text-brand-700 hover:text-brand-600">{{ __("Register") }}</a>
            </p>
        </div>
    </div>

    {{-- Admin checkout block modal --}}
    <div id="adminCheckoutBackdrop" class="fixed inset-0 z-[60] hidden bg-slate-900/50"></div>
    <div id="adminCheckoutModal" class="fixed inset-0 z-[70] hidden items-center justify-center p-4" role="dialog" aria-modal="true" aria-labelledby="adminCheckoutTitle">
        <div class="w-full max-w-md rounded-2xl bg-white p-6 shadow-2xl ring-1 ring-slate-200">
            <div class="mb-4 flex items-center justify-between">
                <h3 id="adminCheckoutTitle" class="text-lg font-semibold text-slate-900">{{ __('Admin session active') }}</h3>
                <button id="closeAdminCheckoutModal" type="button" class="rounded-full p-2 text-slate-500 bg-slate-100 hover:bg-slate-200" aria-label="{{ __('Close') }}">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m6 6 12 12M18 6 6 18"/></svg>
                </button>
            </div>
            <p class="mb-6 text-sm text-slate-600">
                {{ __("You're logged in as admin. Do you want to login as customer?") }}
            </p>
            <p id="adminCheckoutError" class="mb-4 hidden text-sm text-rose-600"></p>
            <div class="flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                <button id="adminCheckoutNoBtn" type="button" class="ec-btn ec-btn-ghost !py-3">
                    {{ __('No') }}
                </button>
                <button id="adminCheckoutYesBtn" type="button" class="ec-btn ec-btn-primary !py-3">
                    {{ __('Yes') }}
                </button>
            </div>
        </div>
    </div>

    <main class="pb-20 lg:pb-0">
        @yield('content')
    </main>

    <nav class="ec-bottom-nav fixed inset-x-0 bottom-0 z-40 px-2 py-2 lg:hidden">
        <div class="mx-auto grid max-w-md grid-cols-5 items-end gap-1">
            <a href="{{ route('ecommerce.home') }}" class="flex flex-col items-center justify-center rounded-lg px-2 py-1 text-slate-700 hover:bg-slate-100 hover:text-[var(--accent-strong)]">
                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10.5 12 3l9 7.5V21a1 1 0 0 1-1 1h-5v-6h-6v6H4a1 1 0 0 1-1-1v-10.5Z"/></svg>
                <span class="mt-1 text-[11px] font-semibold">{{ __('Home') }}</span>
            </a>
            <button id="openCategoryPanel" type="button" class="flex flex-col items-center justify-center rounded-lg px-2 py-1 text-slate-700 hover:bg-slate-100 hover:text-[var(--accent-strong)]">
                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
                <span class="mt-1 text-[11px] font-semibold">{{ __('Category') }}</span>
            </button>
            <button id="openCartOffcanvasBottom" type="button" class="relative -mt-5 mx-auto flex h-14 w-14 flex-col items-center justify-center rounded-full border-4 border-white bg-[var(--accent-strong)] text-white shadow-lg">
                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l1.6 9.2a2 2 0 0 0 2 1.6h7.8a2 2 0 0 0 2-1.5L21 6H7"/><circle cx="9" cy="19" r="1.2"/><circle cx="18" cy="19" r="1.2"/></svg>
                <span id="bottomCartCount" class="absolute -right-1 -top-1 rounded-full bg-rose-500 px-1.5 py-0.5 text-[10px] font-bold text-white {{ ($cartCount ?? 0) > 0 ? '' : 'hidden' }}">{{ $cartCount ?? 0 }}</span>
            </button>
       
            <a href="{{ route('ecommerce.wishlist') }}" class="flex flex-col items-center justify-center rounded-lg px-2 py-1 text-slate-700 hover:bg-slate-100 hover:text-brand-700">
                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m21.752 7.516c0 4.653-4.807 8.19-9.033 12.068a1.1 1.1 0 0 1-1.438 0C7.056 15.706 2.248 12.169 2.248 7.516A5.392 5.392 0 0 1 7.64 2.124c1.46 0 2.86.595 3.893 1.649a5.47 5.47 0 0 1 3.894-1.649 5.392 5.392 0 0 1 5.325 5.392Z"/></svg>
                <span class="mt-1 text-[11px] font-semibold">{{ __('Wishlist') }}</span>
            </a>
            <button id="openProfilePanel" type="button" class="flex flex-col items-center justify-center rounded-lg px-2 py-1 text-slate-700 hover:bg-slate-100 hover:text-brand-700">
                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 12a4 4 0 1 0 0-8 4 4 0 0 0 0 8Zm-7 9a7 7 0 0 1 14 0"/></svg>
                <span class="mt-1 text-[11px] font-semibold">{{ __('Profile') }}</span>
            </button>
        </div>
    </nav>

    <footer class="ec-footer">
        <div class="mx-auto grid max-w-7xl gap-10 px-4 py-14 sm:px-6 lg:grid-cols-4 lg:px-8">
            <div class="lg:col-span-2">
                @if(!empty($footerLogo))
                    <a href="{{ route('ecommerce.home') }}" class="ec-footer-logo inline-flex items-center">
                        <img src="{{ $footerLogo }}" alt="{{ $storeName }}" class="h-10 w-auto object-contain sm:h-12">
                    </a>
                @else
                    <h3 class="ec-display text-2xl font-bold text-white">{{ $storeName }}</h3>
                @endif
                <p class="mt-4 max-w-md text-[0.95rem] leading-relaxed text-slate-300">
                    {{ !empty($footerDescription) ? $footerDescription : __('A conversion-focused premium storefront for modern retail — crafted for clarity, speed, and beautiful product storytelling.') }}
                </p>
                @if(!empty($contactPhone) || !empty($contactEmail) || !empty($contactAddress))
                    <ul class="ec-footer-contact mt-6 space-y-2.5">
                        @if(!empty($contactPhone))
                            <li>
                                <a href="tel:{{ preg_replace('/\s+/', '', $contactPhone) }}" class="inline-flex items-start gap-2.5">
                                    <svg class="mt-0.5 h-4 w-4 shrink-0 opacity-80" viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M5 4h3.5l1.2 4.2-2.1 1.4a12.5 12.5 0 0 0 6.3 6.3l1.4-2.1L19.5 15.5V19a2 2 0 0 1-2.2 2A15.8 15.8 0 0 1 3 6.2 2 2 0 0 1 5 4Z"/></svg>
                                    <span>{{ $contactPhone }}</span>
                                </a>
                            </li>
                        @endif
                        @if(!empty($contactEmail))
                            <li>
                                <a href="mailto:{{ $contactEmail }}" class="inline-flex items-start gap-2.5">
                                    <svg class="mt-0.5 h-4 w-4 shrink-0 opacity-80" viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M4 6h16v12H4V6Zm0 0 8 7 8-7"/></svg>
                                    <span>{{ $contactEmail }}</span>
                                </a>
                            </li>
                        @endif
                        @if(!empty($contactAddress))
                            <li class="inline-flex items-start gap-2.5 text-slate-300">
                                <svg class="mt-0.5 h-4 w-4 shrink-0 opacity-80" viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 21s7-5.4 7-11a7 7 0 1 0-14 0c0 5.6 7 11 7 11Zm0-8.5a2.5 2.5 0 1 0 0-5 2.5 2.5 0 0 0 0 5Z"/></svg>
                                <span>{{ $contactAddress }}</span>
                            </li>
                        @endif
                    </ul>
                @endif
                <div class="mt-6 flex flex-wrap items-center gap-2">
                    <span class="rounded-full border border-white/10 bg-white/5 px-3 py-1 text-xs text-teal-100">{{ __('Secure Checkout') }}</span>
                    <span class="rounded-full border border-white/10 bg-white/5 px-3 py-1 text-xs text-teal-100">{{ __('Fast Delivery') }}</span>
                    <span class="rounded-full border border-white/10 bg-white/5 px-3 py-1 text-xs text-teal-100">{{ __('24/7 Support') }}</span>
                </div>
                @if(!empty($appLink))
                    <a href="{{ $appLink }}" target="_blank" rel="noopener" class="ec-app-link mt-5 inline-flex items-center gap-2">
                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 3v12m0 0 4-4m-4 4-4-4M5 19h14"/></svg>
                        {{ __('Download App') }}
                    </a>
                @endif
            </div>
            <div>
                <h4 class="text-xs font-bold uppercase tracking-[0.16em] text-white">{{ __('Quick Links') }}</h4>
                <ul class="mt-4 space-y-2.5 text-[0.95rem] text-slate-300">
                    <li><a href="{{ route('ecommerce.home') }}">{{ __('Home') }}</a></li>
                    <li><a href="{{ route('ecommerce.offers') }}">{{ __('Offers') }}</a></li>
                    <li><a href="{{ route('ecommerce.wishlist') }}">{{ __('Wishlist') }}</a></li>
                    <li><a href="{{ route('ecommerce.customer-panel') }}">{{ __('Customer Panel') }}</a></li>
                    <li><a href="{{ route('ecommerce.contact') }}">{{ __('Contact') }}</a></li>
                    <li><a href="{{ route('ecommerce.privacy') }}">{{ __('Privacy Policy') }}</a></li>
                    <li><a href="{{ route('ecommerce.terms') }}">{{ __('Terms and Conditions') }}</a></li>
                </ul>
            </div>
            <div>
                <h4 class="text-xs font-bold uppercase tracking-[0.16em] text-white">{{ __('Follow Us') }}</h4>
                <p class="mt-4 text-[0.95rem] text-slate-300">{{ __('Stay connected with the latest drops and store updates.') }}</p>
                @php
                    $socialLinks = array_filter([
                        'facebook' => $socialFacebook ?? null,
                        'instagram' => $socialInstagram ?? null,
                        'tiktok' => $socialTiktok ?? null,
                        'linkedin' => $socialLinkedin ?? null,
                    ]);
                @endphp
                @if(!empty($socialLinks))
                    <div class="ec-social-links mt-5">
                        @if(!empty($socialLinks['facebook']))
                            <a href="{{ $socialLinks['facebook'] }}" target="_blank" rel="noopener noreferrer" class="ec-social-link" aria-label="Facebook" title="Facebook">
                                <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M14 13.5h2.5l.5-3H14V8.75c0-.88.18-1.25 1.37-1.25H17V5.02C16.69 4.98 15.85 4.9 14.86 4.9 12.76 4.9 11.3 6.1 11.3 8.48V10.5H9v3h2.3V19h2.7v-5.5Z"/></svg>
                            </a>
                        @endif
                        @if(!empty($socialLinks['instagram']))
                            <a href="{{ $socialLinks['instagram'] }}" target="_blank" rel="noopener noreferrer" class="ec-social-link" aria-label="Instagram" title="Instagram">
                                <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M7 3h10a4 4 0 0 1 4 4v10a4 4 0 0 1-4 4H7a4 4 0 0 1-4-4V7a4 4 0 0 1 4-4Zm10 2H7a2 2 0 0 0-2 2v10a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2Zm-5 3.2A3.8 3.8 0 1 1 8.2 12 3.8 3.8 0 0 1 12 8.2Zm0 1.6A2.2 2.2 0 1 0 14.2 12 2.2 2.2 0 0 0 12 9.8Zm4.55-2.85a.95.95 0 1 1-.95.95.95.95 0 0 1 .95-.95Z"/></svg>
                            </a>
                        @endif
                        @if(!empty($socialLinks['tiktok']))
                            <a href="{{ $socialLinks['tiktok'] }}" target="_blank" rel="noopener noreferrer" class="ec-social-link" aria-label="TikTok" title="TikTok">
                                <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M16.6 5.82A5.8 5.8 0 0 1 14.4 3h-2.6v12.2a2.5 2.5 0 1 1-1.8-2.4V10a5.1 5.1 0 1 0 4.4 5.05V9.2a8.3 8.3 0 0 0 4.8 1.5V8.1a5.9 5.9 0 0 1-2.6-.9 5.8 5.8 0 0 1-2-.38Z"/></svg>
                            </a>
                        @endif
                        @if(!empty($socialLinks['linkedin']))
                            <a href="{{ $socialLinks['linkedin'] }}" target="_blank" rel="noopener noreferrer" class="ec-social-link" aria-label="LinkedIn" title="LinkedIn">
                                <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M6.94 8.5H4.1V19.5h2.84V8.5ZM5.52 4.5a1.66 1.66 0 1 0 0 3.32 1.66 1.66 0 0 0 0-3.32ZM19.9 19.5h-2.83v-5.35c0-1.28-.02-2.92-1.78-2.92-1.78 0-2.05 1.39-2.05 2.83V19.5H10.4V8.5h2.72v1.5h.04c.38-.72 1.3-1.48 2.68-1.48 2.87 0 3.4 1.89 3.4 4.34V19.5Z"/></svg>
                            </a>
                        @endif
                    </div>
                @endif
            </div>
        </div>
        <div class="border-t border-white/10">
            <div class="mx-auto flex max-w-7xl flex-wrap items-center justify-between gap-3 px-4 py-4 text-sm text-slate-400 sm:px-6 lg:px-8">
                <p>© {{ date('Y') }} {{ $storeName }}. {{ __('All rights reserved.') }}</p>
                <div class="flex flex-wrap items-center gap-4">
                    <a href="{{ route('ecommerce.privacy') }}" class="hover:text-teal-100">{{ __('Privacy Policy') }}</a>
                    <a href="{{ route('ecommerce.terms') }}" class="hover:text-teal-100">{{ __('Terms and Conditions') }}</a>
                </div>
            </div>
        </div>
    </footer>
    <script>
        window.ecommerceCart = {
            urls: {
                cart: @json(route('ecommerce.cart')),
                add: @json(route('ecommerce.cart.add')),
                update: @json(route('ecommerce.cart.update')),
                remove: @json(route('ecommerce.cart.remove')),
                clear: @json(route('ecommerce.cart.clear')),
                image: @json(\Illuminate\Support\Str::beforeLast(route('ecommerce.image', ['file' => '__file__']), '/__file__')),
                login: @json(route('ecommerce.login.submit')),
                logoutAdminForCustomer: @json(route('ecommerce.logout-admin-for-customer')),
                checkout: @json(route('ecommerce.checkout')),
                searchSuggest: @json(route('ecommerce.search.suggest')),
                searchResults: @json(route('ecommerce.category', ['slug' => 'all'])),
                wishlistToggle: @json(route('ecommerce.wishlist.toggle')),
                wishlistRemove: @json(route('ecommerce.wishlist.remove')),
                productRatingBase: @json(\Illuminate\Support\Str::beforeLast(route('ecommerce.item-details.rating', ['id' => 0]), '/0/rating')),
            },
            csrf: document.querySelector('meta[name="csrf-token"]')?.content || '',
            isLoggedIn: @json(!empty($isEcommerceCustomer)),
            isAdmin: @json(!empty($isBackendAdmin)),
            money: @json($moneySettings ?? companyAmountSettings()),
        };

        window.formatEcommerceMoney = function (amount) {
            const cfg = window.ecommerceCart?.money || {};
            const precision = Math.max(0, Number(cfg.precision ?? 2));
            const currency = String(cfg.currency ?? '$');
            const position = cfg.currency_position || 'Before Amount';
            let thousand = cfg.thousands_separator ?? ',';
            let decimal = cfg.decimals_separator ?? '.';
            if (thousand === 'space') thousand = ' ';
            if (decimal === 'space') decimal = ' ';

            const n = Number(amount || 0);
            const fixed = Math.abs(n).toFixed(precision);
            const parts = fixed.split('.');
            parts[0] = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, thousand);
            const formatted = precision > 0 ? parts.join(decimal) : parts[0];
            const signed = n < 0 ? '-' + formatted : formatted;
            return position === 'Before Amount' ? (currency + signed) : (signed + currency);
        };

        (function () {
            const forms = document.querySelectorAll('[data-product-search]');
            if (!forms.length) return;

            const money = (amount) => window.formatEcommerceMoney(amount);
            let timer = null;

            const hideDropdown = (form) => {
                const dropdown = form.querySelector('[data-search-dropdown]');
                if (dropdown) dropdown.classList.add('hidden');
            };

            const showDropdown = (form) => {
                const dropdown = form.querySelector('[data-search-dropdown]');
                if (dropdown) dropdown.classList.remove('hidden');
            };

            const renderResults = (form, items) => {
                const list = form.querySelector('[data-search-results]');
                const empty = form.querySelector('[data-search-empty]');
                if (!list || !empty) return;
                list.innerHTML = '';
                if (!items.length) {
                    empty.classList.remove('hidden');
                    showDropdown(form);
                    return;
                }
                empty.classList.add('hidden');
                items.forEach((item) => {
                    const li = document.createElement('li');
                    li.innerHTML = `
                        <a href="${item.url}" class="flex items-center gap-3 rounded-md px-2 py-1.5 text-slate-700 hover:bg-slate-100 hover:text-brand-700">
                            <img src="${item.image}" alt="" class="h-10 w-10 rounded object-cover bg-slate-100">
                            <span class="min-w-0 flex-1">
                                <span class="block truncate font-medium">${item.name}</span>
                                <span class="block text-xs text-slate-500">${money(item.price)}</span>
                            </span>
                        </a>`;
                    list.appendChild(li);
                });
                showDropdown(form);
            };

            const fetchSuggestions = async (form, query) => {
                if (!query || query.trim().length < 1) {
                    hideDropdown(form);
                    return;
                }
                try {
                    const url = `${window.ecommerceCart.urls.searchSuggest}?q=${encodeURIComponent(query.trim())}`;
                    const response = await fetch(url, {
                        headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    });
                    const data = await response.json();
                    renderResults(form, data.items || []);
                } catch (e) {
                    hideDropdown(form);
                }
            };

            forms.forEach((form) => {
                const input = form.querySelector('[data-search-input]');
                if (!input) return;

                input.addEventListener('input', () => {
                    clearTimeout(timer);
                    timer = setTimeout(() => fetchSuggestions(form, input.value), 250);
                });

                input.addEventListener('focus', () => {
                    if (input.value.trim()) fetchSuggestions(form, input.value);
                });

                document.addEventListener('click', (event) => {
                    if (!form.contains(event.target)) hideDropdown(form);
                });
            });
        })();

        (function () {
            const openBtn = document.getElementById('openCartOffcanvas');
            const openBottomCartBtn = document.getElementById('openCartOffcanvasBottom');
            const closeBtn = document.getElementById('closeCartOffcanvas');
            const panel = document.getElementById('cartOffcanvas');
            const backdrop = document.getElementById('cartBackdrop');
            const openMobileSearch = document.getElementById('openMobileSearch');
            const closeMobileSearch = document.getElementById('closeMobileSearch');
            const mobileSearchPanel = document.getElementById('mobileSearchPanel');
            const mobileSearchBackdrop = document.getElementById('mobileSearchBackdrop');
            const openCategoryPanel = document.getElementById('openCategoryPanel');
            const closeCategoryPanel = document.getElementById('closeCategoryPanel');
            const categoryPanel = document.getElementById('categoryPanel');
            const categoryBackdrop = document.getElementById('categoryBackdrop');
            const openProfilePanel = document.getElementById('openProfilePanel');
            const closeProfilePanel = document.getElementById('closeProfilePanel');
            const profilePanel = document.getElementById('profilePanel');
            const profileBackdrop = document.getElementById('profileBackdrop');
            const cartItemsList = document.getElementById('cartItemsList') || document.getElementById('cartItemsContainer');
            const emptyCartMessage = document.getElementById('emptyCartMessage');
            const cartSubtotal = document.getElementById('cartSubtotal');
            const cartTax = document.getElementById('cartTax');
            const cartTaxInclusive = document.getElementById('cartTaxInclusive');
            const cartTaxInclusiveNote = document.getElementById('cartTaxInclusiveNote');
            const cartTotal = document.getElementById('cartTotal');
            const headerCartCount = document.getElementById('headerCartCount');
            const bottomCartCount = document.getElementById('bottomCartCount');
            const proceedBtn = document.getElementById('proceedToCheckoutBtn');
            const loginBackdrop = document.getElementById('customerLoginBackdrop');
            const loginModal = document.getElementById('customerLoginModal');
            const closeLoginModal = document.getElementById('closeCustomerLoginModal');
            const loginForm = document.getElementById('customerLoginForm');
            const loginError = document.getElementById('customerLoginError');
            const adminBackdrop = document.getElementById('adminCheckoutBackdrop');
            const adminModal = document.getElementById('adminCheckoutModal');
            const closeAdminModal = document.getElementById('closeAdminCheckoutModal');
            const adminNoBtn = document.getElementById('adminCheckoutNoBtn');
            const adminYesBtn = document.getElementById('adminCheckoutYesBtn');
            const adminError = document.getElementById('adminCheckoutError');

            if (!openBtn || !closeBtn || !panel || !backdrop || !cartItemsList || !emptyCartMessage || !cartSubtotal) return;

            const lockScroll = () => {
                const scrollbar = Math.max(0, window.innerWidth - document.documentElement.clientWidth);
                document.documentElement.style.setProperty('--ec-scrollbar-compensation', scrollbar > 0 ? `${scrollbar}px` : '0px');
                document.body.classList.add('ec-scroll-locked');
            };

            const unlockScroll = () => {
                document.body.classList.remove('ec-scroll-locked');
                document.documentElement.style.removeProperty('--ec-scrollbar-compensation');
            };

            const openPanel = () => {
                panel.classList.remove('translate-x-full');
                backdrop.classList.remove('hidden');
                lockScroll();
            };

            const closePanel = () => {
                panel.classList.add('translate-x-full');
                backdrop.classList.add('hidden');
                unlockScroll();
            };

            const openLoginModal = () => {
                if (!loginModal || !loginBackdrop) return;
                loginBackdrop.classList.remove('hidden');
                loginModal.classList.remove('hidden');
                loginModal.classList.add('flex');
                lockScroll();
            };
            window.ecommerceOpenLogin = openLoginModal;

            const hideLoginModal = () => {
                if (!loginModal || !loginBackdrop) return;
                loginBackdrop.classList.add('hidden');
                loginModal.classList.add('hidden');
                loginModal.classList.remove('flex');
                unlockScroll();
            };

            const openAdminCheckoutModal = () => {
                if (!adminModal || !adminBackdrop) return;
                if (adminError) {
                    adminError.classList.add('hidden');
                    adminError.textContent = '';
                }
                closePanel();
                adminBackdrop.classList.remove('hidden');
                adminModal.classList.remove('hidden');
                adminModal.classList.add('flex');
                lockScroll();
            };
            window.ecommerceOpenAdminCheckoutBlock = openAdminCheckoutModal;

            const hideAdminCheckoutModal = () => {
                if (!adminModal || !adminBackdrop) return;
                adminBackdrop.classList.add('hidden');
                adminModal.classList.add('hidden');
                adminModal.classList.remove('flex');
                unlockScroll();
            };

            const isAdminSession = () => {
                return !!(window.ecommerceCart.isAdmin || proceedBtn?.getAttribute('data-is-admin') === '1');
            };

            const goToCheckoutOrAuth = () => {
                if (isAdminSession()) {
                    openAdminCheckoutModal();
                    return;
                }
                const loggedIn = window.ecommerceCart.isLoggedIn || proceedBtn?.getAttribute('data-logged-in') === '1';
                if (!loggedIn) {
                    openLoginModal();
                    return;
                }
                window.location.href = proceedBtn?.getAttribute('data-checkout-url') || window.ecommerceCart.urls.checkout;
            };
            window.ecommerceGoToCheckoutOrAuth = goToCheckoutOrAuth;

            const showToast = (message, type = 'success') => {
                const host = document.getElementById('ecToastHost');
                if (!host || !message) return;
                const toast = document.createElement('div');
                toast.className = `ec-toast ec-toast-${type}`;
                toast.textContent = message;
                host.appendChild(toast);
                requestAnimationFrame(() => toast.classList.add('is-visible'));
                window.setTimeout(() => {
                    toast.classList.remove('is-visible');
                    window.setTimeout(() => toast.remove(), 220);
                }, 2200);
            };
            window.ecommerceToast = showToast;

            openBtn.addEventListener('click', openPanel);
            if (openBottomCartBtn) openBottomCartBtn.addEventListener('click', openPanel);
            closeBtn.addEventListener('click', closePanel);
            backdrop.addEventListener('click', closePanel);
            if (closeLoginModal) closeLoginModal.addEventListener('click', hideLoginModal);
            if (loginBackdrop) loginBackdrop.addEventListener('click', hideLoginModal);
            if (closeAdminModal) closeAdminModal.addEventListener('click', hideAdminCheckoutModal);
            if (adminNoBtn) adminNoBtn.addEventListener('click', hideAdminCheckoutModal);
            if (adminBackdrop) adminBackdrop.addEventListener('click', hideAdminCheckoutModal);

            const money = (amount) => window.formatEcommerceMoney(amount);

            const productImage = (item) => {
                if (item.image_is_url) return item.image;
                return `${window.ecommerceCart.urls.image}/${item.image}`;
            };

            const setCountBadges = (count) => {
                if (headerCartCount) {
                    headerCartCount.textContent = String(count);
                    headerCartCount.classList.toggle('is-empty', count <= 0);
                    headerCartCount.classList.remove('hidden');
                }
                if (bottomCartCount) {
                    bottomCartCount.textContent = String(count);
                    bottomCartCount.classList.toggle('hidden', count <= 0);
                }
            };

            const renderCart = (payload) => {
                const items = payload.items || [];
                cartItemsList.querySelectorAll('.cart-item').forEach((el) => el.remove());

                const escapeHtml = (value) => String(value ?? '')
                    .replace(/&/g, '&amp;')
                    .replace(/</g, '&lt;')
                    .replace(/>/g, '&gt;')
                    .replace(/"/g, '&quot;')
                    .replace(/'/g, '&#39;');

                items.forEach((item) => {
                    const lineTotal = Number(item.price) * Number(item.qty);
                    const row = document.createElement('div');
                    row.className = 'cart-item flex gap-3 rounded-xl border border-slate-200 p-3.5';
                    row.dataset.productId = item.id;
                    row.dataset.unitPrice = String(item.price);
                    row.innerHTML = `
                        <img src="${productImage(item)}" alt="${escapeHtml(item.name)}" class="h-16 w-16 rounded-md object-cover">
                        <div class="flex-1 min-w-0">
                            <p class="ec-cart-item-title">${escapeHtml(item.name)}</p>
                            <div class="ec-qty-stepper" data-qty-stepper>
                                <button type="button" class="ec-qty-btn" data-qty-dec data-product-id="${item.id}" aria-label="Decrease">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.2" d="M5 12h14"/></svg>
                                </button>
                                <input type="number" min="1" class="ec-qty-input" data-qty-input data-product-id="${item.id}" value="${Number(item.qty) || 1}">
                                <button type="button" class="ec-qty-btn" data-qty-inc data-product-id="${item.id}" aria-label="Increase">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.2" d="M12 5v14M5 12h14"/></svg>
                                </button>
                            </div>
                            <p class="ec-cart-item-price mt-1.5">${money(lineTotal)}</p>
                        </div>
                        <button type="button" class="remove-cart-item h-fit rounded-full p-2 text-slate-400 hover:bg-rose-50 hover:text-rose-600" data-product-id="${item.id}" aria-label="Remove item">
                            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-trash">
                            <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                            <path d="M4 7l16 0" />
                            <path d="M10 11l0 6" />
                            <path d="M14 11l0 6" />
                            <path d="M5 7l1 12a2 2 0 0 0 2 2h8a2 2 0 0 0 2 -2l1 -12" />
                            <path d="M9 7v-3a1 1 0 0 1 1 -1h4a1 1 0 0 1 1 1v3" />
                        </svg>
                        </button>
                    `;
                    cartItemsList.insertBefore(row, emptyCartMessage);
                });

                cartSubtotal.textContent = money(payload.subtotal);
                if (cartTax) cartTax.textContent = money(payload.tax);
                const inclusiveTax = Number(payload.tax_inclusive || 0);
                if (cartTaxInclusive) cartTaxInclusive.textContent = money(inclusiveTax);
                if (cartTaxInclusiveNote) cartTaxInclusiveNote.classList.toggle('hidden', !(inclusiveTax > 0));
                if (cartTotal) cartTotal.textContent = money(payload.total);
                emptyCartMessage.classList.toggle('hidden', items.length > 0);
                setCountBadges(payload.count || 0);
            };

            const updateCartQty = async (productId, qty) => {
                try {
                    const payload = await requestJson(window.ecommerceCart.urls.update, {
                        product_id: productId,
                        qty: Math.max(0, Number(qty) || 0),
                    });
                    renderCart(payload);
                } catch (e) {
                    showToast(e.message || '{{ __('Unable to update cart') }}', 'error');
                    try {
                        const res = await fetch(window.ecommerceCart.urls.cart, {
                            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                        });
                        const payload = await res.json();
                        if (res.ok) renderCart(payload);
                    } catch (_) {}
                }
            };

            const requestJson = async (url, body = {}) => {
                const res = await fetch(url, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': window.ecommerceCart.csrf,
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    body: JSON.stringify(body),
                });
                const data = await res.json().catch(() => ({}));
                if (!res.ok) throw new Error(data.message || 'Request failed');
                return data;
            };

            if (adminYesBtn) {
                adminYesBtn.addEventListener('click', async () => {
                    if (adminError) {
                        adminError.classList.add('hidden');
                        adminError.textContent = '';
                    }
                    adminYesBtn.disabled = true;
                    try {
                        const payload = await requestJson(window.ecommerceCart.urls.logoutAdminForCustomer, {});
                        if (payload.csrf_token) {
                            window.ecommerceCart.csrf = payload.csrf_token;
                            const meta = document.querySelector('meta[name="csrf-token"]');
                            if (meta) meta.setAttribute('content', payload.csrf_token);
                        }
                        window.ecommerceCart.isAdmin = false;
                        if (proceedBtn) proceedBtn.setAttribute('data-is-admin', '0');
                        // Clear customer flag — they must login as customer after admin logout.
                        window.ecommerceCart.isLoggedIn = false;
                        if (proceedBtn) proceedBtn.setAttribute('data-logged-in', '0');
                        hideAdminCheckoutModal();
                        openLoginModal();
                    } catch (e) {
                        if (adminError) {
                            adminError.textContent = e.message || @json(__('Unable to end admin session.'));
                            adminError.classList.remove('hidden');
                        }
                    } finally {
                        adminYesBtn.disabled = false;
                    }
                });
            }

            window.ecommerceCartAdd = async (productId, qty = 1, price = null, open = false) => {
                const body = { product_id: productId, qty };
                if (price !== null && price !== undefined) body.price = price;
                const payload = await requestJson(window.ecommerceCart.urls.add, body);
                renderCart(payload);
                if (open) {
                    openPanel();
                } else {
                    showToast(payload.message || '{{ __('Added to cart.') }}', 'success');
                }
                return payload;
            };

            if (proceedBtn) {
                proceedBtn.addEventListener('click', () => {
                    goToCheckoutOrAuth();
                });
            }

            if (loginForm) {
                loginForm.addEventListener('submit', async (event) => {
                    event.preventDefault();
                    if (loginError) {
                        loginError.classList.add('hidden');
                        loginError.textContent = '';
                    }
                    const formData = new FormData(loginForm);
                    try {
                        const payload = await requestJson(window.ecommerceCart.urls.login, {
                            email: formData.get('email'),
                            password: formData.get('password'),
                        });
                        window.ecommerceCart.isLoggedIn = true;
                        if (proceedBtn) proceedBtn.setAttribute('data-logged-in', '1');
                        hideLoginModal();
                        window.location.href = payload.redirect || window.ecommerceCart.urls.checkout;
                    } catch (e) {
                        if (loginError) {
                            loginError.textContent = e.message || 'Login failed';
                            loginError.classList.remove('hidden');
                        }
                    }
                });
            }

            cartItemsList.addEventListener('click', async (event) => {
                const button = event.target.closest('.remove-cart-item');
                if (!button) return;
                const productId = Number(button.getAttribute('data-product-id'));
                try {
                    const payload = await requestJson(window.ecommerceCart.urls.remove, { product_id: productId });
                    renderCart(payload);
                } catch (e) {
                    console.error(e);
                }
            });

            cartItemsList.addEventListener('click', async (event) => {
                const dec = event.target.closest('[data-qty-dec]');
                const inc = event.target.closest('[data-qty-inc]');
                if (!dec && !inc) return;

                const btn = dec || inc;
                const productId = Number(btn.getAttribute('data-product-id'));
                const row = btn.closest('.cart-item');
                const input = row?.querySelector('[data-qty-input]');
                const current = Number(input?.value || 1);
                const next = dec ? current - 1 : current + 1;
                await updateCartQty(productId, next);
            });

            cartItemsList.addEventListener('change', async (event) => {
                const input = event.target.closest('[data-qty-input]');
                if (!input) return;
                const productId = Number(input.getAttribute('data-product-id'));
                const qty = Math.max(1, Number(input.value) || 1);
                input.value = String(qty);
                await updateCartQty(productId, qty);
            });

            document.addEventListener('click', async (event) => {
                const btn = event.target.closest('[data-add-to-cart]');
                if (!btn) return;
                event.preventDefault();
                const productId = Number(btn.getAttribute('data-product-id'));
                const qty = Number(btn.getAttribute('data-qty') || 1);
                const priceAttr = btn.getAttribute('data-price');
                const price = priceAttr !== null && priceAttr !== '' ? Number(priceAttr) : null;
                const buyNow = btn.hasAttribute('data-buy-now');
                try {
                    btn.disabled = true;
                    await window.ecommerceCartAdd(productId, qty, price, false);
                    if (buyNow) {
                        goToCheckoutOrAuth();
                    }
                } catch (e) {
                    showToast(e.message || '{{ __('Unable to add to cart') }}', 'error');
                } finally {
                    btn.disabled = false;
                }
            });

            if (openMobileSearch && closeMobileSearch && mobileSearchPanel && mobileSearchBackdrop) {
                const showMobileSearch = () => {
                    mobileSearchPanel.classList.remove('-translate-y-full');
                    mobileSearchBackdrop.classList.remove('hidden');
                    lockScroll();
                };

                const hideMobileSearch = () => {
                    mobileSearchPanel.classList.add('-translate-y-full');
                    mobileSearchBackdrop.classList.add('hidden');
                    unlockScroll();
                };

                openMobileSearch.addEventListener('click', showMobileSearch);
                closeMobileSearch.addEventListener('click', hideMobileSearch);
                mobileSearchBackdrop.addEventListener('click', hideMobileSearch);
            }

            if (openCategoryPanel && closeCategoryPanel && categoryPanel && categoryBackdrop) {
                const showCategoryPanel = () => {
                    categoryPanel.classList.remove('-translate-x-full');
                    categoryBackdrop.classList.remove('hidden');
                    lockScroll();
                };

                const hideCategoryPanel = () => {
                    categoryPanel.classList.add('-translate-x-full');
                    categoryBackdrop.classList.add('hidden');
                    unlockScroll();
                };

                openCategoryPanel.addEventListener('click', showCategoryPanel);
                closeCategoryPanel.addEventListener('click', hideCategoryPanel);
                categoryBackdrop.addEventListener('click', hideCategoryPanel);
            }

            if (openProfilePanel && closeProfilePanel && profilePanel && profileBackdrop) {
                const showProfilePanel = () => {
                    profilePanel.classList.remove('translate-x-full');
                    profileBackdrop.classList.remove('hidden');
                    lockScroll();
                };

                const hideProfilePanel = () => {
                    profilePanel.classList.add('translate-x-full');
                    profileBackdrop.classList.add('hidden');
                    unlockScroll();
                };

                openProfilePanel.addEventListener('click', showProfilePanel);
                closeProfilePanel.addEventListener('click', hideProfilePanel);
                profileBackdrop.addEventListener('click', hideProfilePanel);
            }

            @if(session('open_admin_checkout_block'))
                openAdminCheckoutModal();
            @elseif(session('open_customer_login'))
                openLoginModal();
            @endif
        })();

        (function () {
            document.querySelectorAll('[data-ec-lang]').forEach((wrap) => {
                const btn = wrap.querySelector('.ec-lang-btn');
                const menu = wrap.querySelector('.ec-lang-menu');
                if (!btn || !menu) return;

                const close = () => {
                    wrap.classList.remove('is-open');
                    btn.setAttribute('aria-expanded', 'false');
                    menu.hidden = true;
                };
                const open = () => {
                    wrap.classList.add('is-open');
                    btn.setAttribute('aria-expanded', 'true');
                    menu.hidden = false;
                };

                btn.addEventListener('click', (e) => {
                    e.stopPropagation();
                    wrap.classList.contains('is-open') ? close() : open();
                });
                document.addEventListener('click', (e) => {
                    if (!wrap.contains(e.target)) close();
                });
                document.addEventListener('keydown', (e) => {
                    if (e.key === 'Escape') close();
                });
            });
        })();

        (function () {
            if (!window.ecommerceCart?.urls?.wishlistToggle) return;

            const syncCounts = (count) => {
                document.querySelectorAll('[data-wishlist-count]').forEach((el) => {
                    el.textContent = String(count || 0);
                    el.classList.toggle('hidden', !(count > 0));
                });
            };

            const applyBtnState = (btn, inWishlist) => {
                btn.setAttribute('data-in-wishlist', inWishlist ? '1' : '0');
                btn.classList.toggle('is-active', inWishlist);
                btn.classList.toggle('is-wishlisted', inWishlist);
                const icon = btn.querySelector('svg');
                if (icon) icon.setAttribute('fill', inWishlist ? 'currentColor' : 'none');
                const label = btn.querySelector('[data-wishlist-label]');
                if (label) {
                    label.textContent = inWishlist
                        ? @json(__('Wishlisted'))
                        : @json(__('Add to Wishlist'));
                }
                btn.setAttribute(
                    'aria-label',
                    inWishlist ? @json(__('Remove from wishlist')) : @json(__('Add to wishlist'))
                );
            };

            document.addEventListener('click', async (event) => {
                const btn = event.target.closest('[data-wishlist-toggle]');
                if (!btn) return;
                event.preventDefault();
                event.stopPropagation();

                const productId = Number(btn.getAttribute('data-product-id'));
                if (!productId) return;

                btn.disabled = true;
                try {
                    const res = await fetch(window.ecommerceCart.urls.wishlistToggle, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': window.ecommerceCart.csrf,
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                        body: JSON.stringify({ product_id: productId }),
                    });
                    const data = await res.json().catch(() => ({}));
                    if (!res.ok) throw new Error(data.message || 'Wishlist update failed');

                    const inWishlist = !!data.added;
                    document.querySelectorAll(`[data-wishlist-toggle][data-product-id="${productId}"]`).forEach((el) => {
                        applyBtnState(el, inWishlist);
                    });
                    syncCounts(data.count || 0);
                } catch (e) {
                    console.error(e);
                } finally {
                    btn.disabled = false;
                }
            });
        })();
    </script>
    <script>
        (function () {
            const backdrop = document.getElementById('ratingBackdrop');
            const modal = document.getElementById('ratingModal');
            const form = document.getElementById('ratingForm');
            const productIdInput = document.getElementById('ratingProductId');
            const ratingValueInput = document.getElementById('ratingValue');
            const commentInput = document.getElementById('ratingComment');
            const recommendInput = document.getElementById('ratingRecommend');
            const errorEl = document.getElementById('ratingFormError');
            const starPicker = document.getElementById('ratingStarPicker');
            const closeBtn = document.getElementById('closeRatingModal');
            const cancelBtn = document.getElementById('cancelRatingModal');
            const submitBtn = document.getElementById('submitRatingBtn');
            if (!modal || !form || !backdrop) return;

            const toast = (msg, type) => {
                if (typeof window.ecommerceToast === 'function') window.ecommerceToast(msg, type);
                else alert(msg);
            };

            const setStars = (value) => {
                const rating = Math.max(1, Math.min(5, parseInt(value, 10) || 5));
                ratingValueInput.value = String(rating);
                starPicker.querySelectorAll('[data-star]').forEach((btn) => {
                    const star = parseInt(btn.getAttribute('data-star'), 10);
                    btn.classList.toggle('is-on', star <= rating);
                });
                if (recommendInput) recommendInput.checked = rating >= 4;
            };

            const openModal = (productId, preset = null) => {
                productIdInput.value = String(productId);
                setStars(preset?.rating || 5);
                if (commentInput) commentInput.value = preset?.comment || '';
                if (recommendInput && typeof preset?.recommend !== 'undefined') {
                    recommendInput.checked = !!preset.recommend;
                }
                if (errorEl) {
                    errorEl.textContent = '';
                    errorEl.classList.add('hidden');
                }
                backdrop.classList.remove('hidden');
                modal.classList.remove('hidden');
                modal.classList.add('flex');
                document.documentElement.classList.add('overflow-hidden');
            };

            const closeModal = () => {
                backdrop.classList.add('hidden');
                modal.classList.add('hidden');
                modal.classList.remove('flex');
                document.documentElement.classList.remove('overflow-hidden');
            };

            const beginRate = async (productId) => {
                if (!window.ecommerceCart?.isLoggedIn) {
                    toast(@json(__("You're not logged in yet, login first.")), 'error');
                    if (typeof window.ecommerceOpenLogin === 'function') {
                        window.setTimeout(() => window.ecommerceOpenLogin(), 350);
                    }
                    return;
                }

                const preset = (window.__productRatingPreset && String(window.__productRatingProductId) === String(productId))
                    ? window.__productRatingPreset
                    : null;
                openModal(productId, preset);
            };

            window.ecommerceOpenRating = beginRate;
            window.ecommerceCloseRating = closeModal;

            starPicker?.querySelectorAll('[data-star]').forEach((btn) => {
                btn.addEventListener('click', () => setStars(btn.getAttribute('data-star')));
            });

            [closeBtn, cancelBtn, backdrop].forEach((el) => {
                el?.addEventListener('click', closeModal);
            });

            document.addEventListener('click', (event) => {
                const trigger = event.target.closest('[data-rate-product]');
                if (!trigger) return;
                event.preventDefault();
                const productId = trigger.getAttribute('data-product-id');
                if (productId) beginRate(productId);
            });

            form.addEventListener('submit', async (event) => {
                event.preventDefault();
                const productId = productIdInput.value;
                if (!productId) return;
                if (submitBtn) submitBtn.disabled = true;
                if (errorEl) {
                    errorEl.textContent = '';
                    errorEl.classList.add('hidden');
                }

                try {
                    const base = window.ecommerceCart?.urls?.productRatingBase || '/ecommerce/item-details';
                    const res = await fetch(`${base.replace(/\/$/, '')}/${encodeURIComponent(productId)}/rating`, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': window.ecommerceCart?.csrf || '',
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                        body: JSON.stringify({
                            rating: parseInt(ratingValueInput.value, 10) || 5,
                            comment: commentInput?.value || '',
                            recommend: !!recommendInput?.checked,
                        }),
                    });
                    const data = await res.json().catch(() => ({}));
                    if (res.status === 401 || data.login_required) {
                        closeModal();
                        toast(data.message || @json(__("You're not logged in yet, login first.")), 'error');
                        if (typeof window.ecommerceOpenLogin === 'function') window.ecommerceOpenLogin();
                        return;
                    }
                    if (!res.ok) throw new Error(data.message || @json(__('Unable to save rating right now.')));

                    toast(data.message || @json(__('Thanks for your review!')), 'success');
                    closeModal();
                    if (typeof window.ecommerceOnRatingSaved === 'function') {
                        window.ecommerceOnRatingSaved(data.panel || null, productId);
                    } else if (window.location.pathname.includes('/item-details/')) {
                        window.location.reload();
                    }
                } catch (e) {
                    if (errorEl) {
                        errorEl.textContent = e.message || @json(__('Unable to save rating right now.'));
                        errorEl.classList.remove('hidden');
                    } else {
                        toast(e.message || @json(__('Unable to save rating right now.')), 'error');
                    }
                } finally {
                    if (submitBtn) submitBtn.disabled = false;
                }
            });
        })();
    </script>
    <script>
        (function () {
            document.addEventListener('click', function (e) {
                const btn = e.target.closest('[data-ec-password-toggle]');
                if (!btn) return;
                const wrap = btn.closest('.ec-password-field');
                const input = wrap ? wrap.querySelector('input') : null;
                if (!input) return;

                const show = input.type === 'password';
                input.type = show ? 'text' : 'password';
                btn.setAttribute('aria-label', show ? @json(__('Hide password')) : @json(__('Show password')));

                const openIcon = btn.querySelector('.ec-eye-open');
                const closedIcon = btn.querySelector('.ec-eye-closed');
                if (openIcon && closedIcon) {
                    openIcon.classList.toggle('hidden', show);
                    closedIcon.classList.toggle('hidden', !show);
                }
            });
        })();
    </script>
    @stack('scripts')
</body>
</html>
