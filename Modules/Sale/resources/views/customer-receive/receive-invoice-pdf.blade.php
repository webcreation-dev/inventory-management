@php
    $businessName = session('company.business_name', '');
    $outlet = $customerReceive->outlet;
    $outletLine = $outlet?->outlet_name ?? '';
    $accountLabel = $customerReceive->payment ? ($customerReceive->payment->name ?? '') : '';
    $authorizedName = $customerReceive->user?->name ?? auth()->user()?->name ?? '';
    $invoiceLogoPath = $invoiceLogoPath ?? null;
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="UTF-8">
    <title>{{ __('Money Receipt') }} - {{ $customerReceive->reference_no ?? '' }}</title>
    <style>
        body {
            font-family: 'DejaVu Sans', sans-serif;
            margin: 0;
            padding: 0;
            color: #1a3d32;
            font-size: 11pt;
            line-height: 1.35;
        }
        .mr-card {
            border: 1px solid #e0e0e0;
            border-radius: 4px;
            overflow: visible;
            width: 100%;
        }
        .mr-header {
            background-color: #ececec;
            padding: 12px 16px;
        }
        .mr-header-table { width: 100%; border-collapse: collapse; }
        .mr-header-table td { vertical-align: top; }
        .mr-logo-cell { width: 48px; padding-right: 8px; }
        .mr-logo-cell img { max-height: 30px; max-width: 40px; }
        .mr-brand-name {
            margin: 0;
            font-size: 17pt;
            font-weight: 700;
            color: #1a3d32;
            line-height: 1.15;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .mr-brand-meta {
            margin-top: 6px;
            font-size: 10pt;
            color: #1a3d32;
            line-height: 1.45;
        }
        .mr-brand-meta-item {
            display: inline-block;
            vertical-align: top;
            white-space: nowrap;
            max-width: 100%;
        }
        .mr-brand-meta-item--wrap {
            white-space: normal;
        }
        .mr-brand-meta-item + .mr-brand-meta-item::before {
            content: '•';
            display: inline-block;
            margin-right: 8px;
            margin-left: 2px;
            color: #9ca3af;
            font-weight: normal;
        }
        .mr-body { padding: 12px 16px 0; }
        .mr-title-row-table { width: 100%; border-collapse: collapse; margin-bottom: 12px; }
        .mr-title-row-table td { vertical-align: middle; }
        .mr-title-text {
            font-size: 12pt;
            font-weight: 700;
            letter-spacing: 0.06em;
            text-transform: uppercase;
            color: #1a3d32;
        }
        .mr-title-bar {
            display: inline-block;
            width: 4px;
            height: 24px;
            background: #ececec;
            vertical-align: middle;
            margin-right: 8px;
        }
        .mr-account-badge {
            display: inline-block;
            padding: 6px 12px;
            border: 1px solid #e0e0e0;
            border-radius: 16px;
            font-size: 9.5pt;
            font-weight: 600;
            text-align: right;
            white-space: nowrap;
        }
        .mr-account-wrap { text-align: right; }
        .mr-grid-table { width: 100%; border-collapse: collapse; border: none; }
        .mr-grid-table td,
        .mr-grid-table tr {
            border: none !important;
            vertical-align: top;
        }
        .mr-grid-table td { width: 50%; padding: 0 8px 0 0; }
        .mr-grid-table td:last-child { padding: 0 0 0 8px; }
        .mr-panel {
            border: none !important;
            padding: 10px 12px;
            background: #fff;
        }
        .mr-panel-head {
            font-size: 9pt;
            font-weight: 800;
            letter-spacing: 0.05em;
            text-transform: uppercase;
            color: #1a3d32;
            margin-bottom: 8px;
        }
        .mr-panel-dash {
            border: none;
            border-top: 1px dashed #e0e0e0;
            margin: 0 0 8px;
        }
        .mr-field { margin-bottom: 6px; font-size: 10pt; line-height: 1.4; }
        .mr-field:last-child { margin-bottom: 0; }
        .mr-field-label { font-weight: 700; display: block; margin-bottom: 1px; }
        .mr-tx-table { width: 100%; border-collapse: collapse; font-size: 10pt; }
        .mr-tx-table td { padding: 6px 0; vertical-align: top; border-bottom: 1px solid #f0f0f0; }
        .mr-tx-table tr:last-child td { border-bottom: none; }
        .mr-tx-table td:last-child {
            text-align: right;
            font-weight: 500;
            color: #334843;
        }
        .mr-ref-val { font-weight: 700; color: #1a3d32 !important; }
        .mr-total-wrap { margin: 12px 0 0; padding: 0 16px; }
        .mr-total {
            background-color: #ececec;
            border: 1px solid #e0e0e0;
            border-radius: 4px;
            padding: 12px 14px;
        }
        .mr-total-table { width: 100%; border-collapse: collapse; }
        .mr-total-label {
            font-weight: 700;
            font-size: 11pt;
            letter-spacing: 0.04em;
            text-transform: uppercase;
            color: #1a3d32;
        }
        .mr-total-amt {
            font-size: 20pt;
            font-weight: 700;
            color: #1a3d32;
            text-align: right;
        }
        .mr-current-due-row .mr-total-label {
            font-size: 10pt;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.03em;
            padding-top: 8px;
            border-top: 1px dashed #e0e0e0;
        }
        .mr-current-due-row .mr-current-due-val {
            font-size: 11pt;
            font-weight: 600;
            color: #334843;
            text-align: right;
            padding-top: 8px;
            border-top: 1px dashed #e0e0e0;
            font-variant-numeric: tabular-nums;
        }
        .mr-note-block { padding: 10px 16px 8px; font-size: 10pt; color: #334843; }
        .mr-note-block .mr-field-label { margin-bottom: 4px; }
        .mr-auth {
            padding: 12px 16px;
            margin-top: 10px;
            border-top: 1px dashed #e0e0e0;
        }
        .mr-auth-table { width: 100%; border-collapse: collapse; }
        .mr-auth-by { font-weight: 700; font-size: 10pt; color: #1a3d32; vertical-align: bottom; }
        .mr-sig { text-align: right; vertical-align: bottom; }
        .mr-sig-label {
            font-size: 8.5pt;
            font-style: italic;
            color: #6b7280;
            margin-bottom: 16px;
        }
        .mr-sig-line { border-bottom: 1px solid #1a3d32; height: 1px; }
        .mr-footer {
            background-color: #f3f4f6;
            padding: 12px 16px;
            text-align: center;
            font-size: 9.5pt;
            color: #4b5563;
            line-height: 1.45;
        }
        .mr-footer strong { color: #1a3d32; }
        .border-none { border: none !important; }
    </style>
</head>
<body>
<div class="mr-card">
    <div class="mr-header">
        <table class="mr-header-table">
            <tr>
                <td class="mr-logo-cell">
                    @if($invoiceLogoPath)
                        <img src="{{ $invoiceLogoPath }}" alt="" style="height: 45px; width: 200px;">
                    @endif
                </td>
                <td>
                    <p class="mr-brand-name">{{ $businessName }}</p>
                    @if($outlet && (($outletLine !== '') || ($outlet->phone ?? null) || ($outlet->email ?? null) || ($outlet->address ?? null)))
                        <div class="mr-brand-meta">
                            @if($outletLine !== '')
                                <span class="mr-brand-meta-item">{{ $outletLine }}</span> -
                            @endif
                            @if($outlet && ($outlet->phone ?? null))
                                <span class="mr-brand-meta-item">{{ __('Phone') }}: {{ $outlet->phone }}</span> - 
                            @endif
                            @if($outlet && ($outlet->email ?? null))
                                <span class="mr-brand-meta-item">{{ $outlet->email }}</span> -
                            @endif
                            @if($outlet && ($outlet->address ?? null))
                                <span class="mr-brand-meta-item mr-brand-meta-item--wrap">{{ $outlet->address }}</span>
                            @endif
                        </div>
                    @endif
                </td>
            </tr>
        </table>
    </div>

    <div class="mr-body">
        <table class="mr-title-row-table">
            <tr>
                <td>
                    <span class="mr-title-bar"></span>
                    <span class="mr-title-text">{{ __('Money Receipt') }}</span>
                </td>
                <td class="mr-account-wrap">
                    @if($accountLabel !== '')
                        <span>{{ __('Account') }}: {{ $accountLabel }}</span>
                    @endif
                </td>
            </tr>
        </table>

        <table class="mr-grid-table">
            <tr>
                <td class="border-none" style="width: 45%; border: none !important;">
                    <div class="mr-panel">
                        <div><strong>{{ __('Customer Receipt') }}</strong></div>
                        <br>
                        <table class="mr-tx-table">
                            @if($customerReceive->customer)
                                @if($customerReceive->customer->name)
                                    <tr>
                                        <td>{{ __('Name') }}</td>
                                        <td>{{ $customerReceive->customer->name }}</td>
                                    </tr>
                                @endif
                                @if($customerReceive->customer->phone ?? null)
                                    <tr>
                                        <td>{{ __('Phone') }}</td>
                                        <td>{{ $customerReceive->customer->phone }}</td>
                                    </tr>
                                @endif
                                @if($customerReceive->customer->email ?? null)
                                    <tr>
                                        <td>{{ __('Email') }}</td>
                                        <td>{{ $customerReceive->customer->email }}</td>
                                    </tr>
                                @endif
                                @if($customerReceive->customer->address ?? null)
                                    <tr>
                                        <td>{{ __('Address') }}</td>
                                        <td>{{ $customerReceive->customer->address }}</td>
                                    </tr>
                                @endif
                            @endif
                        </table>
                    </div>
                </td>
                <td style="width: 10%;"></td>
                <td class="border-none" style="width: 45%; border: none !important;">
                    <div class="mr-panel">
                        <div><strong>{{ __('Transaction Details') }}</strong></div>
                        <br>
                        <table class="mr-tx-table">
                            <tr>
                                <td>{{ __('Reference No') }}</td>
                                <td class="mr-ref-val">#{{ $customerReceive->reference_no ?? '' }}</td>
                            </tr>
                            <tr>
                                <td>{{ __('Date') }}</td>
                                <td>{{ formatDate($customerReceive->date) }}</td>
                            </tr>
                            @if($customerReceive->payment)
                                <tr>
                                    <td>{{ __('Payment Mode') }}</td>
                                    <td>{{ $customerReceive->payment->name }}</td>
                                </tr>
                            @endif
                            @if($customerReceive->user)
                                <tr>
                                    <td>{{ __('Received By') }}</td>
                                    <td>{{ $customerReceive->user->name ?? '' }}</td>
                                </tr>
                            @endif
                        </table>
                    </div>
                </td>
            </tr>
        </table>
    </div>

    <div class="mr-total-wrap">
        <div class="mr-total">
            <table class="mr-total-table">
                <tr>
                    <td class="mr-total-label">{{ __('Total Amount Received') }}</td>
                    <td class="mr-total-amt">{{ formatAmount($customerReceive->amount) }}</td>
                </tr>
                @isset($customerCurrentDue)
                    @php
                        $dueTypePdf = $customerCurrentDue >= 0 ? __('Debit') : __('Credit');
                    @endphp
                    <tr class="mr-current-due-row">
                        <td class="mr-total-label">{{ __('Current Amount') }}</td>
                        <td class="mr-current-due-val">{{ formatAmount(abs($customerCurrentDue)) }} ({{ $dueTypePdf }})</td>
                    </tr>
                @endisset
            </table>
        </div>
    </div>

    @if($customerReceive->note)
        <div class="mr-note-block">
            <span class="mr-field-label">{{ __('Note') }}</span>
            <p style="margin:0;">{{ $customerReceive->note }}</p>
        </div>
    @endif

    <div class="mr-auth">
        <table class="mr-auth-table">
            <tr>
                <td class="mr-auth-by">
                    {{ __('Authorized by') }}: {{ $authorizedName !== '' ? $authorizedName : '—' }}
                </td>
                <td class="mr-sig">
                    <div class="mr-sig-label">{{ $businessName }} ({{ __('Authorized Signature') }})</div>
                    <div class="mr-sig-line"></div>
                </td>
            </tr>
        </table>
    </div>

    <div class="mr-footer">
        <p style="margin:0;">{{ __('Thank you for your payment.') }}</p>
    </div>
</div>
</body>
</html>
