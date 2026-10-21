@extends('auth.auth-layout')
@section('page_title', __('Login'))
@section('auth_content')

@php
    $demoCredentials = null;
    if (config('app.is_demo')) {
        $jsonPath = resource_path('demo/login.json');
        if (file_exists($jsonPath)) {
            $demoCredentials = json_decode(file_get_contents($jsonPath), true) ?? [];
        }
    }
@endphp

@if(session('success'))
    {!! insertSuccess(session('success')) !!}
@endif
@if(session('error'))
    {!! insertFailed(session('error')) !!}
@endif
@if(request('session_expired') || session('session_expired'))
    {!! insertFailed(__('Your session has expired. Please log in again.')) !!}
@endif


<!-- Login -->
<div class="card">
    <div class="card-body">
        <!-- Logo -->
        <div class="app-brand justify-content-center mb-6">
            <a href="{{ route('login') }}" class="app-brand-link login-page">
                <img src="{!! lpFaviconLogoUrl(asset('public/uploads/whitelabel/' . (getWhiteLabel('site_logo') ?? 'initial-logo.png'))) !!}" alt="Site Logo" class="app-brand-logo">
            </a>
        </div>
        <!-- /Logo -->
        <h4 class="mb-3">{{ __('Login') }}</h4>
        <form id="formAuthentication" method="POST" action="{{ route('login') }}">
            @csrf
            <div class="mb-6 form-control-validation">
                <label for="email" class="form-label">{{ __('Email or Phone') }} {!! requiredField() !!}</label>
                <input 
                    type="text" 
                    class="form-control @error('email') is-invalid @enderror" 
                    id="email" 
                    name="email" 
                    value="{{ old('email', ($demoCredentials ?? [])['email'] ?? '') }}" 
                    placeholder="{{ __('Enter your Email or Phone') }}" 
                    autocomplete="username"
                    autofocus 
                    aria-invalid="{{ $errors->has('email') ? 'true' : 'false' }}"
                    @error('email') aria-describedby="email-error" @enderror
                />
                @error('email')
                    <div id="email-error" class="invalid-feedback d-block">{{ $message }}</div>
                @enderror
            </div>
            <div class="mb-6 form-password-toggle form-control-validation">
                <label class="form-label" for="password">{{ __('Password') }} {!! requiredField() !!}</label>
                <div class="input-group input-group-merge">
                    <input 
                        type="password" 
                        id="password" 
                        class="form-control @error('password') is-invalid @enderror" 
                        name="password" 
                        value="{{ old('password', ($demoCredentials ?? [])['password'] ?? '') }}" 
                        placeholder="{{ __('Enter Your Password') }}" 
                        @error('password') aria-describedby="password-error" @enderror
                        aria-invalid="{{ $errors->has('password') ? 'true' : 'false' }}"
                    />
                    <span class="input-group-text cursor-pointer">
                        <i class="icon-base ti tabler-eye-off"></i>
                    </span>
                </div>
                @error('password')
                    <div id="password-error" class="invalid-feedback d-block">{{ $message }}</div>
                @enderror
            </div>
            <div class="my-4">
                <div class="d-flex justify-content-end">
                    <a href="{{ route('password.request') }}">
                        <p class="mb-0">{{ __('Forgot Password?') }}</p>
                    </a>
                </div>
            </div>
            @if (config('saas.enabled') && Route::has('saas.register'))
            <div class="d-flex justify-content-between gap-3 mt-4">
                <button class="btn btn-primary w-100" type="submit">{{ __('Login') }}</button>
                <a href="{{ route('saas.register') }}" class="btn btn-outline-primary w-100">{{ __('Register') }}</a>
            </div>
            @else
            <div class="text-center mt-4">
                <button class="btn btn-primary d-grid w-100" type="submit">{{ __('Login') }}</button>
            </div>
            @endif
        </form>
    </div>
</div>
<!-- /Login -->
@endsection