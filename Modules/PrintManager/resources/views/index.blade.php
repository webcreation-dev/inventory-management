@extends('backend.backend_layout')
@section('page-title', __('Print Manager'))
@section('page-content')
<div class="container-xxl flex-grow-1 container-p-y">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-6 row-gap-4">
        <div>
            <h4 class="mb-1">{{ __('Print Manager') }}</h4>
            <p class="text-muted mb-0">{{ __('Configure QZ Tray, test printers, and manage branch print settings.') }}</p>
        </div>
    </div>

    <div class="row g-4 mb-4">
        <div class="col-md-4">
            <div class="card h-100">
                <div class="card-body">
                    <h5 class="card-title">{{ __('Current Branch Setting') }}</h5>
                    <p class="mb-1"><strong>{{ __('Method') }}:</strong> {{ $setting->print_method ?? '-' }}</p>
                    <p class="mb-1"><strong>{{ __('Default Printer') }}:</strong> {{ $setting->default_printer ?: __('Not configured') }}</p>
                    <p class="mb-0"><strong>{{ __('Paper Size') }}:</strong> {{ $setting->paper_size ?? '-' }}</p>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card h-100">
                <div class="card-body">
                    <h5 class="card-title">{{ __('Quick Actions') }}</h5>
                    <div class="d-grid gap-2">
                        <a href="{{ route('printmanager.settings.index') }}" class="btn btn-primary">
                            <i class="ti tabler-settings me-1"></i>{{ __('Printer Settings') }}
                        </a>
                        <a href="{{ route('printmanager.test.index') }}" class="btn btn-outline-primary">
                            <i class="ti tabler-printer me-1"></i>{{ __('Test Print') }}
                        </a>
                        <a href="{{ route('printmanager.printers.index') }}" class="btn btn-outline-info">
                            <i class="ti tabler-list me-1"></i>{{ __('Printer List') }}
                        </a>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card h-100 border-warning">
                <div class="card-body">
                    <h5 class="card-title">{{ __('How To Test') }}</h5>
                    <ol class="mb-0 ps-3">
                        <li>{{ __('Install and open QZ Tray on this PC.') }}</li>
                        <li>{{ __('Go to Printer Settings and refresh printers.') }}</li>
                        <li>{{ __('Save your default printer.') }}</li>
                        <li>{{ __('Open Test Print and click Print Test Page.') }}</li>
                        <li>{{ __('For POS sale print, set counter printer to QZ Tray Print.') }}</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h5 class="mb-0">{{ __('Recent Print Logs') }} ({{ $settingsCount }} {{ __('saved profile(s)') }})</h5>
        </div>
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>{{ __('Date') }}</th>
                        <th>{{ __('Module') }}</th>
                        <th>{{ __('Printer') }}</th>
                        <th>{{ __('Method') }}</th>
                        <th>{{ __('Status') }}</th>
                        <th>{{ __('Error') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($recentLogs as $log)
                        <tr>
                            <td>{{ $log->created_at?->format('Y-m-d H:i') }}</td>
                            <td>{{ $log->module }}</td>
                            <td>{{ $log->printer ?: '-' }}</td>
                            <td>{{ $log->print_method }}</td>
                            <td>
                                <span class="badge bg-label-{{ $log->status === 'success' ? 'success' : ($log->status === 'failed' ? 'danger' : 'warning') }}">
                                    {{ ucfirst($log->status) }}
                                </span>
                            </td>
                            <td>{{ $log->error_message ?: '-' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center py-4 text-muted">{{ __('No print logs yet. Run a test print first.') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
