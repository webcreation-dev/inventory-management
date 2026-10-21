<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ __('Money Receipt') }} — {{ $customerReceive->reference_no }}</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,400;0,9..40,500;0,9..40,600;0,9..40,700&display=swap" rel="stylesheet">

    @php
        $invoice_logo = session('company.invoice_logo');
        $businessName = session('company.business_name', '');
        $outlet = $customerReceive->outlet;
        $outletLine = $outlet?->outlet_name ?? '';
        $accountLabel = $customerReceive->payment ? ($customerReceive->payment->name ?? '') : '';
        $authorizedName = $customerReceive->user?->name ?? auth()->user()?->name ?? '';
    @endphp

    <style>
        :root {
            --mr-green: #1a3d32;
            --mr-gold:rgb(236, 236, 236);
            --mr-cream: #fdf5e6;
            --mr-border: #e0e0e0;
            --mr-muted: #6b7280;
        }
        body {
            font-family: 'DM Sans', system-ui, sans-serif;
            margin: 0;
            color: var(--mr-green);
            font-size: 14px;
        }
        .receive-shell {
            max-width: 800px;
            margin: 24px auto;
            padding: 16px;
        }
        .mr-card {
            background: #fff;
            border-radius: 20px;
            box-shadow: 0 12px 40px rgba(26, 61, 50, 0.08);
            overflow: hidden;
        }
        .mr-header {
            background: var(--mr-gold);
            padding: 22px 26px;
            display: flex;
            flex-wrap: wrap;
            align-items: flex-start;
            justify-content: space-between;
            gap: 16px;
        }
        .mr-header-main {
            display: flex;
            align-items: center;
            gap: 16px;
            min-width: 0;
        }
        /* .mr-logo-ring {
            flex-shrink: 0;
            width: 64px;
            height: 64px;
            border-radius: 50%;
            background: rgba(253, 245, 230, 0.95);
            border: 3px solid rgba(26, 61, 50, 0.15);
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
        } */
        .mr-logo-ring img {
            max-height: 45px;
        }
        .mr-brand-name {
            margin: 0;
            font-size: 1.65rem;
            font-weight: 700;
            color: var(--mr-green);
            letter-spacing: -0.02em;
            line-height: 1.15;
        }
        .mr-brand-sub {
            margin: 6px 0 0;
            font-size: 0.9rem;
            color: var(--mr-green);
            opacity: 0.92;
        }
        .mr-header-contact {
            text-align: right;
            font-size: 0.8125rem;
            color: var(--mr-green);
            line-height: 1.65;
        }
        .mr-header-contact-row {
            display: flex;
            align-items: flex-start;
            justify-content: flex-end;
            gap: 8px;
        }
        .mr-header-contact-row svg {
            flex-shrink: 0;
            margin-top: 3px;
            opacity: 0.85;
        }
        .mr-body {
            padding: 22px 26px 0;
        }
        .mr-title-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 12px;
            margin-bottom: 20px;
        }
        .mr-title {
            display: flex;
            align-items: center;
            gap: 12px;
            margin: 0;
            font-size: 1.125rem;
            font-weight: 700;
            letter-spacing: 0.06em;
            text-transform: uppercase;
            color: var(--mr-green);
        }
        .mr-title-bar {
            width: 5px;
            align-self: stretch;
            min-height: 28px;
            background: var(--mr-gold);
            border-radius: 2px;
        }
        .mr-account-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 14px;
            border-radius: 999px;
            border: 1px solid var(--mr-border);
            font-size: 0.8125rem;
            font-weight: 600;
        }
        .mr-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
        }
        @media (max-width: 640px) {
            .mr-grid { grid-template-columns: 1fr; }
            .mr-header-contact { text-align: left; }
            .mr-header-contact-row { justify-content: flex-start; }
        }
        .mr-panel {
            border: 1px solid var(--mr-border);
            border-radius: 12px;
            padding: 16px 18px;
            background: #fff;
        }
        .mr-panel-head {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 0.75rem;
            font-weight: 700;
            letter-spacing: 0.05em;
            text-transform: uppercase;
            color: var(--mr-green);
            margin-bottom: 10px;
        }
        .mr-panel-dash {
            border: none;
            border-top: 1px dashed var(--mr-border);
            margin: 0 0 14px;
        }
        .mr-field {
            margin-bottom: 10px;
            font-size: 0.875rem;
            line-height: 1.45;
        }
        .mr-field:last-child { margin-bottom: 0; }
        .mr-field-label {
            font-weight: 700;
            display: block;
            margin-bottom: 2px;
        }
        .mr-field-val {
            font-weight: 400;
            color: #334843;
        }
        .mr-tx-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.875rem;
        }
        .mr-tx-table tr:not(:last-child) td {
            border-bottom: 1px solid #f0f0f0;
        }
        .mr-tx-table td {
            padding: 10px 0;
            vertical-align: top;
        }
        .mr-tx-table td:last-child {
            text-align: right;
            font-weight: 500;
            color: #334843;
        }
        .mr-tx-table td.mr-ref-val {
            font-weight: 700;
            color: var(--mr-green);
        }
        .mr-total-wrap {
            margin: 22px 0 0;
            padding: 0 26px;
        }
        .mr-total {
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 12px;
            background: var(--mr-gold);
            border-radius: 12px;
            padding: 16px 20px;
            border: 1px solid var(--mr-border);
        }
        .mr-total-label {
            font-weight: 700;
            font-size: 0.9375rem;
            letter-spacing: 0.04em;
            text-transform: uppercase;
            color: var(--mr-green);
        }
        .mr-total-amt {
            font-size: 1.75rem;
            font-weight: 700;
            color: var(--mr-green);
        }
        .mr-current-due {
            margin-top: 12px;
            padding: 12px 20px;
            border-radius: 12px;
            border: 1px dashed var(--mr-border);
            background: #fafafa;
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 8px;
            font-size: 0.875rem;
        }
        .mr-current-due-label {
            font-weight: 700;
            color: var(--mr-green);
            letter-spacing: 0.03em;
            text-transform: uppercase;
            font-size: 0.8125rem;
        }
        .mr-current-due-val {
            font-weight: 600;
            color: #334843;
            font-variant-numeric: tabular-nums;
        }
        .mr-auth {
            padding: 22px 26px;
            margin-top: 30px;
            border-top: 1px dashed var(--mr-border);
            display: flex;
            flex-wrap: wrap;
            align-items: flex-end;
            justify-content: space-between;
            gap: 24px;
        }
        .mr-auth-by {
            font-weight: 700;
            font-size: 0.875rem;
            display: flex;
            align-items: center;
            gap: 8px;
            color: var(--mr-green);
        }
        .mr-sig {
            text-align: right;
            min-width: 200px;
        }
        .mr-sig-label {
            font-size: 0.75rem;
            font-style: italic;
            color: var(--mr-muted);
            margin-bottom: 28px;
        }
        .mr-sig-line {
            border-bottom: 1px solid var(--mr-green);
            opacity: 0.45;
        }
        .mr-note-sys {
            text-align: center;
            font-size: 0.75rem;
            color: var(--mr-muted);
            padding: 8px 26px 18px;
        }
        .mr-footer {
            background: #f3f4f6;
            padding: 16px 26px 20px;
            text-align: center;
            font-size: 0.8125rem;
            color: #4b5563;
            line-height: 1.55;
        }
        .mr-footer strong {
            color: var(--mr-green);
        }
        .mr-note-block {
            padding: 0 26px 18px;
            font-size: 0.875rem;
            color: #334843;
        }
        .mr-note-block .mr-field-label { margin-bottom: 6px; }
        .no-print { text-align: center; padding: 16px 26px 26px; }
        @media print {
            @page {
                size: A4 portrait;
                margin: 10mm 12mm;
            }
            html, body {
                background: #fff !important;
                width: 100%;
                margin: 0;
                -webkit-text-size-adjust: 100%;
                text-size-adjust: 100%;
            }
            /* Only set body text size — do not shrink html root or all `rem` headings (e.g. .mr-brand-name) vs screen */
            body {
                font-size: 14px;
            }
            .receive-shell {
                margin: 0 auto;
                padding: 0;
                max-width: 100%;
                width: 100%;
            }
            .mr-card {
                box-shadow: none;
                border-radius: 0;
            }
            .no-print { display: none !important; }
            /* Compact vertical rhythm for one page; keep same font scale as screen (rems unchanged) */
            .mr-header { padding: 16px 20px; gap: 12px; }
            .mr-logo-ring img { max-height: 42px; }
            .mr-body { padding: 16px 20px 0; }
            .mr-title-row { margin-bottom: 14px; }
            .mr-grid { gap: 12px; }
            .mr-panel { padding: 12px 14px; }
            .mr-panel-head { margin-bottom: 8px; }
            .mr-panel-dash { margin: 0 0 10px; }
            .mr-field { margin-bottom: 8px; }
            .mr-tx-table td { padding: 8px 0; }
            .mr-total-wrap { margin: 16px 0 0; padding: 0 20px; }
            .mr-total { padding: 14px 18px; }
            .mr-current-due { margin-top: 10px; padding: 10px 16px; }
            .mr-note-block { padding: 0 20px 10px; }
            .mr-auth { padding: 16px 20px; margin-top: 18px; gap: 18px; }
            .mr-sig-label { margin-bottom: 20px; }
            .mr-footer { padding: 12px 20px 14px; }
            * {
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }
        }
        .mr-brand-sub-wrap .mr-brand-sub {
            display: flex;
            align-items: center;
            flex-wrap: wrap;
            gap: 5px;
        }
        .mr-brand-sub-wrap {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
        }
    </style>
</head>
<body>
<div class="receive-shell">
    <div class="mr-card">
        <header class="mr-header">
            <div class="mr-header-main">
                <div class="mr-logo-ring">
                    @if($invoice_logo)
                        <img src="{{ asset('public/uploads/site_settings/' . $invoice_logo) }}" alt="">
                    @else
                        <svg width="28" height="28" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                            <path d="M12 3C8.5 7 6 10 6 13.5a6 6 0 1 0 12 0c0-3.5-2.5-6.5-6-10.5Z" stroke="#1a3d32" stroke-width="1.5" fill="#d4a017" fill-opacity="0.35"/>
                        </svg>
                    @endif
                </div>
                <div>
                    <h1 class="mr-brand-name">{{ $businessName }}</h1>
                    <div class="mr-brand-sub-wrap">
                        <p class="mr-brand-sub">
                            <span><svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-building-arch">
                                <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                                <path d="M3 21l18 0" />
                                <path d="M4 21v-15a2 2 0 0 1 2 -2h12a2 2 0 0 1 2 2v15" />
                                <path d="M9 21v-8a3 3 0 0 1 6 0v8" />
                            </svg></span> 
                            <span>{{ $outletLine }}</span>
                        </p>
                        @if($outlet && ($outlet->phone ?? null))
                        <p class="mr-brand-sub">
                            <span><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/></svg></span> <span>{{ $outlet->phone }}</span>
                        </p>
                        @endif
                        @if($outlet && ($outlet->email ?? null))
                        <p class="mr-brand-sub">
                            <span><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg></span> <span>{{ $outlet->email }}</span>
                        </p>
                        @endif
                        @if($outlet && ($outlet->address ?? null))
                        <p class="mr-brand-sub">
                            <span><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg></span> <span>{{ $outlet->address }}</span>
                        </p>
                        @endif
                    </div>
                </div>
            </div>
        </header>
        <div class="mr-header-accent" aria-hidden="true"></div>

        <div class="mr-body">
            <div class="mr-title-row">
                <h2 class="mr-title">
                    <span class="mr-title-bar" aria-hidden="true"></span>
                    {{ __('Money Receipt') }}
                </h2>
                @if($accountLabel !== '')
                    <span class="mr-account-badge">
                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-receipt-dollar">
                            <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                            <path d="M5 21v-16a2 2 0 0 1 2 -2h10a2 2 0 0 1 2 2v16l-3 -2l-2 2l-2 -2l-2 2l-2 -2l-3 2" />
                            <path d="M14.8 8a2 2 0 0 0 -1.8 -1h-2a2 2 0 1 0 0 4h2a2 2 0 1 1 0 4h-2a2 2 0 0 1 -1.8 -1" />
                            <path d="M12 6v10" />
                        </svg>
                        {{ __('Account') }}: {{ $accountLabel }}
                    </span>
                @endif
            </div>

            <div class="mr-grid">
                <section class="mr-panel" aria-labelledby="mr-customer-head">
                    <div class="mr-panel-head" id="mr-customer-head">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                        {{ __('Customer Receipt') }}
                    </div>
                    <hr class="mr-panel-dash">
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
                </section>

                <section class="mr-panel" aria-labelledby="mr-tx-head">
                    <div class="mr-panel-head" id="mr-tx-head">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/></svg>
                        {{ __('Transaction Details') }}
                    </div>
                    <hr class="mr-panel-dash">
                    <table class="mr-tx-table">
                        <tr>
                            <td>{{ __('Reference No') }}</td>
                            <td class="mr-ref-val">#{{ $customerReceive->reference_no }}</td>
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
                </section>
            </div>
        </div>

        <div class="mr-total-wrap">
            <div class="mr-total">
                <span class="mr-total-label">{{ __('Total Amount Received') }}</span>
                <span class="mr-total-amt">{{ formatAmount($customerReceive->amount) }}</span>
            </div>
            @isset($customerCurrentDue)
                @php
                    $dueType = $customerCurrentDue >= 0 ? __('Debit') : __('Credit');
                @endphp
                <div class="mr-current-due" role="status">
                    <span class="mr-current-due-label">{{ __('Current Amount') }}</span>
                    <span class="mr-current-due-val">{{ formatAmount(abs($customerCurrentDue)) }} ({{ $dueType }})</span>
                </div>
            @endisset
        </div>

        @if($customerReceive->note)
            <div class="mr-note-block">
                <span class="mr-field-label">{{ __('Note') }}</span>
                <p style="margin:0;">{{ $customerReceive->note }}</p>
            </div>
        @endif

        <div class="mr-auth">
            <div class="mr-auth-by">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#1a3d32" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
                {{ __('Authorized by') }}: {{ $authorizedName !== '' ? $authorizedName : '—' }}
            </div>
            <div class="mr-sig">
                <div class="mr-sig-label">{{ $businessName }} ({{ __('Authorized Signature') }})</div>
                <div class="mr-sig-line"></div>
            </div>
        </div>

        <footer class="mr-footer">
            <p style="margin:0 0 8px;">
                {{ __('Thank you for your payment.') }}
            </p>
        </footer>
    </div>

    <div class="no-print">
        <button onclick="window.print();" type="button" class="px-6 py-3 rounded-xl font-medium text-white focus:ring-2 focus:ring-offset-2 focus:outline-none"
            style="background:#7367f0;">
            {{ __('Print') }}
        </button>
    </div>
</div>
</body>
</html>
