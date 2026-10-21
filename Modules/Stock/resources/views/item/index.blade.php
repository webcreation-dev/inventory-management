@extends('backend.backend_layout')
@section('page-title', 'List Item')
@push('page-css')
<link rel="stylesheet" href="{{ asset('public/backend_assets/vendor/libs/sweetalert2/sweetalert2.css') }}" />
<style>
    .barcode-preview {
        border: 1px solid #ddd;
        padding: 16px;
        text-align: center;
        background: #f0f0f0;
        margin: 10px 0;
        overflow: auto;
    }
    .barcode-preview-surface {
        margin: 0 auto;
        box-sizing: border-box;
        border: 1px dashed #999;
        background: #fff;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        text-align: center;
    }
    .barcode-controls {
        background: #f8f9fa;
        padding: 15px;
        border-radius: 5px;
        margin-bottom: 20px;
    }
    .barcode-field-control {
        margin-bottom: 10px;
    }
</style>
@endpush
@section('page-content')
<div class="container-xxl flex-grow-1 container-p-y">
    <!-- Add Product -->
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-6 row-gap-4">
        <div class="d-flex flex-column justify-content-center">
            <h4 class="mb-0">{{ __('List') }} {{ __('Item') }}</h4>
        </div>
        @include('backend.components.breadcrumb', [
            'breadcrumbs' => [
                [
                    'label' => '<i class="ti tabler-home"></i>',
                    'link' => '#'
                ],
                [
                    'label' =>  __('Item') . ' ' . __('Stock'), 
                    'link' => '#'
                ],
                [
                    'label' => __('List') . ' ' .  __('Item'),
                    'active' => true
                ]
            ]
        ])
    </div>

    @if(session('success'))
        {!! insertSuccess(session('success')) !!}
    @endif
    @if(session('error'))
        {!! insertFailed(session('error')) !!}
    @endif

    @if($status == 'success')
        {!! insertSuccess('Item created successfully') !!}
    @endif

    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-datatable table-responsive pt-0">
                    <table class="datatables-basic table">
                        <thead>
                            <tr>
                                <th>{{ __('SN') }}</th>
                                <th>{{ __('Name') }}</th>
                                <th>{{ __('Type') }}</th>
                                <th>{{ __('Category') }}</th>
                                <th>{{ __('Purchase') }} {{ __('Price') }}</th>
                                <th>{{ __('Sale') }} {{ __('Price') }}</th>
                                <th>{{ __('Action') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Barcode Print Modal -->
<div class="modal fade" id="barcodePrintModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable" role="document">
        <div class="modal-content">
            <div class="modal-header pb-3">
                <h5 class="modal-title">{{ __('Barcode Print Options') }}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="row mb-3">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-body">
                                <h6 class="mb-3">{{ __('Item Information') }}</h6>
                                <div class="row">
                                    <div class="col-md-4">
                                        <strong>{{ __('Name') }}:</strong> <span id="barcodeModalItemName">N/A</span>
                                    </div>
                                    <div class="col-md-4">
                                        <strong>{{ __('Code') }}:</strong> <span id="barcodeModalItemCode">N/A</span>
                                    </div>
                                    <div class="col-md-4">
                                        <strong>{{ __('Price') }}:</strong> <span id="barcodeModalItemPrice">0.00</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div id="variationSelectContainer" class="row mb-3" style="display: none;">
                    <div class="col-12">
                        <label class="form-label">{{ __('Select Variation Item') }}</label>
                        <select class="form-select select2" id="modalVariationSelect">
                            <option value="">{{ __('Use Parent Item') }}</option>
                        </select>
                    </div>
                </div>

                <div class="row mb-3">
                    <div class="col-12 col-md-6">
                        <label class="form-label">{{ __('Quantity') }}</label>
                        <input type="text" class="form-control number-input" id="modalBarcodeQuantity" value="1" min="1">
                        <small class="text-muted">{{ __('Used as label count / barcode count') }}</small>
                    </div>
                    <div class="col-12 col-md-6 mt-3 mt-md-0">
                        <label class="form-label" for="modalPrintMode">{{ __('Print mode') }}</label>
                        <select class="form-select" id="modalPrintMode">
                            <option value="label">{{ __('Print in label (1 per page)') }}</option>
                            <option value="multiple">{{ __('Print barcode multiple') }}</option>
                        </select>
                        <small class="text-muted">{{ __('Choose between label page mode and multi barcode sheet mode') }}</small>
                    </div>
                    <div class="col-12 col-md-6 mt-3 mt-md-0" id="modalMultiplePerPageWrap" style="display: none;">
                        <label class="form-label" for="modalMultiplePerPage">{{ __('Barcodes per page') }}</label>
                        <input type="number" class="form-control" id="modalMultiplePerPage" value="12" min="2" max="100" step="1">
                    </div>
                </div>

                <div class="barcode-controls">
                    <h6 class="mb-3">{{ __('Label size') }} ({{ __('mm') }})</h6>
                    <div class="row g-3 mb-3">
                        <div class="col-md-4">
                            <label class="form-label" for="modalLabelWidth">{{ __('Label width') }} ({{ __('mm') }})</label>
                            <input type="number" class="form-control form-control-sm" id="modalLabelWidth" value="50" min="20" max="200" step="1">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="modalLabelHeight">{{ __('Label height') }} ({{ __('mm') }})</label>
                            <input type="number" class="form-control form-control-sm" id="modalLabelHeight" value="30" min="15" max="200" step="1">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="modalBarcodeHeight">{{ __('Barcode height') }} ({{ __('px') }})</label>
                            <input type="number" class="form-control form-control-sm" id="modalBarcodeHeight" value="25" min="20" max="200">
                        </div>
                    </div>

                    <h6 class="mb-3">{{ __('Barcode Options') }}</h6>

                    <div class="row g-3 mb-3">
                        <div class="col-md-4">
                            <label class="form-label" for="modalTextLineHeight">{{ __('Line height') }}</label>
                            <input type="number" class="form-control form-control-sm" id="modalTextLineHeight" value="1.2" min="0.8" max="2.5" step="0.1">
                        </div>
                        
                    </div>

                    <div class="barcode-field-control">
                        <div class="row align-items-center">
                            <div class="col-md-4">
                                <div class="form-check mb-0">
                                    <input class="form-check-input" type="checkbox" id="modalShowCompanyName" checked>
                                    <label class="form-check-label" for="modalShowCompanyName">{{ __('Company name') }}</label>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">{{ __('Font size') }}</label>
                                <input type="number" class="form-control form-control-sm" id="modalCompanyNameFontSize" value="14" min="8" max="72">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">{{ __('Font weight') }}</label>
                                <select class="form-select form-select-sm" id="modalCompanyNameFontWeight">
                                    <option value="normal">normal</option>
                                    <option value="bold">bold</option>
                                    <option value="bolder">bolder</option>
                                    <option value="lighter">lighter</option>
                                    <option value="100">100</option>
                                    <option value="200">200</option>
                                    <option value="300">300</option>
                                    <option value="400">400</option>
                                    <option value="500">500</option>
                                    <option value="600">600</option>
                                    <option value="700" selected>700</option>
                                    <option value="800">800</option>
                                    <option value="900">900</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="barcode-field-control">
                        <div class="row align-items-center">
                            <div class="col-md-4">
                                <div class="form-check mb-0">
                                    <input class="form-check-input" type="checkbox" id="modalShowItemName" checked>
                                    <label class="form-check-label" for="modalShowItemName">{{ __('Product name') }}</label>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">{{ __('Font size') }}</label>
                                <input type="number" class="form-control form-control-sm" id="modalItemNameFontSize" value="14" min="8" max="72">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">{{ __('Font weight') }}</label>
                                <select class="form-select form-select-sm" id="modalItemNameFontWeight">
                                    <option value="normal">normal</option>
                                    <option value="bold">bold</option>
                                    <option value="bolder">bolder</option>
                                    <option value="lighter">lighter</option>
                                    <option value="100">100</option>
                                    <option value="200">200</option>
                                    <option value="300">300</option>
                                    <option value="400">400</option>
                                    <option value="500" selected>500</option>
                                    <option value="600">600</option>
                                    <option value="700">700</option>
                                    <option value="800">800</option>
                                    <option value="900">900</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="barcode-field-control">
                        <div class="row align-items-center">
                            <div class="col-md-4">
                                <div class="form-check mb-0">
                                    <input class="form-check-input" type="checkbox" id="modalShowPrice" checked>
                                    <label class="form-check-label" for="modalShowPrice">{{ __('Product price') }}</label>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">{{ __('Font size') }}</label>
                                <input type="number" class="form-control form-control-sm" id="modalPriceFontSize" value="14" min="8" max="72">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">{{ __('Font weight') }}</label>
                                <select class="form-select form-select-sm" id="modalPriceFontWeight">
                                    <option value="normal">normal</option>
                                    <option value="bold">bold</option>
                                    <option value="bolder">bolder</option>
                                    <option value="lighter">lighter</option>
                                    <option value="100">100</option>
                                    <option value="200">200</option>
                                    <option value="300">300</option>
                                    <option value="400" selected>400</option>
                                    <option value="500">500</option>
                                    <option value="600">600</option>
                                    <option value="700">700</option>
                                    <option value="800">800</option>
                                    <option value="900">900</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="barcode-field-control">
                        <div class="row align-items-center">
                            <div class="col-md-4">
                                <div>Barcode</div>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">{{ __('Barcode width') }}</label>
                                <input type="number" class="form-control form-control-sm" id="modalBarcodeWidth" value="2" min="1" max="6" step="0.2">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">{{ __('Barcode text size') }}</label>
                                <input type="number" class="form-control form-control-sm" id="modalBarcodeFontSize" value="12" min="8" max="72">
                            </div>
                        </div>
                    </div>
                </div>

                <div class="barcode-preview">
                    <h6 class="mb-3">{{ __('Preview') }}</h6>
                    <div id="barcodeModalPreview"></div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-label-primary" id="modalSetBarcodeDefault">
                    <i class="ti tabler-device-floppy me-2"></i> {{ __('Set Default') }}
                </button>
                <button type="button" class="btn btn-primary" id="modalPrintBarcode">
                    <i class="ti tabler-printer me-2"></i> {{ __('Print Barcode') }}
                </button>
                <button type="button" class="btn btn-label-secondary" data-bs-dismiss="modal">{{ __('Close') }}</button>
            </div>
        </div>
    </div>
</div>

@endsection
@push('page-js')
@routes
<!-- Page JS -->
<script src="{{ asset('public/backend_assets/vendor/libs/sweetalert2/sweetalert2.js')}}"></script>
<script src="{{ asset('public/backend_assets/js/extended-ui-sweetalert2.js')}}"></script>
<script src="{{ asset('public/backend_assets/vendor/js/barcode.js')}}"></script>
<script src="{{ asset('public/backend_assets/js/pages_list_js/list_item.js')}}?v={{ @filemtime(public_path('backend_assets/js/pages_list_js/list_item.js')) ?: time() }}"></script>
<script src="{{ asset('public/backend_assets/js/pages_list_js/delete_confirmation.js')}}?v={{ @filemtime(public_path('backend_assets/js/pages_list_js/delete_confirmation.js')) ?: time() }}"></script>
@endpush

