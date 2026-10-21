@extends('backend.backend_layout')
@section('page-title', __('List') . ' ' . __('Category Section'))
@push('page-css')
<link rel="stylesheet" href="{{ asset('public/backend_assets/vendor/libs/sweetalert2/sweetalert2.css') }}" />
@include('ecommerce::admin.partials.sortable-list-styles')
@endpush
@section('page-content')
<div class="container-xxl flex-grow-1 container-p-y">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-6 row-gap-4">
        <div class="d-flex flex-column justify-content-center">
            <h4 class="mb-0">{{ __('List') }} {{ __('Category Section') }}</h4>
        </div>
        @include('backend.components.breadcrumb', [
            'breadcrumbs' => [
                ['label' => '<i class="ti tabler-home"></i>', 'link' => route('dashboard')],
                ['label' => __('Ecommerce CMS'), 'link' => route('ecommerce.admin.branding.edit')],
                ['label' => __('List') . ' ' . __('Category Section'), 'active' => true],
            ]
        ])
    </div>

    @if(session('success'))
        {!! insertSuccess(session('success')) !!}
    @endif
    @if(session('error'))
        {!! insertFailed(session('error')) !!}
    @endif

    <div class="row">
        <div class="col-12">
            @include('ecommerce::admin.partials.sortable-cms-list', [
                'items' => $items,
                'listTitle' => __('Category Section'),
                'createUrl' => route('ecommerce.admin.categories.create'),
                'createLabel' => __('Add Category'),
                'sortUrl' => route('ecommerce.admin.categories.sort-order'),
                'emptyMessage' => __('No categories found. Click Add Category to create one.'),
            ])
        </div>
    </div>
</div>
@endsection
@push('page-js')
@routes
<script src="{{ asset('public/backend_assets/vendor/libs/sweetalert2/sweetalert2.js')}}"></script>
<script src="{{ asset('public/backend_assets/js/extended-ui-sweetalert2.js')}}"></script>
<script src="{{ asset('public/backend_assets/vendor/libs/sortablejs/sortable.js') }}"></script>
<script src="{{ asset('public/backend_assets/js/pages_js/ecommerce_sortable_list.js') }}?v={{ @filemtime(public_path('backend_assets/js/pages_js/ecommerce_sortable_list.js')) ?: time() }}"></script>
<script src="{{ asset('public/backend_assets/js/pages_list_js/delete_confirmation.js')}}?v={{ @filemtime(public_path('backend_assets/js/pages_list_js/delete_confirmation.js')) ?: time() }}"></script>
@endpush
