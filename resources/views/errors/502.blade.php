<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>502 - {{ __('Bad Gateway') }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Public+Sans:ital,wght@0,300;0,400;0,500;0,600;0,700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('public/backend_assets/vendor/css/core.css') }}">
    <link rel="stylesheet" href="{{ asset('public/backend_assets/vendor/css/theme-default.css') }}">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@latest/tabler-icons.min.css">
    <link rel="stylesheet" href="{{ asset('public/backend_assets/vendor/fonts/iconify-icons.css') }}" />
    <style>
        :root { --bs-body-font-family: 'Public Sans', sans-serif; }
        body {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 50%, #dee2e6 100%);
            padding: 1rem;
        }
        .error-page-card {
            width: 440px;
            border: none;
            border-radius: 0.5rem;
            box-shadow: 0 0.25rem 1.5rem rgba(0,0,0,.08);
        }
        .error-icon-wrap {
            width: 5rem;
            height: 5rem;
            border-radius: 50%;
            background: rgba(220, 53, 69, 0.12);
            color: #dc3545;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 1.25rem;
        }
        .error-icon-wrap i { font-size: 2.5rem; }
        .error-code { font-size: 3.5rem; font-weight: 700; color: #dc3545; line-height: 1; margin-bottom: 0.5rem; }
        .btn-action { border-radius: 0.375rem; padding: 0.5rem 1rem; }
    </style>
</head>
<body>
    <div class="card error-page-card">
        <div class="card-body text-center p-5">
            <div class="error-icon-wrap">
                <i class="ti tabler-lock-off"></i>
            </div>
            <h1 class="error-code">502</h1>
            <h4 class="mb-2">{{ __('Bad Gateway') }}</h4>
            <a href="javascript:history.back()" class="btn btn-outline-secondary btn-action">
                <i class="ti tabler-arrow-left me-1"></i>{{ __('Go Back') }}
            </a>
        </div>
    </div>
</body>
</html>
