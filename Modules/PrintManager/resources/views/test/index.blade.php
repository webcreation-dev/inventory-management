@extends('backend.backend_layout')
@section('page-title', __('Printer Test'))
@section('page-content')
@php
    $savedPrinter = $setting->default_printer;
    $methodBadgeClass = match ($printMethodValue ?? '') {
        'qz_tray' => 'bg-label-success',
        'browser' => 'bg-label-info',
        'local_agent' => 'bg-label-warning',
        default => 'bg-label-secondary',
    };
@endphp
<div class="container-xxl flex-grow-1 container-p-y">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-6 row-gap-4">
        <div class="d-flex flex-column justify-content-center">
            <h4 class="mb-0">{{ __('Printer Test') }}</h4>
            <p class="text-muted mb-0 mt-1">{{ __('Send a sample page to verify your printer setup.') }}</p>
        </div>
        @include('backend.components.breadcrumb', [
            'breadcrumbs' => [
                ['label' => '<i class="ti tabler-home"></i>', 'link' => route('dashboard')],
                ['label' => __('Print Manager'), 'link' => route('printmanager.settings.index')],
                ['label' => __('Test'), 'active' => true],
            ]
        ])
    </div>

    <div class="row">
        <div class="col-xl-7 col-lg-8">
            <div class="card">
                <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
                    <h5 class="card-title mb-0">{{ __('Run Test Print') }}</h5>
                    <a href="{{ route('printmanager.settings.index') }}" class="btn btn-sm btn-outline-primary">
                        <i class="ti tabler-settings me-1"></i>{{ __('Edit Settings') }}
                    </a>
                </div>
                <div class="card-body">
                    {{-- Saved configuration summary (not editable here) --}}
                    <div class="rounded border bg-lighter p-3 mb-4">
                        <div class="d-flex align-items-center justify-content-between mb-3">
                            <span class="fw-medium text-heading">{{ __('Saved Configuration') }}</span>
                            <span class="badge {{ $methodBadgeClass }}">{{ __($printMethodLabel) }}</span>
                        </div>
                        <div class="row g-3">
                            <div class="col-sm-6">
                                <div class="d-flex align-items-start gap-2">
                                    <span class="d-flex align-items-center justify-content-center avatar avatar-sm rounded bg-label-primary">
                                        <i class="ti tabler-cpu avatar-icon"></i>
                                    </span>
                                    <div class="lh-sm">
                                        <small class="text-muted d-block">{{ __('Print Method') }}</small>
                                        <span class="fw-medium">{{ __($printMethodLabel) }}</span>
                                    </div>
                                </div>
                            </div>
                            <div class="col-sm-6">
                                <div class="d-flex align-items-start gap-2">
                                    <span class="d-flex align-items-center justify-content-center avatar avatar-sm rounded {{ $savedPrinter ? 'bg-label-success' : 'bg-label-warning' }}">
                                        <i class="ti tabler-printer avatar-icon"></i>
                                    </span>
                                    <div class="lh-sm">
                                        <small class="text-muted d-block">{{ __('Saved Default Printer') }}</small>
                                        @if($savedPrinter)
                                            <span class="fw-medium" id="currentPrinter">{{ $savedPrinter }}</span>
                                        @else
                                            <span class="text-warning fw-medium" id="currentPrinter">{{ __('Not configured') }}</span>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <form id="printTestForm">
                        @csrf
                        <div class="mb-3">
                            <label class="form-label" for="branch_id">{{ __('Branch / Outlet') }}</label>
                            <select class="form-select select2" id="branch_id" name="branch_id">
                                <option value="">{{ __('Company Default') }}</option>
                                @foreach($outlets as $outlet)
                                    <option value="{{ $outlet->id }}" {{ (int) session('outlet.outlet_id') === (int) $outlet->id ? 'selected' : '' }}>
                                        {{ $outlet->outlet_name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label" for="default_printer">{{ __('Printer for this test') }}</label>
                            <div class="input-group">
                                <select class="form-select" id="default_printer" name="default_printer">
                                    <option value="">{{ __('Use saved default') }}</option>
                                    @if($savedPrinter)
                                        <option value="{{ $savedPrinter }}">{{ $savedPrinter }}</option>
                                    @endif
                                </select>
                                <button type="button" class="btn btn-outline-secondary" id="refreshTestPrintersBtn" title="{{ __('Refresh printers') }}">
                                    <i class="ti tabler-refresh"></i>
                                </button>
                            </div>
                            <small class="text-muted d-block mt-2" id="qzConnectionStatus">{{ __('Connecting to QZ Tray...') }}</small>
                            <small class="text-muted d-block mt-1">{{ __('Pick any installed printer for a one-time test. Your saved default stays unchanged.') }}</small>
                        </div>

                        <div class="alert alert-info mb-4" id="testStatus" role="status">{{ __('Ready to test print.') }}</div>

                        <button type="submit" class="btn btn-primary" id="runTestPrintBtn">
                            <i class="ti tabler-printer me-1"></i>{{ __('Print Test Page') }}
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('page-js')
@include('printmanager::partials.scripts')
@include('printmanager::partials.config-script')
<script>
    window.PrintManagerConfig = Object.assign(window.PrintManagerConfig || {}, {
        routes: Object.assign(window.PrintManagerConfig.routes || {}, {
            test: @json(route('printmanager.test.run')),
            log: @json(route('printmanager.print.log')),
        }),
        selectedPrinter: @json($setting->default_printer),
        printMethod: @json($setting->print_method),
    });
</script>
<script src="{{ asset('public/backend_assets/js/printmanager/qz-manager.js') }}?v={{ @filemtime(public_path('backend_assets/js/printmanager/qz-manager.js')) ?: time() }}"></script>
<script src="{{ asset('public/backend_assets/js/printmanager/printer.js') }}?v={{ @filemtime(public_path('backend_assets/js/printmanager/printer.js')) ?: time() }}"></script>
<script src="{{ asset('public/backend_assets/js/printmanager/receipt-printer.js') }}?v={{ @filemtime(public_path('backend_assets/js/printmanager/receipt-printer.js')) ?: time() }}"></script>
<script>
    $(function () {
        const ensurePrinterSelection = function () {
            const $printer = $('#default_printer');
            const selected = window.PrintManagerConfig && window.PrintManagerConfig.selectedPrinter;

            if (selected && $printer.find('option').filter(function () {
                return $(this).val() === selected;
            }).length) {
                $printer.val(selected);
                return;
            }

            if ($printer.val()) {
                return;
            }

            const firstInstalled = $printer.find('option').filter(function () {
                return $(this).val() !== '';
            }).first().val();

            if (firstInstalled) {
                $printer.val(firstInstalled);
            }
        };

        const loadPrinters = function () {
            return PrintManagerPrinter.refreshPrinters('#default_printer', '#qzConnectionStatus')
                .done(ensurePrinterSelection)
                .fail(function (error) {
                    $('#testStatus')
                        .removeClass('alert-info alert-success')
                        .addClass('alert-danger')
                        .text(error || '{{ __('Unable to load printers. Start QZ Tray and click refresh.') }}');
                });
        };

        loadPrinters();
        $('#refreshTestPrintersBtn').on('click', function () {
            loadPrinters();
        });
    });
</script>
@endpush
