@extends('backend.backend_layout')
@section('page-title', __('Printer Settings'))
@push('page-css')
<link rel="stylesheet" href="{{ asset('public/backend_assets/vendor/libs/select2/select2.css') }}" />
@endpush
@section('page-content')
<div class="container-xxl flex-grow-1 container-p-y">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-6 row-gap-4">
        <div class="d-flex flex-column justify-content-center">
            <h4 class="mb-0">{{ __('Printer Settings') }}</h4>
        </div>
        @include('backend.components.breadcrumb', [
            'breadcrumbs' => [
                ['label' => '<i class="ti tabler-home"></i>', 'link' => route('printmanager.index')],
                ['label' => __('Print Manager'), 'link' => route('printmanager.index')],
                ['label' => __('Settings'), 'active' => true],
            ]
        ])
    </div>

    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">{{ __('Configure Printer') }}</h5>
                </div>
                <div class="card-body">
                    <form id="printSettingsForm">
                        @csrf
                        <div class="row">
                            <div class="col-md-6 col-lg-4 mb-3">
                                <label class="form-label" for="branch_id">{{ __('Branch / Outlet') }}</label>
                                <select class="form-select select2" id="branch_id" name="branch_id">
                                    <option value="">{{ __('Company Default') }}</option>
                                    @foreach($outlets as $outlet)
                                        <option value="{{ $outlet->id }}" {{ (int) $selectedBranchId === (int) $outlet->id ? 'selected' : '' }}>
                                            {{ $outlet->outlet_name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-6 col-lg-4 mb-3">
                                <label class="form-label" for="print_method">{{ __('Print Method') }}</label>
                                <select class="form-select select2" id="print_method" name="print_method">
                                    @foreach($printMethods as $value => $label)
                                        <option value="{{ $value }}" {{ $setting->print_method === $value ? 'selected' : '' }}>{{ __($label) }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-6 col-lg-4 mb-3">
                                <label class="form-label" for="default_printer">{{ __('Default Printer') }}</label>
                                <div class="input-group">
                                    <select class="form-select" id="default_printer" name="default_printer">
                                        <option value="">{{ __('Select Printer') }}</option>
                                        @if($setting->default_printer)
                                            <option value="{{ $setting->default_printer }}" selected>{{ $setting->default_printer }}</option>
                                        @endif
                                    </select>
                                    <button type="button" class="btn btn-outline-primary" id="refreshPrintersBtn">
                                        <i class="ti tabler-refresh"></i>
                                    </button>
                                </div>
                                <small class="text-muted" id="qzConnectionStatus">{{ __('Checking QZ Tray security...') }}</small>
                                @if(config('printmanager.env') === 'development')
                                    <small class="d-block text-warning mt-1">{{ __('Development mode: QZ trust prompts enabled until keys are configured.') }}</small>
                                @else
                                    <small class="d-block text-info mt-1">{{ __('Production mode: certificate + SHA512 signing required.') }}</small>
                                @endif
                            </div>
                            <div class="col-md-6 col-lg-4 mb-3">
                                <label class="form-label" for="paper_size">{{ __('Paper Size') }}</label>
                                <select class="form-select select2" id="paper_size" name="paper_size">
                                    @foreach($paperSizes as $value => $label)
                                        <option value="{{ $value }}" {{ $setting->paper_size === $value ? 'selected' : '' }}>{{ __($label) }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-3 col-lg-2 mb-3">
                                <label class="form-label" for="copies">{{ __('Copies') }}</label>
                                <input type="number" min="1" max="10" class="form-control" id="copies" name="copies" value="{{ $setting->copies ?? 1 }}">
                            </div>
                            <div class="col-md-3 col-lg-2 mb-3">
                                <label class="form-label" for="cash_drawer">{{ __('Cash Drawer') }}</label>
                                <select class="form-select select2" id="cash_drawer" name="cash_drawer">
                                    <option value="OFF" {{ $setting->cash_drawer === 'OFF' ? 'selected' : '' }}>{{ __('OFF') }}</option>
                                    <option value="ON" {{ $setting->cash_drawer === 'ON' ? 'selected' : '' }}>{{ __('ON') }}</option>
                                </select>
                            </div>
                            <div class="col-md-3 col-lg-2 mb-3">
                                <label class="form-label" for="status">{{ __('Status') }}</label>
                                <select class="form-select select2" id="status" name="status">
                                    <option value="active" {{ $setting->status === 'active' ? 'selected' : '' }}>{{ __('Active') }}</option>
                                    <option value="inactive" {{ $setting->status === 'inactive' ? 'selected' : '' }}>{{ __('Inactive') }}</option>
                                </select>
                            </div>
                            <div class="col-md-6 col-lg-4 mb-3">
                                <label class="form-label" for="character_encoding">{{ __('Character Encoding') }}</label>
                                <input type="text" class="form-control" id="character_encoding" name="character_encoding" value="{{ $setting->character_encoding ?? 'UTF-8' }}">
                            </div>
                            @php $margins = $setting->margins ?? ['top' => 0, 'right' => 0, 'bottom' => 0, 'left' => 0]; @endphp
                            <div class="col-md-3 col-lg-2 mb-3">
                                <label class="form-label" for="margin_top">{{ __('Margin Top') }}</label>
                                <input type="number" min="0" class="form-control margin-field" id="margin_top" name="margins[top]" value="{{ $margins['top'] ?? 0 }}">
                            </div>
                            <div class="col-md-3 col-lg-2 mb-3">
                                <label class="form-label" for="margin_right">{{ __('Margin Right') }}</label>
                                <input type="number" min="0" class="form-control margin-field" id="margin_right" name="margins[right]" value="{{ $margins['right'] ?? 0 }}">
                            </div>
                            <div class="col-md-3 col-lg-2 mb-3">
                                <label class="form-label" for="margin_bottom">{{ __('Margin Bottom') }}</label>
                                <input type="number" min="0" class="form-control margin-field" id="margin_bottom" name="margins[bottom]" value="{{ $margins['bottom'] ?? 0 }}">
                            </div>
                            <div class="col-md-3 col-lg-2 mb-3">
                                <label class="form-label" for="margin_left">{{ __('Margin Left') }}</label>
                                <input type="number" min="0" class="form-control margin-field" id="margin_left" name="margins[left]" value="{{ $margins['left'] ?? 0 }}">
                            </div>
                        </div>
                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-primary" id="saveSettingsBtn">
                                <i class="ti tabler-device-floppy me-1"></i>{{ __('Save Settings') }}
                            </button>
                            <a href="{{ route('printmanager.test.index') }}" class="btn btn-outline-secondary">
                                <i class="ti tabler-printer me-1"></i>{{ __('Test Printer') }}
                            </a>
                            <a href="{{ route('printmanager.printers.index') }}" class="btn btn-outline-info">
                                <i class="ti tabler-list me-1"></i>{{ __('Printer List') }}
                            </a>
                        </div>
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
            settingsStore: @json(route('printmanager.settings.store')),
            settingsShow: @json(route('printmanager.settings.show')),
        }),
        selectedPrinter: @json($setting->default_printer),
        printMethod: @json($setting->print_method),
    });
</script>
<script src="{{ asset('public/backend_assets/js/printmanager/qz-manager.js') }}?v={{ @filemtime(public_path('backend_assets/js/printmanager/qz-manager.js')) ?: time() }}"></script>
<script src="{{ asset('public/backend_assets/js/printmanager/printer.js') }}?v={{ @filemtime(public_path('backend_assets/js/printmanager/printer.js')) ?: time() }}"></script>
<script src="{{ asset('public/backend_assets/js/printmanager/settings.js') }}?v={{ @filemtime(public_path('backend_assets/js/printmanager/settings.js')) ?: time() }}"></script>
@endpush
