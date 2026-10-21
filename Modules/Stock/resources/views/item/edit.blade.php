@extends('backend.backend_layout')
@section('page-title',__('Edit Item'))
@push('page-css')
<link rel="stylesheet" href="{{ asset('public/backend_assets/vendor/libs/dropzone/dropzone.css') }}" />
<link rel="stylesheet" href="{{ asset('public/backend_assets/vendor/libs/flatpickr/flatpickr.css') }}" />
<link rel="stylesheet" href="{{ asset('public/backend_assets/vendor/css/pages/cropper.min.css') }}" />
<style>
    body {
        overflow-x: hidden;
    }
    .variation_generator .table-responsive thead th:first-child, .variation_generator .table-responsive tbody td:first-child {
        padding-left: 0px !important
    }
    .remove-image i {
        margin-right: 5px;
        font-size: 14px;
    }
    .item-ckeditor-host .ck-editor__editable {
        min-height: 220px;
    }
    .item-ckeditor-host .ck.ck-editor {
        width: 100%;
    }
    .add-medicine-field {
        height: 37px;
    }
    /* Hide unit information for Service_Product by default */
    /* @if($item->type === 'Service_Product')
    .hide-for-service-product {
        display: none !important;
    }
    @endif */
</style>
@endpush
@section('page-content')

<div class="container-xxl flex-grow-1 container-p-y">
    <!-- Edit Item -->
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-6 row-gap-4">
        <div class="d-flex flex-column justify-content-center">
            <h4 class="mb-0">{{ __('Edit Item') }}</h4>
        </div>
        @include('backend.components.breadcrumb', [
            'breadcrumbs' => [
                [
                    'label' => '<i class="ti tabler-home"></i>',
                    'link' => '#'
                ],
                [
                    'label' => __('Items'), 
                    'link' => '#'
                ],
                [
                    'label' => __('Edit Item'),
                    'active' => true
                ]
            ]
        ])
    </div>

    <div class="row">
        <div class="col-12">
            <div class="card">
                <form action="{{ route('item.update', $item->encrypted_id) }}" method="POST" id="itemForm" enctype="multipart/form-data">
                    @csrf
                    @method('PUT')
                    <div class="card-header">
                        <h5 class="card-tile mb-0">{{ __('Item Basic Information') }}</h5>
                    </div>
                    
                    <div class="card-body">
                        <div class="row">
                            <div class="col-12 col-md-6 col-lg-4">
                                <div class="mb-5 validate_wrapper">
                                    <label class="form-label" for="type">{{ __('Item Type') }}   {!! requiredField() !!}</label>
                                    <select id="type" name="type_old" class="select2 form-select item_type" data-placeholder="Item Type" disabled>
                                        @if(product_type_enabled('General') || $item->type == 'General_Product')
                                        <option value="General_Product" {{ $item->type == 'General_Product' ? 'selected' : '' }}>{{ __('General Product') }}</option>
                                        @endif
                                        @if((product_type_enabled('Variation') && module_enabled('variation_information')) || $item->type == 'Variation_Product')
                                        <option value="Variation_Product" {{ $item->type == 'Variation_Product' ? 'selected' : '' }}>{{ __('Variation Product') }}</option>
                                        @endif
                                        @if(product_type_enabled('IMEI') || $item->type == 'IMEI_Product')
                                        <option value="IMEI_Product" {{ $item->type == 'IMEI_Product' ? 'selected' : '' }}>{{ __('IMEI Product') }}</option>
                                        @endif
                                        @if(product_type_enabled('Serial') || $item->type == 'Serial_Product')
                                        <option value="Serial_Product" {{ $item->type == 'Serial_Product' ? 'selected' : '' }}>{{ __('Serial Product') }}</option>
                                        @endif
                                        @if(((product_type_enabled('Expiry') || product_type_enabled('Medicine')) && module_enabled('pharmacy_information')) || $item->type == 'Medicine_Product')
                                        <option value="Medicine_Product" {{ $item->type == 'Medicine_Product' ? 'selected' : '' }}>{{ __('Medicine Product') }}</option>
                                        @endif
                                        @if((product_type_enabled('Installment') && module_enabled('installment_sale')) || $item->type == 'Installment_Product')
                                        <option value="Installment_Product" {{ $item->type == 'Installment_Product' ? 'selected' : '' }}>{{ __('Installment Product') }}</option>
                                        @endif
                                        @if(product_type_enabled('Service') || $item->type == 'Service_Product')
                                        <option value="Service_Product" {{ $item->type == 'Service_Product' ? 'selected' : '' }}>{{ __('Service Product') }}</option>
                                        @endif
                                        @if(product_type_enabled('Combo') || $item->type == 'Combo_Product')
                                        <option value="Combo_Product" {{ $item->type == 'Combo_Product' ? 'selected' : '' }}>{{ __('Combo Product') }}</option>
                                        @endif
                                    </select>
                                    <input type="hidden" name="type" id="type" value="{{ $item->type }}" />
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-12 col-md-6 col-lg-4">
                                <div class="mb-5 validate_wrapper">
                                    <label class="form-label" for="name">{{ __('Item Name') }} {!! requiredField() !!}</label>
                                    <input type="text" class="form-control" placeholder="{{ __('Item Name') }}" name="name" id="name" value="{{ old('name', $item->name) }}" />
                                </div>
                            </div>
                            
                            <div class="col-12 col-md-6 col-lg-4">
                                <div class="mb-5 validate_wrapper">
                                    <label class="form-label" for="alternative_name">{{ __('Alternative Name') }}</label>
                                    <input type="text" class="form-control" placeholder="{{ __('Alternative Name') }}" name="alternative_name" id="alternative_name" value="{{ old('alternative_name', $item->alternative_name) }}" />
                                </div>
                            </div>

                            <div class="col-12 col-md-6 col-lg-4">
                                <div class="mb-5 validate_wrapper">
                                    <label class="form-label" for="code">{{ __('Item Code') }} {!! requiredField() !!}</label>
                                    <input type="text" class="form-control" placeholder="{{ __('Item Code') }}" name="code" id="code" value="{{ old('code', $item->code) }}" />
                                </div>
                            </div>

                            <div class="col-12 col-md-6 col-lg-4">
                                <div class="d-flex justify-content-between">
                                    <div class="mb-5 w-100 validate_wrapper">
                                        <label class="form-label" for="category_id">
                                            <span>{{ __('Category') }}</span> {!! requiredField() !!}
                                        </label>
                                        <select id="category_id" name="category_id" class="select2 form-select" data-placeholder="{{ __('Select Category') }}">
                                            <option value="">{{ __('Select Category') }}</option>
                                            @foreach($categories ?? [] as $category)
                                                <option value="{{ $category->id }}" {{ $item->category_id == $category->id ? 'selected' : '' }}>{{ $category->name }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <a type="button" href="javascript:void(0);" class="fw-medium btn btn-icon btn-label-primary ms-4 add-plus-btn" data-bs-toggle="modal"
                                data-bs-target="#modal_category"
                                    ><i class="icon-base ti tabler-plus icon-md"></i></a>
                                </div>
                            </div>

                            @unless(defined('LP') && strtoupper((string) LP) === 'BD')
                            <div class="col-12 col-md-6 col-lg-4">
                                <div class="mb-5 validate_wrapper">
                                    <label class="form-label" for="sub_category_id">
                                        <span>{{ __('Sub Category') }}</span>
                                    </label>
                                    <select id="sub_category_id" name="sub_category_id" class="select2 form-select"
                                            data-placeholder="{{ __('Select Sub Category') }}"
                                            data-by-category-url="{{ route('item-sub-category.by-category') }}"
                                            data-selected="{{ $item->sub_category_id }}">
                                        <option value="">{{ __('Select Sub Category') }}</option>
                                        @foreach($subCategories ?? [] as $subCategory)
                                            <option value="{{ $subCategory->id }}" {{ (int) $item->sub_category_id === (int) $subCategory->id ? 'selected' : '' }}>
                                                {{ $subCategory->name }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                            @endunless

                            <div class="col-12 col-md-6 col-lg-4 hide-for-service-product hide-for-combo-product">
                                <div class="d-flex justify-content-between">
                                    <div class="mb-5 w-100 validate_wrapper">
                                        <label class="form-label" for="brand_id">
                                            <span>{{ __('Brand') }}</span>
                                        </label>
                                        <select id="brand_id" name="brand_id" class="select2 form-select" data-placeholder="{{ __('Select Brand') }}">
                                            <option value="">{{ __('Select Brand') }}</option>
                                            @foreach($brands ?? [] as $brand)
                                                <option value="{{ $brand->id }}" {{ $item->brand_id == $brand->id ? 'selected' : '' }}>{{ $brand->name }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <a type="button" href="javascript:void(0);" class="fw-medium btn btn-icon btn-label-primary ms-4 add-plus-btn" data-bs-toggle="modal"
                                data-bs-target="#modal_brand"
                                    ><i class="icon-base ti tabler-plus icon-md"></i></a>
                                </div>
                            </div>

                            <div class="col-12 col-md-6 col-lg-4 hide-for-service-product hide-for-combo-product">
                                <div class="d-flex justify-content-between">
                                    <div class="mb-5 w-100 validate_wrapper">
                                        <label class="form-label" for="supplier_id">
                                            <span>{{ __('Supplier') }}</span>
                                        </label>
                                        <select id="supplier_id" name="supplier_id" class="select2 form-select" data-placeholder="{{ __('Select Supplier') }}">
                                            <option value="">{{ __('Select Supplier') }}</option>
                                            @foreach($suppliers ?? [] as $supplier)
                                                <option value="{{ $supplier->id }}" {{ $item->supplier_id == $supplier->id ? 'selected' : '' }}>{{ $supplier->name }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <a type="button" href="javascript:void(0);" class="fw-medium btn btn-icon btn-label-primary ms-4 add-plus-btn" data-bs-toggle="modal" data-bs-target="#modal_supplier"
                                    ><i class="icon-base ti tabler-plus icon-md"></i></a>
                                </div>
                            </div>

                            @if(module_enabled('loyalty_feature'))
                            <div class="col-12 col-md-6 col-lg-4">
                                <div class="mb-5 validate_wrapper">
                                    <label class="form-label" for="loyalty_point">{{ __('Loyalty Point') }}</label>
                                    <input type="text" class="form-control number-input" placeholder="{{ __('Loyalty Point') }}" name="loyalty_point" id="loyalty_point" value="{{ old('loyalty_point', $item->loyalty_point) }}" />
                                </div>
                            </div>
                            @endif
                            <div class="col-12 hide-for-service-product hide-for-combo-product">
                                <div class="mb-5 validate_wrapper">
                                    <label class="form-label" for="description">{{ __('Description') }}</label>
                                    <textarea name="description" id="description" class="d-none">{{ old('description', $item->description) }}</textarea>
                                    <div id="description_editor" class="item-ckeditor-host border rounded"></div>
                                </div>
                            </div>
                        </div>
                    </div>


                    @if(module_enabled('pharmacy_information') || $item->type == 'Medicine_Product')
                    <div class="card-header pt-0 pharmacy_section" style="display: none;">
                        <h5 class="card-tile mb-0">{{ __('Pharmacy Information') }}</h5>
                    </div>
                    
                    <div class="card-body pharmacy_section" style="display: none;">
                        <div class="row">
                            <div class="col-12 col-md-6 col-lg-4">
                                <div class="mb-5 validate_wrapper">
                                    <label class="form-label" for="expiry_date_maintain">{{ __('Expiry Date Maintain') }}</label>
                                    <select id="expiry_date_maintain" name="expiry_date_maintain" class="select2 form-select" data-placeholder="{{ __('Expiry Date Maintain') }}">
                                        <option value="Yes" {{ $item->expiry_date_maintain == 'Yes' ? 'selected' : '' }}>{{ __('Yes') }}</option>
                                        <option value="No" {{ $item->expiry_date_maintain == 'No' ? 'selected' : '' }}>{{ __('No') }}</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-12 col-md-6 col-lg-4">
                                <div class="mb-5 validate_wrapper">
                                    <label class="form-label" for="generic_name">{{ __('Generic Name') }}</label>
                                    <input type="text" class="form-control" placeholder="{{ __('Generic Name') }}" name="generic_name" id="generic_name" value="{{ old('generic_name', $item->generic_name) }}" />
                                </div>
                            </div>

                            @if(module_enabled('rack') || $item->rack_id)
                            <div class="col-12 col-md-6 col-lg-4">
                                <div class="d-flex justify-content-between">
                                    <div class="mb-5 w-100 validate_wrapper">
                                        <label class="form-label" for="rack_id">
                                            <span>{{ __('Rack') }}</span>
                                        </label>
                                        <select id="rack_id" name="rack_id" class="select2 form-select" data-placeholder="Select Rack">
                                            <option value="">{{ __('Select Rack') }}</option>
                                            @foreach($racks ?? [] as $rack)
                                                <option value="{{ $rack->id }}" {{ $item->rack_id == $rack->id ? 'selected' : '' }}>{{ $rack->name }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    @if(module_enabled('rack'))
                                    <a type="button" href="javascript:void(0);" class="fw-medium btn btn-icon btn-label-primary ms-4 add-plus-btn" data-bs-toggle="modal"
                                data-bs-target="#modal_rack"
                                    ><i class="icon-base ti tabler-plus icon-md"></i></a>
                                    @endif
                                </div>
                            </div>
                            @endif

                        </div>
                    </div>
                    @endif


                    <div class="card-header pt-0 hide-for-service-product hide-for-combo-product">
                        <h5 class="card-tile mb-0">{{ __('Unit Information') }}</h5>
                    </div>
                    <div class="card-body hide-for-service-product hide-for-combo-product">
                        @if(module_enabled('double_unit'))
                        <div class="row">
                            <div class="col-12 col-md-6 col-lg-4">
                                <div class="mb-5 validate_wrapper">
                                    <label class="form-label" for="unit_type">{{ __('Unit Type') }} {!! requiredField() !!}</label>
                                    <select id="unit_type" class="select2 form-select" name="unit_type" data-placeholder="Unit Type">
                                        <option value="1" {{ $item->unit_type == '1' ? 'selected' : '' }}>{{ __('Single Unit') }}</option>
                                        <option value="2" {{ $item->unit_type == '2' ? 'selected' : '' }}>{{ __('Double Unit') }}</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        @else
                        <input type="hidden" name="unit_type" id="unit_type" value="1">
                        @endif
                        <div class="row">
                            <div class="col-12 col-md-6 col-lg-4 purchase_unit_wrap" style="display: none;">
                                <div class="d-flex justify-content-between">
                                    <div class="mb-5 w-100 validate_wrapper">
                                        <label class="form-label" for="purchase_unit_id">
                                            <span>{{ __('Purchase Unit') }}</span> {!! requiredField() !!}
                                        </label>
                                        <select id="purchase_unit_id" name="purchase_unit_id" class="select2 form-select" data-placeholder="Purchase Unit">
                                            <option value="">{{ __('Purchase Unit') }}</option>
                                            @foreach($units ?? [] as $unit)
                                                <option value="{{ $unit->id }}" {{ $item->purchase_unit_id == $unit->id ? 'selected' : '' }}>{{ $unit->unit_name }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <a href="javascript:void(0);" class="fw-medium btn btn-icon btn-label-primary ms-4 purchase_unit add-plus-btn"
                                    data-bs-toggle="modal" data-bs-target="#modal_unit"
                                    ><i class="icon-base ti tabler-plus icon-md"></i></a>
                                </div>
                            </div>

                            <div class="col-12 col-md-6 col-lg-4 sale_unit_wrap">
                                <div class="d-flex justify-content-between">
                                    <div class="mb-5 w-100 validate_wrapper">
                                        <label class="form-label" for="sale_unit_id">
                                            <span class="sale_unit_label">{{ __('Unit') }}</span> {!! requiredField() !!}
                                        </label>
                                        <select id="sale_unit_id" name="sale_unit_id" class="select2 form-select" data-placeholder="{{ __('Unit') }}">
                                            <option value="">{{ __('Unit') }}</option>
                                            @foreach($units ?? [] as $unit)
                                                <option value="{{ $unit->id }}" {{ $item->sale_unit_id == $unit->id ? 'selected' : '' }}>{{ $unit->unit_name }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <a href="javascript:void(0);" class="fw-medium btn btn-icon btn-label-primary ms-4 sale_unit add-plus-btn"
                                    data-bs-toggle="modal" data-bs-target="#modal_unit"
                                    ><i class="icon-base ti tabler-plus icon-md"></i></a>
                                </div>
                            </div>
                            <div class="col-12 col-md-6 col-lg-4 conversion_rate_wrap" style="display: none;">
                                <div class="mb-5 validate_wrapper">
                                    <label class="form-label" for="conversion_rate">
                                        <span>{{ __('Conversion Rate') }}</span> {!! requiredField() !!}
                                    </label>
                                    <input type="text" class="form-control" placeholder="{{ __('Conversion Rate') }}" name="conversion_rate" id="conversion_rate" value="{{ old('conversion_rate', $item->conversion_rate) }}" />
                                </div>
                            </div>
                            
                        </div>
                    </div>



                    <div class="card-header pt-0 hide-for-combo-product">
                        <h5 class="card-tile mb-0">{{ __('Price Information') }}</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-12 col-md-6 col-lg-4 purchase_price_wrap hide-for-service-product hide-for-combo-product">
                                <div class="mb-5 validate_wrapper">
                                    <label class="form-label" for="purchase_price">{{ __('Purchase Price') }}</label>
                                    <input type="text" class="form-control number-input" placeholder="{{ __('Purchase Price') }}" name="purchase_price" id="purchase_price" value="{{ old('purchase_price', $item->purchase_price) }}" />
                                </div>
                            </div>
                            <div class="col-12 col-md-6 col-lg-4 hide-for-service-product hide-for-combo-product">
                                <div class="mb-5 validate_wrapper">
                                    <label class="form-label" for="profit_margin">{{ __('Profit Margin') }}</label>
                                    <div class="input-group">
                                        <input type="text" class="form-control number-input" id="profit_margin" placeholder="{{ __('Profit Margin') }}" name="profit_margin" aria-describedby="profit_margin" value="{{ old('profit_margin', $item->profit_margin) }}">
                                        <span id="profit_margin" class="input-group-text cursor-pointer">%</span>
                                    </div>
                                </div>
                            </div>
                            <div class="col-12 col-md-6 col-lg-4 sale_price_wrap hide-for-combo-product">
                                <div class="mb-5 validate_wrapper">
                                    <label class="form-label" for="sale_price">{{ __('Sale Price') }} {!! requiredField() !!}</label>
                                    <input type="text" class="form-control number-input" placeholder="{{ __('Sale Price') }}" name="sale_price" id="sale_price" value="{{ old('sale_price', $item->sale_price) }}" />
                                </div>
                            </div>
                            <div class="col-12 col-md-6 col-lg-4 whole_sale_price_wrap hide-for-service-product hide-for-combo-product">
                                <div class="mb-5 validate_wrapper">
                                    <label class="form-label" for="whole_sale_price">{{ __('Whole Sale Price') }}</label>
                                    <input type="text" class="form-control number-input" placeholder="{{ __('Whole Sale Price') }}" name="whole_sale_price" id="whole_sale_price" value="{{ old('whole_sale_price', $item->whole_sale_price) }}" />
                                </div>
                            </div>
                        </div>
                    </div>

                    @if(module_enabled('variation_information') || $item->type == 'Variation_Product')
                    <!-- Variation Section -->
                    <div class="card-header pt-0 variation_section" style="display: none;">
                        <h5 class="card-tile mb-0">{{ __('Variation Information') }}</h5>
                    </div>
                    <div class="card-body variation_section variation_generator" style="display: none;">
                        <div class="row">
                            <div class="col-12">
                                <div class="table-responsive">
                                    <table class="table table-bordered" id="variationAttributesTable">
                                        <thead>
                                            <tr>
                                                <th style="width: 45%;">{{ __('Variation Option') }}</th>
                                                <th style="width: 45%;">{{ __('Variation Values') }}</th>
                                                <th style="width: 10%;">{{ __('Action') }}</th>
                                            </tr>
                                        </thead>
                                        <tbody id="variationAttributesBody">
                                            <tr class="variation-row">
                                                <td>
                                                    <select class="form-select select2 variation-option" data-placeholder="{{ __('Select Variation') }}">
                                                        <option value="">{{ __('Select Variation') }}</option>
                                                    </select>
                                                </td>
                                                <td>
                                                    <select class="form-select select2 variation-values" multiple data-placeholder="{{ __('Select Values') }}" style="width: 100%;">
                                                    </select>
                                                </td>
                                                <td>
                                                    <button type="button" class="btn text-danger remove-variation-row">
                                                        <i class="icon-base ti tabler-trash"></i>
                                                    </button>
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                                <div class="mt-3 d-flex gap-2">
                                    <button type="button" class="btn btn-outline-primary d-flex gap-1" id="addVariationRow">
                                        <i class="ti tabler-plus"></i> {{ __('Add Variation Row') }}
                                    </button>
                                    <button type="button" class="btn btn-primary d-flex gap-1" id="generateVariations">
                                        <i class="ti tabler-refresh"></i> {{ __('Generate Variations') }}
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Generated Variations Section -->
                    <div class="card-header pt-0 generated_variations_section" style="display: none;">
                        <div class="d-flex justify-content-between align-items-center card-tile">
                            <h5 class="mb-0">{{ __('Generated Variations') }}</h5>
                            <button type="button" class="btn btn-outline-danger d-flex gap-1 align-items-center" id="backToGenerate">
                                <i class="ti tabler-arrow-left"></i> {{ __('Back To Generate') }}
                            </button>
                        </div>
                    </div>
                    <div class="card-body generated_variations_section" style="display: none;">
                        <div class="row" id="generatedVariationsBody">
                        </div>
                    </div>
                    @endif

                    <!-- Combo Product Section -->
                    <div class="card-header pt-0 combo_product_section" style="display: none;">
                        <h5 class="card-tile mb-0">{{ __('Combo Items') }}</h5>
                    </div>
                    <div class="card-body combo_product_section" style="display: none;">
                        <div class="row mb-3">
                            <div class="col-12 col-md-6 col-lg-4">
                                <div class="mb-3">
                                    <label class="form-label" for="combo_item_select">{{ __('Select Item') }}</label>
                                    <select id="combo_item_select" class="select2 form-select" data-placeholder="{{ __('Select General Product') }}">
                                        <option value="">{{ __('Select Item') }}</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-bordered" id="comboItemsTable">
                                <thead>
                                    <tr>
                                        <th style="width: 5%;">{{ __('SN') }}</th>
                                        <th style="width: 10%;">{{ __('Show In Invoice') }}</th>
                                        <th style="width: 30%;">{{ __('Item Name - Code') }}</th>
                                        <th style="width: 15%;">{{ __('Quantity') }}</th>
                                        <th style="width: 15%;">{{ __('Amount') }}</th>
                                        <th style="width: 15%;">{{ __('Total') }}</th>
                                        <th style="width: 10%;">{{ __('Action') }}</th>
                                    </tr>
                                </thead>
                                <tbody id="comboItemsBody">
                                    <!-- Combo items will be added here dynamically -->
                                </tbody>
                            </table>
                        </div>
                        <div class="row mt-3">
                            <div class="col-12 text-end">
                                <div class="mb-3">
                                    <label class="form-label" for="combo_sale_price"><strong>{{ __('Sale Price') }}:</strong></label>
                                    <input type="text" class="form-control text-end" id="combo_sale_price" name="combo_sale_price" placeholder="0.00" readonly style="max-width: 200px; display: inline-block; margin-left: 10px;" value="{{ old('combo_sale_price', $item->sale_price) }}">
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="card-header pt-0 stock_information_section hide-for-service-product hide-for-combo-product">
                        <h5 class="card-tile mb-0">{{ __('Stock Information') }}</h5>
                    </div>
                    <div class="card-body stock_information_section hide-for-service-product hide-for-combo-product">
                        <div class="row">
                            <div class="col-12 col-md-6 col-lg-4">
                                <div class="mb-5 validate_wrapper">
                                    <label class="form-label" for="opening_stock">{{ __('Opening Stock') }}</label>
                                    <button type="button" class="btn btn-outline-primary w-100 set-opening-stock-btn" id="setOpeningStockBtn">
                                        <i class="ti tabler-settings me-1"></i> {{ __('Set Opening Stock') }}
                                    </button>
                                    <div class="opening-stock-summary mt-2" id="openingStockSummary" style="display: none;"></div>
                                </div>
                            </div>
                            
                            <div class="col-12 col-md-6 col-lg-4">
                                <div class="mb-5 validate_wrapper">
                                    <label class="form-label" for="alert_quantity">{{ __('Alert Stock') }}</label>
                                    <input type="text" class="form-control number-input" placeholder="{{ __('Alert Stock') }}" name="alert_quantity" id="alert_quantity" value="{{ old('alert_quantity', $item->alert_quantity) }}" />
                                </div>
                            </div>
                            <div class="opening_stock_append d-none"></div>
                        </div>
                    </div>


                    @if(module_enabled('warranty_guarantee'))
                    <div class="card-header pt-0 hide-for-service-product hide-for-combo-product">
                        <h5 class="card-tile mb-0">{{ __('Warranty Information') }}</h5>
                    </div>
                    <div class="card-body hide-for-service-product hide-for-combo-product">
                        <div class="row">
                            <div class="col-12 col-md-6 col-lg-4">
                                <div class="mb-5 validate_wrapper">
                                    <label class="form-label" for="warranty">{{ __('Warranty') }}</label>
                                    <div class="row">
                                        <div class="col-6">
                                            <input type="text" class="form-control number-input" placeholder="{{ __('Warranty') }}" name="warranty" id="warranty" value="{{ old('warranty', $item->warranty) }}" />
                                        </div>
                                        <div class="col-6">
                                            <select id="warranty_date" class="select2 form-select" name="warranty_date" data-placeholder="Warranty Date">
                                                <option value="day" {{ $item->warranty_date == 'day' ? 'selected' : '' }}>{{ __('Day') }}</option>
                                                <option value="month" {{ $item->warranty_date == 'month' ? 'selected' : '' }}>{{ __('Month') }}</option>
                                                <option value="year" {{ $item->warranty_date == 'year' ? 'selected' : '' }}>{{ __('Year') }}</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-12 col-md-6 col-lg-4">
                                <div class="mb-5 validate_wrapper">
                                    <label class="form-label" for="guarantee">{{ __('Guarantee') }}</label>
                                    <div class="row">
                                        <div class="col-6">
                                            <input type="text" class="form-control number-input" placeholder="{{ __('Guarantee') }}" name="guarantee" id="guarantee" value="{{ old('guarantee', $item->guarantee) }}" />
                                        </div>
                                        <div class="col-6">
                                            <select id="guarantee_date" class="select2 form-select" name="guarantee_date" data-placeholder="Guarantee Date">
                                                <option value="day" {{ $item->guarantee_date == 'day' ? 'selected' : '' }}>{{ __('Day') }}</option>
                                                <option value="month" {{ $item->guarantee_date == 'month' ? 'selected' : '' }}>{{ __('Month') }}</option>
                                                <option value="year" {{ $item->guarantee_date == 'year' ? 'selected' : '' }}>{{ __('Year') }}</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    @endif

                    
                    <div class="card-header pt-0">
                        <h5 class="card-tile mb-0">{{ __('Tax Information') }}</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            @if(!empty($itemProfileTaxes))
                            <div class="col-12 col-md-6 col-lg-4">
                                <div class="mb-5 validate_wrapper">
                                    <label class="form-label" for="applicable_tax_id">{{ __('Applicable Tax') }}</label>
                                    @php
                                        $applicable_tax_ids = $item->applicable_tax_id
                                            ? array_map('trim', explode(',', $item->applicable_tax_id))
                                            : [];
                                    @endphp
                                    <select id="applicable_tax_id" name="applicable_tax_id[]" class="select2 form-select" multiple data-placeholder="{{ __('Select Tax') }}">
                                        @foreach($itemProfileTaxes as $tax)
                                            <option value="{{ $tax->id }}" {{ in_array((string)$tax->id, $applicable_tax_ids) ? 'selected' : '' }}>{{ $tax->tax_name }} ({{ $tax->tax_rate }}%)</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                            @endif

                            <div class="col-12 col-md-6 col-lg-4">
                                <div class="mb-5 validate_wrapper">
                                    <label class="form-label" for="tax_type">{{ __('Tax Type') }}</label>
                                    <select id="tax_type" name="tax_type" class="select2 form-select" data-placeholder="{{ __('Tax Type') }}">
                                        <option value="Exclusive" {{ old('tax_type', $item->tax_type ?? 'Exclusive') == 'Exclusive' ? 'selected' : '' }}>{{ __('Exclusive') }}</option>
                                        <option value="Inclusive" {{ old('tax_type', $item->tax_type ?? 'Exclusive') == 'Inclusive' ? 'selected' : '' }}>{{ __('Inclusive') }}</option>
                                    </select>
                                </div>
                            </div>

                            @if(isEnableGST())
                            <div class="col-12 col-md-6 col-lg-4">
                                <div class="mb-5 validate_wrapper">
                                    <label class="form-label" for="hsn_code">{{ __('HSN Code') }}</label>
                                    <input type="text" class="form-control" placeholder="{{ __('HSN Code') }}" name="hsn_code" id="hsn_code" value="{{ old('hsn_code', $item->hsn_code) }}" />
                                </div>
                            </div>
                            @endif
                        </div>
                    </div>


                    <!-- Media -->
                    <div class="card-header d-flex justify-content-between align-items-center card-title">
                        <h5 class="mb-0">{{ __('Item Image') }}</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6 col-12">
                                <div class="col-12 validate_wrapper">
                                    <label class="form-label mb-1" for="photo">{{ __('Primary Image') }}</label>
                                    <input type="file" class="form-control" id="photo" name="photo" accept="image/*">
                                    <input type="hidden" name="remove_photo" id="remove_photo" value="0">
                                    <div class="mt-2">
                                        @php
                                            $imageUrl = $item->photo 
                                                ? asset('public/uploads/items/' . $item->photo) 
                                                : asset('public/uploads/dummy_images/default-picture.png');
                                        @endphp
                                        <img id="photo_preview" src="{{ $imageUrl }}" alt="Item Image" style="border:1px dashed #d1d0d4; padding: 10px; border-radius: 5px; max-width: 200px; max-height: 200px; cursor: pointer;">
                                        <button type="button" class="btn btn-sm btn-danger mt-2 remove-image" style="{{ $item->photo ? '' : 'display: none;' }}">
                                            <i class="ti tabler-trash"></i> {{ __('Remove Image') }}
                                        </button>
                                    </div>
                                </div>
                            </div>
                            @unless(defined('LP') && strtoupper((string) LP) === 'BD')
                            <div class="col-md-6 col-12 mt-3 mt-md-0 additional_images_wrap">
                                <div class="col-12 validate_wrapper">
                                    <label class="form-label mb-1" for="gallery_images">{{ __('Additional Images') }}</label>
                                    <input type="file" class="form-control" id="gallery_images" name="gallery_images[]" accept="image/*" multiple>
                                    <small class="text-muted d-block mt-1">{{ __('You can select multiple images for the product gallery.') }}</small>
                                    <div id="existing_gallery" class="d-flex flex-wrap gap-2 mt-2">
                                        @foreach(($item->images ?? []) as $galleryImage)
                                            <div class="position-relative existing-gallery-item" data-id="{{ $galleryImage->id }}" style="width: 100px;">
                                                <img src="{{ $galleryImage->url }}" alt="Gallery" style="width: 100px; height: 100px; object-fit: cover; border: 1px dashed #d1d0d4; border-radius: 5px; padding: 4px;">
                                                <button type="button" class="btn btn-sm btn-danger position-absolute top-0 end-0 m-1 remove-existing-gallery" title="{{ __('Remove') }}">
                                                    <i class="ti tabler-x"></i>
                                                </button>
                                            </div>
                                        @endforeach
                                    </div>
                                    <div id="remove_gallery_ids_container"></div>
                                    <div id="gallery_preview" class="d-flex flex-wrap gap-2 mt-2"></div>
                                </div>
                            </div>
                            @endunless
                        </div>
                    </div>
                    
                    <!-- /Media -->

                    <!-- Submit Button -->
                    <div class="card-footer">
                        <div class="d-flex">
                            <button type="submit" name="submit" value="submit" class="btn btn-primary add_item_submit me-4">
                                {!! submitIconWithText('') !!}
                            </button>
                            <a href="{{ route('item.index') }}" class="btn btn-primary waves-effect waves-light">
                                {!! backIconWithText() !!}
                            </a>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>



<!-- Category Modal -->
<div class="modal fade" id="modal_category" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
            <form id="categoryForm">
                <div class="modal-header">
                    <h5 class="modal-title">{{ __('Category') }}</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-12 mb-4 validate_wrapper">
                            <label for="name" class="form-label">{{ __('Category') }} {{ __('Name') }} {!! requiredField() !!}</label>
                            <input type="text" id="name" name="name" class="form-control" placeholder="{{ __('Enter') }} {{ __('Category') }} {{ __('Name') }}" />
                        </div>
                        <div class="col-12 mb-4 validate_wrapper">
                            <label for="description" class="form-label">{{ __('Category') }} {{ __('Description') }}</label>
                            <textarea type="text" id="description" name="description" class="form-control" placeholder="{{ __('Enter') }} {{ __('Category') }} {{ __('Description') }}"></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="submit" class="btn btn-primary add_category">
                        {!! submitIconWithText('') !!}
                    </button>
                    <button type="button" class="btn btn-danger" data-bs-dismiss="modal">
                        {!! closeIconWithText() !!}
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
<!-- Category Modal End -->

<!-- Brand Modal -->
<div class="modal fade" id="modal_brand" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
            <form id="brandForm">
                <div class="modal-header">
                    <h5 class="modal-title">{{ __('Brand') }}</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-12 mb-4 validate_wrapper">
                            <label for="name" class="form-label">{{ __('Brand') }} {{ __('Name') }} {!! requiredField() !!}</label>
                            <input type="text" id="name" name="name" class="form-control" placeholder="{{ __('Enter') }} {{ __('Brand') }} {{ __('Name') }}" />
                        </div>
                        <div class="col-12 mb-4 validate_wrapper">
                            <label for="description" class="form-label">{{ __('Brand') }} {{ __('Description') }}</label>
                            <textarea type="text" id="description" name="description" class="form-control" placeholder="{{ __('Enter') }} {{ __('Brand') }} {{ __('Description') }}"></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="submit" class="btn btn-primary add_brand">
                        {!! submitIconWithText('') !!}
                    </button>
                    <button type="button" class="btn btn-danger" data-bs-dismiss="modal">
                        {!! closeIconWithText() !!}
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>
<!-- Brand Modal End -->


<!-- Supplier Modal -->
<div class="modal fade" id="modal_supplier" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-xl" role="document">
        <div class="modal-content">
            <form id="supplierForm">
                <div class="modal-header">
                    <h5 class="modal-title">{{ __('Add') }} {{ __('Supplier') }}</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-lg-4 col-md-6 col-12 mb-4 validate_wrapper">
                            <label for="name" class="form-label">{{ __('Name') }} {!! requiredField() !!}</label>
                            <input type="text" name="name" id="name" class="form-control" placeholder="{{ __('Enter') }} {{ __('Name') }}" />
                        </div>
                        <div class="col-lg-4 col-md-6 col-12 mb-4 validate_wrapper">
                            <label for="contact_person" class="form-label">{{ __('contact_person') }} {!! requiredField() !!}</label>
                            <input type="text" name="contact_person" id="contact_person" class="form-control" placeholder="{{ __('Enter') }} {{ __('contact_person') }}" />
                        </div>
                        <div class="col-lg-4 col-md-6 col-12 mb-4 validate_wrapper">
                            <label for="phone" class="form-label">{{ __('Phone') }} {!! requiredField() !!}</label>
                            <input type="text" name="phone" id="phone" class="form-control" placeholder="{{ __('Enter') }} {{ __('Phone') }}" />
                        </div>
                        <div class="col-lg-4 col-md-6 col-12 mb-4 validate_wrapper">
                            <label for="email" class="form-label">{{ __('Email') }}</label>
                            <input type="email" name="email" id="email" class="form-control" placeholder="{{ __('Enter') }} {{ __('Email') }}" />
                        </div>
                        <div class="col-lg-4 col-md-6 col-12 mb-4">
                            <label for="opening_balance" class="form-label">{{ __('opening_balance') }}</label>
                            <div class="row">
                                <div class="col-7 validate_wrapper">
                                    <input type="text" name="opening_balance" id="opening_balance" class="form-control" placeholder="{{ __('opening_balance') }}" />
                                </div>
                                <div class="col-5 validate_wrapper">
                                    <select name="opening_balance_type" id="opening_balance_type" class="select2 form-select" data-placeholder="{{ __('opening_balance_type') }}">
                                        <option value="Debit">{{ __('Debit') }}</option>
                                        <option value="Credit">{{ __('Credit') }}</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        <div class="col-lg-4 col-md-6 col-12 mb-4 validate_wrapper">
                            <label for="description" class="form-label">{{ __('Description') }}</label>
                            <textarea name="description" id="description" class="form-control" placeholder="{{ __('Enter') }} {{ __('Description') }}"></textarea>
                        </div>
                        <div class="col-lg-4 col-md-6 col-12 mb-4 validate_wrapper">
                            <label for="address" class="form-label">{{ __('Address') }}</label>
                            <textarea name="address" id="address" class="form-control" placeholder="{{ __('Enter') }} {{ __('Address') }}"></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="submit" class="btn btn-primary add_supplier">
                        {!! submitIconWithText('') !!}
                    </button>
                    <button type="button" class="btn btn-danger" data-bs-dismiss="modal">
                        {!! closeIconWithText() !!}
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
<!-- Supplier Modal End -->


<!-- Rack Modal -->
<div class="modal fade" id="modal_rack" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
            <form id="rackForm">
                <div class="modal-header">
                    <h5 class="modal-title">{{ __('Rack') }}</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-12 mb-4 validate_wrapper">
                            <label for="name" class="form-label">{{ __('Rack') }} {{ __('Name') }} {!! requiredField() !!}</label>
                            <input type="text" id="name" name="name" class="form-control" placeholder="{{ __('Enter') }} {{ __('Rack') }} {{ __('Name') }}" />
                        </div>
                        <div class="col-12 mb-4 validate_wrapper">
                            <label for="description" class="form-label">{{ __('Rack') }} {{ __('Description') }}</label>
                            <textarea type="text" id="description" name="description" class="form-control" placeholder="{{ __('Enter') }} {{ __('Rack') }} {{ __('Description') }}"></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="submit" class="btn btn-primary add_rack">
                        {!! submitIconWithText('') !!}
                    </button>
                    <button type="button" class="btn btn-danger" data-bs-dismiss="modal">
                        {!! closeIconWithText() !!}
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
<!-- Rack Modal End -->


<!-- Unit Modal -->
<div class="modal fade" id="modal_unit" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
            <form id="unitForm">
                <input type="hidden" class="sale_or_purchase" name="sale_or_purchase">
                <div class="modal-header">
                    <h5 class="modal-title">{{ __('Unit') }}</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-12 mb-4 validate_wrapper">
                            <label for="name" class="form-label">{{ __('Unit') }} {{ __('Name') }} {!! requiredField() !!}</label>
                            <input type="text" id="name" name="name" class="form-control" placeholder="{{ __('Enter') }} {{ __('Unit') }} {{ __('Name') }}" />
                        </div>
                        <div class="col-12 mb-4 validate_wrapper">
                            <label for="description" class="form-label">{{ __('Unit') }} {{ __('Description') }}</label>
                            <textarea type="text" id="description" name="description" class="form-control" placeholder="{{ __('Enter') }} {{ __('Unit') }} {{ __('Description') }}"></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="submit" class="btn btn-primary add_unit">
                        {!! submitIconWithText('') !!}
                    </button>
                    <button type="button" class="btn btn-danger" data-bs-dismiss="modal">
                        {!! closeIconWithText() !!}
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
<!-- Unit Modal End -->

<!-- Opening Stock Modal -->
<div class="modal fade" id="modal_outlet" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-xl" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">{{ __('opening_stock_set_for_outlet') }}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="openingStockModalBody">
                <div class="row" id="openingStockOutletsContainer">
                    @foreach ($outlets as $outlet)
                    <div class="col-12 col-md-6 col-lg-4 mb-4" data-outlet-id="{{ $outlet->id }}">
                        <div class="card h-100 outlet-stock-card">
                            <div class="card-header pb-0">
                                <h6 class="card-title mb-0"><strong>{{ __('Outlet') }}:</strong> {{ $outlet->outlet_name }}</h6>
                            </div>
                            <div class="card-body outlet-stock-body" data-outlet-id="{{ $outlet->id }}">
                                <!-- Content will be dynamically generated based on product type -->
                            </div>
                        </div>
                    </div>
                    @endforeach
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-primary opening_stock_submit">
                    {!! submitIconWithText('') !!}
                </button>
                <button type="button" class="btn btn-danger" data-bs-dismiss="modal">
                    {!! closeIconWithText() !!}
                </button>
            </div>
        </div>
    </div>
</div>
<!-- Opening Stock Modal End -->


@endsection

@push('page-js')
@routes
<script>
    // Pass item data to JavaScript for edit mode
    window.itemData = @json($item);
    window.outletsData = @json($outlets ?? []);
    window.openingStockData = @json($openingStockData ?? []);
    window.variationsData = @json($variations ?? []);
    window.comboItemsData = @json($comboItems ?? []);
    window.isEditMode = true;
</script>
<script src="{{ asset('public/backend_assets/vendor/libs/dropzone/dropzone.js') }}"></script>
<script src="{{ asset('public/backend_assets/vendor/libs/flatpickr/flatpickr.js') }}"></script>
<script src="{{ asset('public/backend_assets/vendor/js/cropper.js') }}"></script>
<script src="{{ asset('public/backend_assets/js/pages_js/edit_item.js') }}?v={{ @filemtime(public_path('backend_assets/js/pages_js/edit_item.js')) ?: time() }}"></script>
<script src="{{ asset('public/backend_assets/js/pages_js/item_sub_category_select.js') }}?v={{ @filemtime(public_path('backend_assets/js/pages_js/item_sub_category_select.js')) ?: time() }}"></script>
<script src="https://cdn.ckeditor.com/ckeditor5/41.4.2/classic/ckeditor.js"></script>
<script>
(function () {
    const input = document.getElementById('gallery_images');
    const preview = document.getElementById('gallery_preview');
    const removeContainer = document.getElementById('remove_gallery_ids_container');

    if (input && preview) {
        input.addEventListener('change', function () {
            preview.innerHTML = '';
            Array.from(this.files || []).forEach(function (file) {
                if (!file.type.startsWith('image/')) return;
                const wrap = document.createElement('div');
                wrap.style.width = '100px';
                const img = document.createElement('img');
                img.style.cssText = 'width:100px;height:100px;object-fit:cover;border:1px dashed #d1d0d4;border-radius:5px;padding:4px;';
                img.src = URL.createObjectURL(file);
                wrap.appendChild(img);
                preview.appendChild(wrap);
            });
        });
    }

    document.querySelectorAll('.remove-existing-gallery').forEach(function (btn) {
        btn.addEventListener('click', function () {
            const item = this.closest('.existing-gallery-item');
            if (!item || !removeContainer) return;
            const id = item.getAttribute('data-id');
            const hidden = document.createElement('input');
            hidden.type = 'hidden';
            hidden.name = 'remove_gallery_ids[]';
            hidden.value = id;
            removeContainer.appendChild(hidden);
            item.remove();
        });
    });

    const uploadUrl = @json(route('item.editor-image'));
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

    function ItemEditorUploadAdapter(loader) {
        this.loader = loader;
    }

    ItemEditorUploadAdapter.prototype.upload = function () {
        return this.loader.file.then(function (file) {
            return new Promise(function (resolve, reject) {
                const data = new FormData();
                data.append('upload', file);

                fetch(uploadUrl, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': csrfToken,
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: data,
                    credentials: 'same-origin'
                }).then(function (response) {
                    return response.json().then(function (payload) {
                        if (!response.ok) {
                            const message = payload?.message
                                || payload?.errors?.upload?.[0]
                                || @json(__('Image upload failed.'));
                            throw new Error(message);
                        }
                        if (!payload.url) {
                            throw new Error(@json(__('Image upload failed.')));
                        }
                        resolve({ default: payload.url });
                    });
                }).catch(function (error) {
                    reject(error?.message || @json(__('Image upload failed.')));
                });
            });
        });
    };

    ItemEditorUploadAdapter.prototype.abort = function () {};

    function ItemEditorUploadAdapterPlugin(editor) {
        editor.plugins.get('FileRepository').createUploadAdapter = function (loader) {
            return new ItemEditorUploadAdapter(loader);
        };
    }

    const host = document.getElementById('description_editor');
    const textarea = document.getElementById('description');
    if (!host || !textarea || typeof ClassicEditor === 'undefined') {
        return;
    }

    ClassicEditor.create(host, {
        extraPlugins: [ItemEditorUploadAdapterPlugin],
        toolbar: {
            items: [
                'heading', '|',
                'bold', 'italic', 'link', '|',
                'bulletedList', 'numberedList', 'blockQuote', '|',
                'uploadImage', 'undo', 'redo'
            ]
        }
    }).then(function (editor) {
        editor.setData(textarea.value || '');
        editor.model.document.on('change:data', function () {
            textarea.value = editor.getData();
        });

        const form = textarea.closest('form');
        if (form) {
            form.addEventListener('submit', function () {
                textarea.value = editor.getData();
            });
        }

        window.itemDescriptionEditor = editor;
    }).catch(function (error) {
        console.error('CKEditor init failed:', error);
    });
})();
</script>
@endpush

