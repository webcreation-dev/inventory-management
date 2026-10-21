@php
    $brandStrong = $brandStrong ?? $brandColor ?? \Modules\Ecommerce\Support\BrandColor::DEFAULT;
    $brandAccent = $brandAccent ?? $brandStrong;
    $brandSoft = $brandSoft ?? '#ecfeff';
    $brandGlow = $brandGlow ?? 'rgba(13, 148, 136, 0.28)';
    $brandRgb = $brandRgb ?? '13, 148, 136';
    $displayName = $siteName ?? $companyName ?? config('app.name');
    $logoUrl = $siteLogo ?? null;
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title>{{ $subject ?? __('Verify your email') }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            line-height: 1.6;
            color: #1e293b;
            background-color: #eef2f6;
            margin: 0;
            padding: 0;
            -webkit-font-smoothing: antialiased;
        }
        .email-wrapper {
            width: 100%;
            background: #eef2f6;
            padding: 40px 16px;
        }
        .email-container {
            max-width: 580px;
            margin: 0 auto;
            background-color: #ffffff;
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 10px 40px rgba(15, 23, 42, 0.08);
            border: 1px solid #e2e8f0;
        }
        .email-header {
            color: #ffffff;
            padding: 36px 36px 32px;
            text-align: center;
        }
        .logo-wrap {
            margin: 0 auto 16px;
            display: inline-block;
            border-radius: 14px;
            line-height: 0;
        }
        .logo-wrap img {
            display: block;
            max-height: 56px;
            max-width: 220px;
            width: auto;
            height: auto;
        }
        .brand-mark {
            display: inline-block;
            width: 56px;
            height: 56px;
            line-height: 56px;
            border-radius: 14px;
            background: rgba(255, 255, 255, 0.18);
            border: 1px solid rgba(255, 255, 255, 0.3);
            font-size: 22px;
            font-weight: 700;
            letter-spacing: -0.03em;
            margin-bottom: 14px;
            color: #ffffff;
        }
        .email-header h1 {
            font-size: 22px;
            font-weight: 700;
            margin-bottom: 6px;
            letter-spacing: -0.02em;
            color: #ffffff;
        }
        .email-header .subtitle {
            font-size: 14px;
            opacity: 0.92;
            font-weight: 500;
            color: #ffffff;
        }
        .badge {
            display: inline-block;
            margin-top: 16px;
            padding: 6px 12px;
            border-radius: 999px;
            background: rgba(255, 255, 255, 0.18);
            font-size: 12px;
            font-weight: 600;
            letter-spacing: 0.04em;
            text-transform: uppercase;
            color: #ffffff;
        }
        .email-body {
            padding: 36px 36px 28px;
        }
        .greeting {
            font-size: 18px;
            font-weight: 700;
            color: #0f172a;
            margin-bottom: 12px;
        }
        .message-content {
            font-size: 15px;
            color: #475569;
            margin-bottom: 28px;
        }
        .message-content p {
            margin-bottom: 10px;
        }
        .cta-wrap {
            text-align: center;
            margin: 8px 0 28px;
        }
        .cta-button {
            display: inline-block;
            color: #ffffff !important;
            text-decoration: none;
            font-size: 15px;
            font-weight: 700;
            padding: 14px 32px;
            border-radius: 10px;
        }
        .hint {
            font-size: 13px;
            color: #64748b;
            text-align: center;
            margin-bottom: 20px;
        }
        .link-fallback {
            font-size: 12px;
            color: #64748b;
            word-break: break-all;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            padding: 12px 14px;
        }
        .features {
            margin-top: 28px;
            display: table;
            width: 100%;
        }
        .feature {
            display: table-cell;
            width: 33.33%;
            text-align: center;
            padding: 0 8px;
            vertical-align: top;
        }
        .feature-icon {
            display: inline-block;
            width: 36px;
            height: 36px;
            line-height: 36px;
            border-radius: 10px;
            font-size: 14px;
            font-weight: 700;
            margin-bottom: 8px;
        }
        .feature-title {
            font-size: 12px;
            font-weight: 700;
            color: #334155;
        }
        .email-footer {
            background-color: #f8fafc;
            padding: 22px 36px;
            border-top: 1px solid #e2e8f0;
            text-align: center;
        }
        .email-footer p {
            font-size: 12px;
            color: #64748b;
            margin: 0 0 4px;
        }
        .email-footer .brand {
            font-weight: 700;
            color: #334155;
            margin-bottom: 6px;
        }
    </style>
</head>
<body>
    <div class="email-wrapper">
        <div class="email-container">
            <div class="email-header" style="background: linear-gradient(135deg, {{ $brandStrong }} 0%, {{ $brandAccent }} 100%);">
                @if(!empty($logoUrl))
                    <div class="logo-wrap">
                        <img src="{{ $logoUrl }}" alt="{{ $displayName }}" width="220" height="56">
                    </div>
                @else
                    <div class="brand-mark">{{ mb_strtoupper(mb_substr((string) $displayName, 0, 1)) }}</div>
                @endif
                <h1>{{ $displayName }}</h1>
                <span class="badge">{{ __('Email Verification') }}</span>
            </div>

            <div class="email-body">
                <p class="greeting">{{ __('Hi') }} {{ $customerName ?? __('there') }},</p>
                <div class="message-content">
                    <p>{!! nl2br(e($message_test ?? __('Thanks for registering. Please verify your email to activate your account.'))) !!}</p>
                    <p>{{ __('Click the button below to confirm this email address belongs to you.') }}</p>
                </div>

                <div class="cta-wrap">
                    <a href="{{ $verifyUrl ?? '#' }}"
                       class="cta-button"
                       target="_blank"
                       rel="noopener"
                       style="background: linear-gradient(135deg, {{ $brandStrong }} 0%, {{ $brandAccent }} 100%); box-shadow: 0 8px 20px {{ $brandGlow }};">
                        {{ $activateLabel ?? __('Activate Account') }}
                    </a>
                </div>

                <p class="hint">{{ $expiresLabel ?? __('This link expires in 48 hours.') }}</p>

                <div class="link-fallback">
                    {{ __('If the button does not work, copy and paste this link into your browser:') }}<br>
                    <a href="{{ $verifyUrl ?? '#' }}" style="color: {{ $brandStrong }};">{{ $verifyUrl ?? '' }}</a>
                </div>

                <div class="features">
                    <div class="feature">
                        <div class="feature-icon" style="background: {{ $brandSoft }}; color: {{ $brandAccent }};">1</div>
                        <div class="feature-title">{{ __('Secure login') }}</div>
                    </div>
                    <div class="feature">
                        <div class="feature-icon" style="background: {{ $brandSoft }}; color: {{ $brandAccent }};">2</div>
                        <div class="feature-title">{{ __('Order tracking') }}</div>
                    </div>
                    <div class="feature">
                        <div class="feature-icon" style="background: {{ $brandSoft }}; color: {{ $brandAccent }};">3</div>
                        <div class="feature-title">{{ __('Faster checkout') }}</div>
                    </div>
                </div>
            </div>

            <div class="email-footer">
                <p class="brand">{{ $displayName }}</p>
                <p>{{ __('If you did not create this account, you can ignore this email.') }}</p>
                @if(!empty($companyEmail) || !empty($companyPhone))
                    <p>
                        @if(!empty($companyEmail)){{ $companyEmail }}@endif
                        @if(!empty($companyEmail) && !empty($companyPhone)) · @endif
                        @if(!empty($companyPhone)){{ $companyPhone }}@endif
                    </p>
                @endif
            </div>
        </div>
    </div>
</body>
</html>
