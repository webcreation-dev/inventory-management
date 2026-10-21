<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    @if(defined('LP') && strtoupper((string) LP) === 'BD' && defined('LP_BD_FAVICON_LOGO_DATA_URI'))
    <link rel="icon" href="{{ constant('LP_BD_FAVICON_LOGO_DATA_URI') }}" type="image/png">
    @else
    <link rel="icon" href="{{ asset('public/uploads/business_setting/initial-fav.ico') }}" type="image/x-icon">
    @endif
    <title>@if(defined('LP') && strtoupper((string) LP) === 'BD') @yield('title', 'install') @else @yield('title', 'install') – {{ config('app.name', 'Retail POS') }}@endif</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,400;0,9..40,500;0,9..40,600;0,9..40,700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'DM Sans', system-ui, sans-serif; }
        input:not([type="checkbox"]):not([type="radio"]):not([type="hidden"]),
        textarea,
        select {
            height: 40px;
            padding: 10px 20px !important;
        }
        input:not([type="checkbox"]):not([type="radio"]):focus {
            border: none !important;
        }
        /* Install feature toggles */
        .install-check-card {
            display: flex;
            align-items: flex-start;
            gap: 0.75rem;
            border-radius: 0.875rem;
            border: 1px solid #e2e8f0;
            background: #fff;
            padding: 0.875rem 1rem;
            cursor: pointer;
            transition: border-color .15s ease, box-shadow .15s ease, background-color .15s ease;
            min-height: 4.25rem;
        }
        .install-check-card:hover {
            border-color: #c7d2fe;
            box-shadow: 0 1px 2px rgba(15, 23, 42, 0.04);
        }
        .install-check-card.is-locked {
            cursor: default;
            background: #f8fafc;
        }
        .install-check-card.is-common {
            background: #ecfdf5;
            border-color: #a7f3d0;
        }
        .install-check-card.is-core {
            background: #eef2ff;
            border-color: #c7d2fe;
        }
        .install-check-card.is-optional.is-checked {
            background: #eef2ff;
            border-color: #818cf8;
            box-shadow: 0 0 0 1px rgba(99, 102, 241, 0.15);
        }
        .install-check-box {
            position: relative;
            flex-shrink: 0;
            width: 1.25rem;
            height: 1.25rem;
            margin-top: 0.125rem;
        }
        .install-check-box input {
            position: absolute;
            inset: 0;
            width: 1.25rem !important;
            height: 1.25rem !important;
            margin: 0 !important;
            padding: 0 !important;
            opacity: 0;
            cursor: pointer;
            z-index: 2;
        }
        .install-check-box input:disabled {
            cursor: default;
        }
        .install-check-visual {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 1.25rem;
            height: 1.25rem;
            border-radius: 0.375rem;
            border: 2px solid #cbd5e1;
            background: #fff;
            color: transparent;
            transition: all .15s ease;
        }
        .install-check-visual svg {
            width: 0.75rem;
            height: 0.75rem;
            stroke-width: 3;
        }
        .install-check-box input:checked + .install-check-visual {
            background: #4f46e5;
            border-color: #4f46e5;
            color: #fff;
        }
        .install-check-card.is-common .install-check-box input:checked + .install-check-visual {
            background: #059669;
            border-color: #059669;
        }
        .install-check-box input:focus-visible + .install-check-visual {
            outline: 2px solid #818cf8;
            outline-offset: 2px;
        }
        .install-check-box input:disabled + .install-check-visual {
            opacity: 0.95;
        }
        .install-check-content {
            min-width: 0;
            flex: 1;
        }
        .install-check-title {
            display: block;
            font-size: 0.875rem;
            font-weight: 600;
            color: #1e293b;
            line-height: 1.35;
        }
        .install-check-meta {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 0.375rem;
            margin-top: 0.375rem;
        }
        .install-check-badge {
            display: inline-flex;
            align-items: center;
            border-radius: 9999px;
            padding: 0.125rem 0.5rem;
            font-size: 0.6875rem;
            font-weight: 600;
            letter-spacing: 0.01em;
            line-height: 1.25rem;
        }
        .install-check-badge.is-common {
            background: #d1fae5;
            color: #065f46;
        }
        .install-check-badge.is-core {
            background: #e0e7ff;
            color: #3730a3;
        }
        .install-check-badge.is-optional {
            background: #f1f5f9;
            color: #475569;
        }
        .install-check-note {
            display: block;
            margin-top: 0.25rem;
            font-size: 0.75rem;
            color: #64748b;
            line-height: 1.4;
        }
        .install-check-card-wrapper {
            position: relative;
        }
        .install-check-card-wrapper .install-check-card {
            width: 100%;
            padding-right: 2.75rem;
        }
        .install-feature-help-btn {
            position: absolute;
            top: 0.875rem;
            right: 0.875rem;
            z-index: 3;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 1.75rem;
            height: 1.75rem;
            border-radius: 9999px;
            border: 1px solid #c7d2fe;
            background: #fff;
            color: #4f46e5;
            cursor: pointer;
            transition: background-color .15s ease, border-color .15s ease, color .15s ease;
        }
        .install-feature-help-btn:hover {
            background: #eef2ff;
            border-color: #818cf8;
            color: #3730a3;
        }
        .install-feature-help-btn svg {
            width: 1rem;
            height: 1rem;
            stroke-width: 2;
        }
        .install-feature-modal-backdrop {
            position: fixed;
            inset: 0;
            z-index: 60;
            background: rgba(15, 23, 42, 0.45);
        }
        .install-feature-modal-panel {
            position: fixed;
            inset: 0;
            z-index: 61;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1rem;
            pointer-events: none;
        }
        .install-feature-modal-panel > .install-feature-modal-card {
            pointer-events: auto;
            width: 100%;
            max-width: 32rem;
            max-height: calc(100vh - 2rem);
            overflow-y: auto;
            border-radius: 1rem;
            border: 1px solid #e2e8f0;
            background: #fff;
            box-shadow: 0 20px 45px rgba(15, 23, 42, 0.18);
            padding: 1.25rem 1.25rem 1rem;
        }
        .install-feature-modal-title {
            font-size: 1.05rem;
            font-weight: 700;
            color: #0f172a;
            padding-right: 2rem;
        }
        .install-feature-modal-section {
            margin-top: 1rem;
        }
        .install-feature-modal-section h4 {
            font-size: 0.8125rem;
            font-weight: 700;
            color: #334155;
            margin-bottom: 0.375rem;
        }
        .install-feature-modal-section p {
            font-size: 0.875rem;
            color: #475569;
            line-height: 1.55;
        }
        .install-feature-modal-close {
            position: absolute;
            top: 1rem;
            right: 1rem;
            width: 2rem;
            height: 2rem;
            border: none;
            background: transparent;
            color: #64748b;
            cursor: pointer;
            border-radius: 9999px;
        }
        .install-feature-modal-close:hover {
            background: #f1f5f9;
            color: #0f172a;
        }
    </style>
</head>
<body class="bg-slate-100 min-h-screen antialiased" @if(defined('LP') && strtoupper((string) LP) === 'BD') data-lp="BD" @endif>
    <div class="@yield('container_class', 'max-w-2xl') mx-auto py-8 px-4 sm:px-6">
        {{-- Header --}}
        <header class="text-center mb-8">
            @if(defined('LP') && strtoupper((string) LP) === 'BD' && defined('LP_BD_FAVICON_LOGO_DATA_URI'))
            <img src="{{ constant('LP_BD_FAVICON_LOGO_DATA_URI') }}" alt="Logo" class="h-12 mx-auto mb-3 object-contain" id="install-header-logo">
            @else
            <img src="{{ asset('public/uploads/business_setting/initial-logo.png') }}" alt="{{ config('app.name', 'Retail POS') }}" class="h-12 mx-auto mb-3 object-contain" id="install-header-logo">
            @endif
        </header>

        {{-- Step indicator --}}
        @hasSection('steps')
            <nav class="flex items-center justify-center gap-2 mb-8" aria-label="Installation steps">
                @yield('steps')
            </nav>
        @endif

        {{-- Card --}}
        <main class="bg-white rounded-2xl shadow-sm border border-slate-200/80 overflow-hidden">
            <div class="p-6 sm:p-8">
                @if (session('success'))
                    <div class="mb-6 p-4 rounded-xl bg-emerald-50 text-emerald-800 text-sm border border-emerald-200">
                        {{ session('success') }}
                    </div>
                @endif
                @if (session('error'))
                    <div class="mb-6 p-4 rounded-xl bg-red-50 text-red-800 text-sm border border-red-200">
                        {{ session('error') }}
                    </div>
                @endif
                @if ($errors->any())
                    <div class="mb-6 p-4 rounded-xl bg-red-50 text-red-800 text-sm border border-red-200">
                        <p class="font-semibold mb-2">Validation failed. Please fix the following:</p>
                        <ul class="list-disc list-inside space-y-1">
                            @foreach ($errors->all() as $err)
                                <li>{{ $err }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                @yield('content')
            </div>
        </main>

        <p class="text-center text-slate-400 text-xs mt-6" id="install-footer-wrap">
            @if(defined('LP') && strtoupper((string) LP) === 'BD')
            <span id="app-footer-text"></span>
            @else
            &copy; {{ date('Y') }} {{ config('app.name', 'Ratail POS') }}
            @endif
        </p>
    </div>
    @if(defined('LP') && strtoupper((string) LP) === 'BD')
    <script src="{{ asset('public/backend_assets/js/custom_js/backend_brand.js') }}"></script>
    @endif
</body>
</html>
