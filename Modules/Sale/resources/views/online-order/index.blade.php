@extends('backend.backend_layout')
@section('page-title', __('Online Order'))
@push('page-css')
<link rel="stylesheet" href="{{ asset('public/backend_assets/vendor/libs/sweetalert2/sweetalert2.css') }}" />
@endpush
@section('page-content')
<div class="container-xxl flex-grow-1 container-p-y">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-6 row-gap-4">
        <div class="d-flex flex-column justify-content-center">
            <h4 class="mb-0">{{ __('Online Order') }}</h4>
        </div>
        @include('backend.components.breadcrumb', [
            'breadcrumbs' => [
                [
                    'label' => '<i class="ti tabler-home"></i>',
                    'link' => route('dashboard')
                ],
                [
                    'label' => __('Sale'),
                    'link' => '#'
                ],
                [
                    'label' => __('Online Order'),
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

    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-datatable table-responsive pt-0">
                    <table class="datatables-basic table">
                        <thead>
                            <tr>
                                <th>{{ __('SN') }}</th>
                                <th>{{ __('Order No') }}</th>
                                <th>{{ __('Customer') }}</th>
                                <th>{{ __('Date') }}</th>
                                <th>{{ __('Total') }}</th>
                                <th>{{ __('Order Status') }}</th>
                                <th>{{ __('Payment Status') }}</th>
                                <th>{{ __('Action') }}</th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
@push('page-js')
@routes
<script src="{{ asset('public/backend_assets/vendor/libs/sweetalert2/sweetalert2.js')}}"></script>
<script src="{{ asset('public/backend_assets/js/extended-ui-sweetalert2.js')}}"></script>
<script src="{{ asset('public/backend_assets/js/sale/list_online_orders.js') }}?v={{ @filemtime(public_path('backend_assets/js/sale/list_online_orders.js')) ?: time() }}"></script>
<script src="{{ asset('public/backend_assets/js/pages_list_js/delete_confirmation.js') }}?v={{ @filemtime(public_path('backend_assets/js/pages_list_js/delete_confirmation.js')) ?: time() }}"></script>
@endpush
