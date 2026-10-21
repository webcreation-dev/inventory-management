@php
    $brandStrong = $brandStrong ?? $brandColor ?? \Modules\Ecommerce\Support\BrandColor::DEFAULT;
    $displayName = $siteName ?? $companyName ?? config('app.name');
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title>{{ $subject ?? __('Reply from our team') }}</title>
</head>
<body style="margin:0;padding:0;background-color:#eef2f6;font-family:'Segoe UI',Roboto,Helvetica,Arial,sans-serif;-webkit-font-smoothing:antialiased;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#eef2f6;padding:24px 12px;">
        <tr>
            <td align="center">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:600px;background:#ffffff;border-radius:16px;overflow:hidden;box-shadow:0 10px 40px rgba(15,23,42,0.08);border:1px solid #e2e8f0;">
                    <tr>
                        <td style="background:{{ $brandStrong }};padding:28px 32px;">
                            <p style="margin:0;font-size:11px;letter-spacing:0.12em;text-transform:uppercase;color:rgba(255,255,255,0.85);">{{ $displayName }}</p>
                            <h1 style="margin:8px 0 0;font-size:22px;font-weight:600;color:#ffffff;line-height:1.3;">{{ __('Message from our team') }}</h1>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:32px 32px 24px;color:#1e293b;font-size:15px;line-height:1.65;">
                            <p style="margin:0 0 16px;">{{ __('Hello :name,', ['name' => $customerName ?? '']) }}</p>
                            @if(!empty($originalSubject))
                                <p style="margin:0 0 12px;font-size:13px;color:#64748b;">
                                    {{ __('Regarding your message:') }} <strong style="color:#0f172a;">{{ $originalSubject }}</strong>
                                </p>
                            @endif

                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:8px 0 0;background:#f8fafc;border-radius:8px;border:1px solid #e2e8f0;">
                                <tr>
                                    <td style="padding:16px 18px;">
                                        <p style="margin:0;font-size:14px;color:#0f172a;white-space:pre-wrap;">{{ $replyMessage ?? '' }}</p>
                                    </td>
                                </tr>
                            </table>

                            <p style="margin:24px 0 0;font-size:12px;color:#94a3b8;">{{ __('If you have more questions, you can reply to this email or contact us again through the store.') }}</p>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:0 32px 28px;">
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border-top:1px solid #e2e8f0;padding-top:20px;">
                                <tr>
                                    <td style="font-size:12px;color:#64748b;line-height:1.5;">
                                        {{ __('This message was sent by :app.', ['app' => $displayName]) }}
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                </table>
                <p style="margin:20px 0 0;font-size:11px;color:#94a3b8;max-width:600px;">
                    &copy; {{ date('Y') }} {{ $displayName }}. {{ __('All rights reserved.') }}
                </p>
            </td>
        </tr>
    </table>
</body>
</html>
