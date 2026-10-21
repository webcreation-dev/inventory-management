@extends('backend.backend_layout')
@section('page-title', __('Printer List'))
@section('page-content')
<div class="container-xxl flex-grow-1 container-p-y">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-6 row-gap-4">
        <div class="d-flex flex-column justify-content-center">
            <h4 class="mb-0">{{ __('Saved Printer Settings') }}</h4>
        </div>
        @include('backend.components.breadcrumb', [
            'breadcrumbs' => [
                ['label' => '<i class="ti tabler-home"></i>', 'link' => route('dashboard')],
                ['label' => __('Print Manager'), 'link' => route('printmanager.settings.index')],
                ['label' => __('Printer List'), 'active' => true],
            ]
        ])
    </div>

    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="card-title mb-0">{{ __('Company / Branch Printer Profiles') }}</h5>
            <a href="{{ route('printmanager.settings.index') }}" class="btn btn-sm btn-primary">{{ __('Add / Edit Settings') }}</a>
        </div>
        <div class="card-datatable table-responsive pt-0">
            <table class="table">
                <thead>
                    <tr>
                        <th>{{ __('Branch ID') }}</th>
                        <th>{{ __('Default Printer') }}</th>
                        <th>{{ __('Method') }}</th>
                        <th>{{ __('Paper Size') }}</th>
                        <th>{{ __('Copies') }}</th>
                        <th>{{ __('Cash Drawer') }}</th>
                        <th>{{ __('Status') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($settings as $setting)
                        <tr>
                            <td>{{ $setting->branch_id ?: __('Company Default') }}</td>
                            <td>{{ $setting->default_printer ?: '-' }}</td>
                            <td>{{ $setting->print_method }}</td>
                            <td>{{ $setting->paper_size }}</td>
                            <td>{{ $setting->copies }}</td>
                            <td>{{ $setting->cash_drawer }}</td>
                            <td>{{ ucfirst($setting->status) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center py-4">{{ __('No printer settings found.') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="card mt-4">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="card-title mb-0">{{ __('Installed Printers (QZ Tray)') }}</h5>
            <button type="button" class="btn btn-sm btn-outline-primary" id="refreshInstalledPrintersBtn">
                <i class="ti tabler-refresh"></i> {{ __('Refresh') }}
            </button>
        </div>
        <div class="card-body">
            <div id="installedPrintersList" class="text-muted">{{ __('Connect QZ Tray to load installed printers.') }}</div>
        </div>
    </div>
</div>
@endsection

@push('page-js')
@include('printmanager::partials.scripts')
<script>
    window.PrintManagerConfig = {
        routes: {
            certificate: @json(route('printmanager.qz.certificate')),
            sign: @json(route('printmanager.qz.sign')),
        },
        csrfToken: @json(csrf_token()),
    };
</script>
<script src="{{ asset('public/backend_assets/js/printmanager/qz-manager.js') }}?v={{ @filemtime(public_path('backend_assets/js/printmanager/qz-manager.js')) ?: time() }}"></script>
<script src="{{ asset('public/backend_assets/js/printmanager/printer.js') }}?v={{ @filemtime(public_path('backend_assets/js/printmanager/printer.js')) ?: time() }}"></script>
<script>
    $(function () {
        $('#refreshInstalledPrintersBtn').on('click', function () {
            PrintManagerPrinter.loadInstalledPrinters('#installedPrintersList');
        });
        PrintManagerPrinter.loadInstalledPrinters('#installedPrintersList');
    });
</script>
@endpush
