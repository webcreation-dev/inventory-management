@extends('backend.backend_layout')
@section('page-title', __('Contact'))
@push('page-css')
<link rel="stylesheet" href="{{ asset('public/backend_assets/vendor/libs/sweetalert2/sweetalert2.css') }}" />
@endpush
@section('page-content')
<div class="container-xxl flex-grow-1 container-p-y">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-6 row-gap-4">
        <div class="d-flex flex-column justify-content-center">
            <h4 class="mb-0">{{ __('List') }} {{ __('Contact') }}</h4>
        </div>
        @include('backend.components.breadcrumb', [
            'breadcrumbs' => [
                ['label' => '<i class="ti tabler-home"></i>', 'link' => route('dashboard')],
                ['label' => __('Ecommerce CMS'), 'link' => route('ecommerce.admin.branding.edit')],
                ['label' => __('List') . ' ' . __('Contact'), 'active' => true],
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
                    <table class="datatables-ecommerce-contacts table">
                        <thead>
                            <tr>
                                <th>{{ __('SN') }}</th>
                                <th>{{ __('Name') }}</th>
                                <th>{{ __('Email') }}</th>
                                <th>{{ __('Subject') }}</th>
                                <th>{{ __('Message') }}</th>
                                <th>{{ __('Received') }}</th>
                                <th>{{ __('Status') }}</th>
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

<div class="modal fade" id="replyContactModal" tabindex="-1" aria-labelledby="replyContactModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="replyContactModalLabel">{{ __('Reply to contact') }}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('Close') }}"></button>
            </div>
            <form method="post" action="" id="replyContactForm">
                @csrf
                <div class="modal-body">
                    <p class="small text-muted mb-3">
                        <span class="fw-semibold text-body">{{ __('To') }}:</span>
                        <span id="replyContactToEmail"></span>
                    </p>
                    <div class="mb-3">
                        <label class="form-label" for="reply_subject">{{ __('Subject') }}</label>
                        <input type="text" class="form-control" name="subject" id="reply_subject" required maxlength="255">
                    </div>
                    <div class="mb-0">
                        <label class="form-label" for="reply_message">{{ __('Message') }}</label>
                        <textarea class="form-control" name="message" id="reply_message" rows="6" required maxlength="5000"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-label-secondary" data-bs-dismiss="modal">{{ __('Cancel') }}</button>
                    <button type="submit" class="btn btn-primary">{{ __('Send reply') }}</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
@push('page-js')
@routes
<script src="{{ asset('public/backend_assets/vendor/libs/sweetalert2/sweetalert2.js')}}"></script>
<script src="{{ asset('public/backend_assets/js/extended-ui-sweetalert2.js')}}"></script>
<script src="{{ asset('public/backend_assets/js/pages_list_js/list_ecommerce_contacts.js')}}?v={{ @filemtime(public_path('backend_assets/js/pages_list_js/list_ecommerce_contacts.js')) ?: time() }}"></script>
<script src="{{ asset('public/backend_assets/js/pages_list_js/delete_confirmation.js')}}?v={{ @filemtime(public_path('backend_assets/js/pages_list_js/delete_confirmation.js')) ?: time() }}"></script>
@endpush
