@extends('backend.backend_layout')
@section('page-title', $sale->exists ? __('Update') . ' ' . __('Offer') : __('Add') . ' ' . __('Offer'))
@section('page-content')
@php
    $initialItems = $initialItems ?? [];
    $media = app(\Modules\Ecommerce\Services\EcommerceMediaService::class);
@endphp
<div class="container-xxl flex-grow-1 container-p-y">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-6 row-gap-4">
        <div class="d-flex flex-column justify-content-center">
            <h4 class="mb-0">{{ $sale->exists ? __('Update') . ' ' . __('Offer') : __('Add') . ' ' . __('Offer') }}</h4>
        </div>
        @include('backend.components.breadcrumb', [
            'breadcrumbs' => [
                ['label' => '<i class="ti tabler-home"></i>', 'link' => route('dashboard')],
                ['label' => __('Ecommerce CMS'), 'link' => route('ecommerce.admin.flash-sales.index')],
                ['label' => $sale->exists ? __('Update') . ' ' . __('Offer') : __('Add') . ' ' . __('Offer'), 'active' => true],
            ]
        ])
    </div>

    @if(session('success'))
        {!! insertSuccess(session('success')) !!}
    @endif
    @if(session('error'))
        {!! insertFailed(session('error')) !!}
    @endif
    @if($errors->any())
        <div class="alert alert-danger alert-dismissible mb-4" role="alert">
            <h5 class="alert-heading mb-2">{{ __('Please fix the following errors:') }}</h5>
            <ul class="mb-0 ps-3">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="{{ __('Close') }}"></button>
        </div>
    @endif

    <div class="row">
        <div class="col-12">
            <form method="POST" enctype="multipart/form-data" novalidate action="{{ $sale->exists ? route('ecommerce.admin.flash-sales.update', $sale->id) : route('ecommerce.admin.flash-sales.store') }}" id="flashSaleForm">
                @csrf
                @if($sale->exists) @method('PUT') @endif
                <div class="card">
                    <div class="card-body">
                        <div class="row">
                            <div class="col-12 col-md-6 col-lg-4 mb-5">
                                <label class="form-label" for="title">{{ __('Offer Title') }} {!! requiredField() !!}</label>
                                <input type="text" name="title" id="title" class="form-control @error('title') is-invalid @enderror" value="{{ old('title', $sale->title) }}">
                                @error('title')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-12 col-md-6 col-lg-4 mb-5">
                                <label class="form-label" for="starts_at">{{ __('Start Date') }} {!! requiredField() !!}</label>
                                <input type="text" name="starts_at" id="starts_at" class="form-control datePicker @error('starts_at') is-invalid @enderror"
                                       placeholder="{{ __('Start Date') }}"
                                       value="{{ old('starts_at', optional($sale->starts_at)->format('Y-m-d')) }}">
                                @error('starts_at')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-12 col-md-6 col-lg-4 mb-5">
                                <label class="form-label" for="ends_at">{{ __('End Date') }} {!! requiredField() !!}</label>
                                <input type="text" name="ends_at" id="ends_at" class="form-control datePicker @error('ends_at') is-invalid @enderror"
                                       placeholder="{{ __('End Date') }}"
                                       value="{{ old('ends_at', optional($sale->ends_at)->format('Y-m-d')) }}">
                                <small class="text-muted">{{ __('Countdown on the homepage runs until the end of this day.') }}</small>
                                @error('ends_at')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-12 col-md-6 col-lg-4 mb-5">
                                <label class="form-label" for="status">{{ __('Status') }} {!! requiredField() !!}</label>
                                <select name="status" id="status" class="form-select select2 @error('status') is-invalid @enderror">
                                    <option value="Active" @selected(old('status', $sale->status ?? 'Active') === 'Active')>{{ __('Active') }}</option>
                                    <option value="Inactive" @selected(old('status', $sale->status ?? '') === 'Inactive')>{{ __('Inactive') }}</option>
                                </select>
                                @error('status')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-12 col-md-6 col-lg-4 mb-5">
                                <label class="form-label" for="discount_type">{{ __('Discount Type') }} {!! requiredField() !!}</label>
                                <select name="discount_type" id="discount_type" class="form-select select2 @error('discount_type') is-invalid @enderror">
                                    <option value="custom" @selected(old('discount_type', $sale->discount_type ?? 'custom') === 'custom')>{{ __('Custom per product') }}</option>
                                    <option value="percent" @selected(old('discount_type', $sale->discount_type ?? '') === 'percent')>{{ __('Percentage off all') }}</option>
                                    <option value="fixed" @selected(old('discount_type', $sale->discount_type ?? '') === 'fixed')>{{ __('Fixed amount off all') }}</option>
                                </select>
                                <small class="text-muted">{{ __('Per-product offer price overrides this when set.') }}</small>
                                @error('discount_type')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-12 col-md-6 col-lg-4 mb-5">
                                <label class="form-label" for="discount_value">{{ __('Discount Value') }}</label>
                                <input type="number" step="0.001" min="0" name="discount_value" id="discount_value" class="form-control @error('discount_value') is-invalid @enderror"
                                       value="{{ old('discount_value', $sale->discount_value) }}"
                                       placeholder="{{ __('e.g. 10 for 10% or amount') }}">
                                @error('discount_value')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-12 col-md-6 col-lg-4 mb-5">
                                <label class="form-label" for="banner">{{ __('Offer Banner') }} <span class="text-muted">({{ __('Optional') }})</span></label>
                                <input type="file" name="banner" id="banner" class="form-control @error('banner') is-invalid @enderror" accept="image/jpeg,image/png,image/webp,image/gif,.jpg,.jpeg,.png,.webp,.gif">
                                <small class="text-muted d-block mt-1">{{ __('Recommended size: 1690px × 300px') }}</small>
                                <small class="text-muted d-block">{{ __('Max file size: 700KB. Large image shown at the top of the offer details page.') }}</small>
                                @error('banner')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                                @if($sale->banner)
                                    <div class="mt-2 d-flex align-items-center gap-3">
                                        <img src="{{ $media->url($sale->banner) }}" alt="" style="max-height:60px;">
                                        <label class="form-check mb-0">
                                            <input type="checkbox" class="form-check-input" name="remove_banner" value="1" @checked(old('remove_banner'))>
                                            <span class="form-check-label">{{ __('Remove Banner') }}</span>
                                        </label>
                                    </div>
                                @endif
                            </div>
                            <div class="col-12 col-md-6 col-lg-4 mb-5">
                                <label class="form-label" for="thumbnail">{{ __('Offer Thumbnail') }} <span class="text-muted">({{ __('Optional') }})</span></label>
                                <input type="file" name="thumbnail" id="thumbnail" class="form-control @error('thumbnail') is-invalid @enderror" accept="image/jpeg,image/png,image/webp,image/gif,.jpg,.jpeg,.png,.webp,.gif">
                                <small class="text-muted d-block mt-1">{{ __('Recommended size: 390px × 360px') }}</small>
                                <small class="text-muted d-block">{{ __('Max file size: 500KB. Card image shown on the offers listing page.') }}</small>
                                @error('thumbnail')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                                @if($sale->thumbnail)
                                    <div class="mt-2 d-flex align-items-center gap-3">
                                        <img src="{{ $media->url($sale->thumbnail) }}" alt="" style="max-height:60px;">
                                        <label class="form-check mb-0">
                                            <input type="checkbox" class="form-check-input" name="remove_thumbnail" value="1" @checked(old('remove_thumbnail'))>
                                            <span class="form-check-label">{{ __('Remove Thumbnail') }}</span>
                                        </label>
                                    </div>
                                @endif
                            </div>
                            <div class="col-12 mb-5">
                                <label class="form-label" for="description">{{ __('Description') }}</label>
                                <textarea name="description" id="description" rows="3" class="form-control" placeholder="{{ __('Optional offer details shown on homepage') }}">{{ old('description', $sale->description) }}</textarea>
                            </div>
                        </div>

                        <div class="row mb-4">
                            <div class="col-12 col-md-8 col-lg-6">
                                <label class="form-label" for="flash_sale_item_search">{{ __('Add Product') }}</label>
                                <div class="position-relative" id="flashSaleItemPicker">
                                    <input type="text"
                                           id="flash_sale_item_search"
                                           class="form-control"
                                           autocomplete="off"
                                           placeholder="{{ __('Search product by name or code...') }}"
                                           data-search-url="{{ route('ecommerce.admin.flash-sales.search-items') }}">
                                    <div id="flash_sale_item_results"
                                         class="dropdown-menu w-100 shadow show"
                                         style="display:none; max-height:280px; overflow:auto; position:absolute; inset:auto auto auto 0; z-index:1080;"></div>
                                </div>
                                <small class="text-muted">{{ __('Click the field to see products, or type to search. Select a product to add it to the cart.') }}</small>
                            </div>
                        </div>

                        <h6 class="mb-3">{{ __('Offer Products') }}</h6>
                        <div class="table-responsive border rounded">
                            <table class="table mb-0" id="flashSaleCartTable">
                                <thead>
                                    <tr>
                                        <th>{{ __('Product') }}</th>
                                        <th style="width:160px;">{{ __('Regular Price') }}</th>
                                        <th style="width:180px;">{{ __('Offer Price') }}</th>
                                        <th style="width:80px;" class="text-end">{{ __('Action') }}</th>
                                    </tr>
                                </thead>
                                <tbody id="flashSaleCartBody">
                                    @forelse($cartItems as $item)
                                        <tr data-item-id="{{ $item['id'] }}">
                                            <td>
                                                <input type="hidden" name="item_ids[]" value="{{ $item['id'] }}">
                                                <span class="fw-medium">{{ $item['name'] }}</span>
                                                @if(!empty($item['code']))
                                                    <small class="text-muted">({{ $item['code'] }})</small>
                                                @endif
                                            </td>
                                            <td>{{ $item['sale_price_formatted'] }}</td>
                                            <td>
                                                <input type="number" step="0.001" min="0" class="form-control form-control-sm"
                                                       name="flash_prices[{{ $item['id'] }}]" value="{{ $item['flash_price'] }}"
                                                       placeholder="{{ __('Optional') }}">
                                            </td>
                                            <td class="text-end">
                                                <button type="button" class="btn btn-sm btn-icon btn-outline-danger remove-flash-cart-item" title="{{ __('Remove') }}">
                                                    <i class="ti tabler-trash"></i>
                                                </button>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr class="flash-cart-empty">
                                            <td colspan="4" class="text-center text-muted py-4">{{ __('No products added yet.') }}</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <div class="card-footer">
                        <div class="d-flex">
                            <button type="submit" class="btn btn-primary waves-effect waves-light me-4">
                                {!! submitIconWithText($sale->exists ? $sale : '') !!}
                            </button>
                            <a href="{{ route('ecommerce.admin.flash-sales.index') }}" class="btn btn-primary waves-effect waves-light">
                                {!! backIconWithText() !!}
                            </a>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
@push('page-js')
<script>
window.flashSaleInitialItems = @json($initialItems);
window.flashSaleLabels = {
    optional: @json(__('Optional')),
    empty: @json(__('No products added yet.')),
    noResults: @json(__('No products found')),
    searching: @json(__('Searching…'))
};
</script>
<script src="{{ asset('public/backend_assets/js/pages_js/ecommerce_flash_sale_form.js') }}?v={{ @filemtime(public_path('backend_assets/js/pages_js/ecommerce_flash_sale_form.js')) ?: time() }}"></script>
@endpush
