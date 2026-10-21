@extends('backend.backend_layout')
@section('page-title', __('Open') . ' ' . __('Register'))
@push('page-css')
<link rel="stylesheet" href="{{ asset('public/backend_assets/vendor/libs/flatpickr/flatpickr.css') }}" />
@endpush
@section('page-content')

<div class="container-xxl flex-grow-1 container-p-y">
    <!-- Open Register -->
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-6 row-gap-4">
        <div class="d-flex flex-column justify-content-center">
            <h4 class="mb-0">{{ __('Open') }} {{ __('Register') }}</h4>
        </div>
        @include('backend.components.breadcrumb', [
            'breadcrumbs' => [
                [
                    'label' => '<i class="ti tabler-home"></i>',
                    'link' => '#'
                ],
                [
                    'label' => __('Register'), 
                    'link' => '#'
                ],
                [
                    'label' => __('Open') . ' ' . __('Register'),
                    'active' => true
                ]
            ]
        ])
    </div>

    @if(session('error'))
        {!! insertFailed(session('error')) !!}
    @endif

    <div class="row">
        <div class="col-12">
            <form id="form_register_open">
                <div class="card">
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6 col-lg-4 mb-3">
                                <div class="d-flex justify-content-between">
                                    <div class="mb-5 w-100 validate_wrapper">
                                        <label class="form-label">{{ __('Counter') }} <span class="text-danger">*</span></label>
                                        <select class="form-control select2" id="register_counter_id" name="counter_id" required>
                                            <option value="">{{ __('Select') }} {{ __('Counter') }}</option>
                                            @foreach($counters as $counter)
                                                <option value="{{ $counter->id }}">{{ $counter->name }}</option>
                                            @endforeach
                                        </select>
                                        <div class="invalid-feedback"></div>
                                    </div>
                                    <button type="button" class="fw-medium btn btn-icon btn-label-primary ms-4 add-plus-btn"
                                        data-bs-toggle="modal" data-bs-target="#modal_register_counter"
                                        title="{{ __('Add') }} {{ __('Counter') }}">
                                        <i class="icon-base ti tabler-plus icon-md"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                        <div class="row" id="payment_methods_container">
                            @foreach($payment_methods as $method)
                            <div class="col-md-6 col-lg-4 mb-3">
                                <label class="form-label">{{ $method->name }}</label>
                                <input 
                                    type="text" 
                                    class="form-control number-input payment-method-balance" 
                                    data-payment-method-id="{{ $method->id }}"
                                    data-payment-method-name="{{ $method->name }}"
                                    placeholder="0.00"
                                    value="0"
                                >
                            </div>
                            @endforeach
                        </div>
                    </div>
                    <div class="card-footer">
                        <div class="d-flex">
                            <button type="submit" class="btn btn-primary waves-effect waves-light me-4" id="btn_open_register">
                                {!! submitIconWithText(isset($promotion) ? $promotion : '') !!}
                            </button>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- Quick add Counter (same fields as Configuration > Counter create) --}}
<div class="modal fade" id="modal_register_counter" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-xl" role="document">
        <div class="modal-content">
            <form id="form_register_counter_modal">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title">{{ __('Add') }} {{ __('Counter') }}</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-12 col-md-6">
                            <div class="mb-4 validate_wrapper">
                                <label class="form-label" for="rc_modal_name">{{ __('Name') }} {!! requiredField() !!}</label>
                                <input type="text" class="form-control" name="name" id="rc_modal_name"
                                    placeholder="{{ __('Name') }}" />
                            </div>
                        </div>
                        <div class="col-12 col-md-6">
                            <div class="mb-4 validate_wrapper">
                                <label class="form-label" for="rc_modal_outlet_id">{{ __('Outlet') }} {!! requiredField() !!}</label>
                                <select class="form-select select2-rc-outlet" name="outlet_id" id="rc_modal_outlet_id"
                                    data-placeholder="{{ __('Select') }} {{ __('Outlet') }}">
                                    <option value="">{{ __('Select') }} {{ __('Outlet') }}</option>
                                    @foreach($outlets as $outlet)
                                        <option value="{{ $outlet->id }}" @selected((int) ($session_outlet_id ?? 0) === (int) $outlet->id)>
                                            {{ $outlet->outlet_name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="col-12 col-md-6">
                            <div class="d-flex justify-content-between align-items-start">
                                <div class="mb-4 validate_wrapper w-100">
                                    <label class="form-label" for="rc_modal_printer_id">{{ __('Printer') }}</label>
                                    <select class="form-select select2-rc-printer" name="printer_id" id="rc_modal_printer_id"
                                        data-placeholder="{{ __('Select') }} {{ __('Printer') }}">
                                        <option value="">{{ __('Select') }} {{ __('Printer') }}</option>
                                        @foreach($printers as $printer)
                                            <option value="{{ $printer->id }}">{{ $printer->title }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <button type="button" class="fw-medium btn btn-icon btn-label-primary ms-4 add-plus-btn waves-effect"
                                    id="btn_open_rc_printer_modal" title="{{ __('Add') }} {{ __('Printer') }}">
                                    <i class="icon-base ti tabler-plus icon-md"></i>
                                </button>
                            </div>
                        </div>
                        <div class="col-12 col-md-6">
                            <div class="mb-2 validate_wrapper">
                                <label class="form-label" for="rc_modal_description">{{ __('Description') }}</label>
                                <textarea class="form-control" name="description" id="rc_modal_description" rows="2"
                                    placeholder="{{ __('Description') }}"></textarea>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="submit" class="btn btn-primary" id="btn_register_counter_modal_save">
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

{{-- Printer quick add (opened from counter modal; matches counter/create printer modal) --}}
<div class="modal fade" id="modal_register_printer" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-xl" role="document">
        <div class="modal-content">
            <form id="form_register_printer_modal">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title">{{ __('Printer') }}</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-lg-4 col-md-6 col-12">
                            <div class="mb-4 validate_wrapper">
                                <label for="rc_printer_title" class="form-label">{{ __('Title') }} {!! requiredField() !!}</label>
                                <input type="text" id="rc_printer_title" name="title" class="form-control" placeholder="{{ __('Enter') }} {{ __('Title') }}" />
                            </div>
                        </div>
                        <div class="clear-fix"></div>
                        <div class="col-lg-4 col-md-6 col-12">
                            <div class="mb-4 validate_wrapper">
                                <label for="rc_printer_invoice_print" class="form-label">{{ __('Printing Choice') }} {!! requiredField() !!}</label>
                                <select class="form-select select2-rc-printer-field" name="invoice_print" id="rc_printer_invoice_print" data-placeholder="{{ __('Select') }} {{ __('Printing Choice') }}">
                                    <option value="">{{ __('Select') }} {{ __('Printing Choice') }}</option>
                                    <option value="web_browser">{{ __('Web Browser') }}</option>
                                    <option value="live_server_print">{{ __('Direct Print') }}</option>
                                    <option value="qz_tray_print">{{ __('QZ Tray Print') }}</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-lg-4 col-md-6 col-12 rc-live-server-fields d-none">
                            <div class="mb-4 validate_wrapper">
                                <label for="rc_printer_type" class="form-label">{{ __('Printer') }} {{ __('Type') }} {!! requiredField() !!}</label>
                                <select class="form-select select2-rc-printer-field" name="type" id="rc_printer_type" data-placeholder="{{ __('Select') }} {{ __('Printer') }} {{ __('Type') }}">
                                    <option value="">{{ __('Select') }} {{ __('Printer') }} {{ __('Type') }}</option>
                                    <option value="windows">{{ __('USB Printer') }}</option>
                                    <option value="network">{{ __('Network') }}</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-lg-4 col-md-6 col-12 rc-live-server-fields d-none">
                            <div class="mb-4 validate_wrapper">
                                <label for="rc_printer_characters_per_line" class="form-label">{{ __('Characters Per Line') }} {!! requiredField() !!}</label>
                                <input type="text" id="rc_printer_characters_per_line" name="characters_per_line" class="form-control" placeholder="{{ __('Enter') }} {{ __('Characters Per Line') }}" />
                            </div>
                        </div>
                        <div class="col-lg-4 col-md-6 col-12 rc-live-server-fields d-none">
                            <div class="mb-4 validate_wrapper">
                                <label for="rc_printer_path" class="form-label">{{ __('Share Name') }} {!! requiredField() !!}</label>
                                <input type="text" id="rc_printer_path" name="path" class="form-control" placeholder="{{ __('Enter') }} {{ __('Share Name') }}" />
                            </div>
                        </div>
                        <div class="col-lg-4 col-md-6 col-12 rc-live-server-fields d-none">
                            <div class="mb-4 validate_wrapper">
                                <label for="rc_printer_print_server_url_invoice" class="form-label">{{ __('IPV4 Address') }} {!! requiredField() !!}</label>
                                <input type="text" id="rc_printer_print_server_url_invoice" name="print_server_url_invoice" class="form-control" placeholder="{{ __('Enter') }} {{ __('IPV4 Address') }}" />
                            </div>
                        </div>
                        <div class="col-lg-4 col-md-6 col-12 rc-network-fields d-none">
                            <div class="mb-4 validate_wrapper">
                                <label for="rc_printer_ip_address" class="form-label">{{ __('Printer IP Address') }} {!! requiredField() !!}</label>
                                <input type="text" id="rc_printer_ip_address" name="printer_ip_address" class="form-control" placeholder="{{ __('Enter') }} {{ __('Printer IP Address') }}" />
                            </div>
                        </div>
                        <div class="col-lg-4 col-md-6 col-12 rc-network-fields d-none">
                            <div class="mb-4 validate_wrapper">
                                <label for="rc_printer_port" class="form-label">{{ __('Printer Port Address') }} {!! requiredField() !!}</label>
                                <input type="text" id="rc_printer_port" name="printer_port" class="form-control" placeholder="{{ __('Enter') }} {{ __('Printer Port Address') }}" />
                            </div>
                        </div>
                        @if(module_enabled('fiscal_printer'))
                        <div class="col-lg-4 col-md-6 col-12 mb-4 rc-network-fields d-none">
                            <div class="mb-4 validate_wrapper">
                                <label for="rc_printer_fiscal_printer_status" class="form-label">{{ __('Fiscal Printer Status') }} {!! requiredField() !!}</label>
                                <select class="form-select select2-rc-printer-field" name="fiscal_printer_status" id="rc_printer_fiscal_printer_status" data-placeholder="{{ __('Select') }} {{ __('Fiscal Printer Status') }}">
                                    <option value="">{{ __('Select') }} {{ __('Fiscal Printer Status') }}</option>
                                    <option value="ON">{{ __('ON') }}</option>
                                    <option value="OFF">{{ __('OFF') }}</option>
                                </select>
                            </div>
                        </div>
                        @else
                        <input type="hidden" name="fiscal_printer_status" value="OFF">
                        @endif
                        <div class="col-lg-4 col-md-6 col-12 mb-4 rc-network-fields d-none">
                            <div class="mb-4 validate_wrapper">
                                <label for="rc_printer_open_cash_drawer" class="form-label">{{ __('Open Cash Drawer') }} {!! requiredField() !!}</label>
                                <select class="form-select select2-rc-printer-field" name="open_cash_drawer_when_printing_invoice" id="rc_printer_open_cash_drawer" data-placeholder="{{ __('Select') }} {{ __('Open Cash Drawer') }}">
                                    <option value="">{{ __('Select') }} {{ __('Open Cash Drawer') }}</option>
                                    <option value="ON">{{ __('ON') }}</option>
                                    <option value="OFF">{{ __('OFF') }}</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="submit" class="btn btn-primary" id="btn_register_printer_modal_save">
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

@endsection

@push('page-js')
@routes
<script src="{{ asset('public/backend_assets/vendor/libs/flatpickr/flatpickr.js') }}"></script>
<script src="{{ asset('public/backend_assets/js/custom_js/register.js') }}"></script>
<script>
(function ($) {
    'use strict';

    const counterModal = $('#modal_register_counter');
    const counterForm = $('#form_register_counter_modal');
    const printerModal = $('#modal_register_printer');
    const printerForm = $('#form_register_printer_modal');
    const registerCounterSelect = $('#register_counter_id');
    const rcOutletSelect = $('#rc_modal_outlet_id');
    const rcPrinterSelect = $('#rc_modal_printer_id');
    const rcInvoicePrint = $('#rc_printer_invoice_print');
    const rcPrinterType = $('#rc_printer_type');
    const rcFiscal = $('#rc_printer_fiscal_printer_status');
    const rcCashDrawer = $('#rc_printer_open_cash_drawer');

    const counterStoreUrl = "{{ route('counter.store') }}";
    const printerStoreUrl = "{{ route('printer.store') }}";
    const printerPlaceholder = "{{ __('Select') }} {{ __('Printer') }}";

    function clearRcValidation($form) {
        $form.find('.is-invalid').removeClass('is-invalid');
        $form.find('.invalid-feedback').remove();
    }

    function refreshRegisterCounterOptions(counters, selectedId) {
        registerCounterSelect.empty();
        registerCounterSelect.append('<option value="">{{ __('Select') }} {{ __('Counter') }}</option>');
        (counters || []).forEach(function (c) {
            const opt = $('<option></option>').val(c.id).text(c.name);
            if (selectedId && Number(c.id) === Number(selectedId)) {
                opt.prop('selected', true);
            }
            registerCounterSelect.append(opt);
        });
        if (registerCounterSelect.hasClass('select2-hidden-accessible')) {
            registerCounterSelect.select2('destroy');
        }
        registerCounterSelect.select2({
            dropdownParent: registerCounterSelect.closest('.card-body, body'),
            width: '100%'
        });
    }

    function refreshRcPrinterOptions(printers, selectedId) {
        rcPrinterSelect.empty();
        rcPrinterSelect.append('<option value="">' + printerPlaceholder + '</option>');
        (printers || []).forEach(function (p) {
            const opt = $('<option></option>').val(p.id).text(p.title);
            if (selectedId && Number(p.id) === Number(selectedId)) {
                opt.prop('selected', true);
            }
            rcPrinterSelect.append(opt);
        });
        if (rcPrinterSelect.hasClass('select2-hidden-accessible')) {
            rcPrinterSelect.select2('destroy');
        }
        rcPrinterSelect.select2({
            dropdownParent: counterModal,
            width: '100%',
            placeholder: printerPlaceholder
        });
    }

    function toggleRcPrinterFields() {
        const v = rcInvoicePrint.val();
        if (v === 'live_server_print') {
            printerModal.find('.rc-live-server-fields').removeClass('d-none');
            toggleRcNetworkFields();
        } else {
            printerModal.find('.rc-live-server-fields, .rc-network-fields').addClass('d-none');
        }
    }

    function toggleRcNetworkFields() {
        if (rcPrinterType.val() === 'network') {
            printerModal.find('.rc-network-fields').removeClass('d-none');
        } else {
            printerModal.find('.rc-network-fields').addClass('d-none');
        }
    }

    function destroySelect2IfAny($el) {
        if ($el.length && $el.hasClass('select2-hidden-accessible')) {
            $el.select2('destroy');
        }
    }

    /**
     * Nested modals: printer sits above counter; without z-index stacking the new backdrop covers the counter modal.
     */
    function stackRcNestedModalZIndex() {
        const backdrops = document.querySelectorAll('.modal-backdrop');
        if (backdrops.length >= 2 && counterModal[0] && printerModal[0]) {
            backdrops[0].style.zIndex = '1040';
            counterModal[0].style.zIndex = '1055';
            backdrops[1].style.zIndex = '1060';
            printerModal[0].style.zIndex = '1065';
        }
    }

    function clearRcNestedModalZIndex() {
        counterModal.css('z-index', '');
        printerModal.css('z-index', '');
        document.querySelectorAll('.modal-backdrop').forEach(function (el) {
            el.style.removeProperty('z-index');
        });
    }

    // Do not dismiss counter modal while printer modal is open (Bootstrap may try to hide the parent).
    counterModal.on('hide.bs.modal', function (e) {
        if (printerModal.hasClass('show')) {
            e.preventDefault();
        }
    });

    // Open printer on top without toggling the counter modal (avoid data-bs-toggle on nested buttons).
    $('#btn_open_rc_printer_modal').on('click', function (e) {
        e.preventDefault();
        e.stopPropagation();
        const el = document.getElementById('modal_register_printer');
        if (typeof bootstrap !== 'undefined' && bootstrap.Modal && el) {
            bootstrap.Modal.getOrCreateInstance(el).show();
        } else {
            printerModal.modal('show');
        }
    });

    counterModal.on('shown.bs.modal', function () {
        if (!rcOutletSelect.hasClass('select2-hidden-accessible')) {
            rcOutletSelect.select2({
                dropdownParent: counterModal,
                placeholder: "{{ __('Select') }} {{ __('Outlet') }}"
            });
        }
        if (!rcPrinterSelect.hasClass('select2-hidden-accessible')) {
            rcPrinterSelect.select2({
                dropdownParent: counterModal,
                width: '100%',
                placeholder: printerPlaceholder
            });
        }
    });

    counterModal.on('hidden.bs.modal', function () {
        destroySelect2IfAny(rcOutletSelect);
        destroySelect2IfAny(rcPrinterSelect);
        counterForm[0].reset();
        clearRcValidation(counterForm);
        @if(!empty($session_outlet_id))
        rcOutletSelect.val('{{ $session_outlet_id }}').trigger('change');
        @endif
    });

    printerModal.on('shown.bs.modal', function () {
        stackRcNestedModalZIndex();
        setTimeout(stackRcNestedModalZIndex, 10);

        if (!rcInvoicePrint.hasClass('select2-hidden-accessible')) {
            rcInvoicePrint.select2({
                dropdownParent: printerModal,
                placeholder: "{{ __('Select') }} {{ __('Printing Choice') }}"
            }).on('change', toggleRcPrinterFields);
        }
        if (!rcPrinterType.hasClass('select2-hidden-accessible')) {
            rcPrinterType.select2({
                dropdownParent: printerModal,
                placeholder: "{{ __('Select') }} {{ __('Printer') }} {{ __('Type') }}"
            }).on('change', toggleRcNetworkFields);
        }
        if (rcFiscal.length && !rcFiscal.hasClass('select2-hidden-accessible')) {
            rcFiscal.select2({
                dropdownParent: printerModal,
                placeholder: "{{ __('Select') }} {{ __('Fiscal Printer Status') }}"
            });
        }
        if (!rcCashDrawer.hasClass('select2-hidden-accessible')) {
            rcCashDrawer.select2({
                dropdownParent: printerModal,
                placeholder: "{{ __('Select') }} {{ __('Open Cash Drawer') }}"
            });
        }
        toggleRcPrinterFields();
    });

    printerModal.on('hidden.bs.modal', function () {
        clearRcNestedModalZIndex();
        // BS may drop modal-open from body when the stacked modal closes; restore if counter is still open.
        if (counterModal.hasClass('show')) {
            $('body').addClass('modal-open');
        }

        destroySelect2IfAny(rcInvoicePrint);
        destroySelect2IfAny(rcPrinterType);
        destroySelect2IfAny(rcFiscal);
        destroySelect2IfAny(rcCashDrawer);
        rcInvoicePrint.off('change');
        rcPrinterType.off('change');
        printerForm[0].reset();
        clearRcValidation(printerForm);
        printerModal.find('.rc-live-server-fields, .rc-network-fields').addClass('d-none');
    });

    counterForm.on('submit', function (e) {
        e.preventDefault();
        clearRcValidation(counterForm);
        const $btn = $('#btn_register_counter_modal_save');
        const orig = $btn.html();
        $btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-2"></span>');

        $.ajax({
            type: 'POST',
            url: counterStoreUrl,
            data: counterForm.serialize(),
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content'),
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json'
            },
            success: function (response) {
                if (response && response.status === 'success' && response.data && response.data.counters) {
                    const selId = response.data.counter ? response.data.counter.id : null;
                    refreshRegisterCounterOptions(response.data.counters, selId);
                    counterForm[0].reset();
                    clearRcValidation(counterForm);
                    counterModal.modal('hide');
                    if (typeof showSuccessNotification === 'function') {
                        showSuccessNotification(response.message || '{{ __('Counter Created Successfully') }}');
                    }
                } else if (typeof showErrorNotification === 'function') {
                    showErrorNotification(response.message || 'Something went wrong');
                }
            },
            error: function (xhr) {
                if (xhr.status === 422 && xhr.responseJSON && xhr.responseJSON.errors) {
                    const errors = xhr.responseJSON.errors;
                    Object.keys(errors).forEach(function (field) {
                        const input = counterForm.find('[name="' + field + '"]');
                        const formGroup = input.closest('.validate_wrapper');
                        input.addClass('is-invalid');
                        formGroup.append('<div class="invalid-feedback">' + errors[field][0] + '</div>');
                    });
                } else if (typeof showErrorNotification === 'function') {
                    showErrorNotification((xhr.responseJSON && xhr.responseJSON.message) ? xhr.responseJSON.message : 'An unexpected error occurred');
                }
            },
            complete: function () {
                $btn.prop('disabled', false).html(orig);
            }
        });
    });

    printerForm.on('submit', function (e) {
        e.preventDefault();
        clearRcValidation(printerForm);

        $.ajax({
            type: 'POST',
            url: printerStoreUrl,
            data: printerForm.serialize(),
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content'),
                'X-Requested-With': 'XMLHttpRequest'
            },
            success: function (response) {
                if (response && response.status === 'success' && response.data && response.data.printers) {
                    const selId = response.data.printer ? response.data.printer.id : null;
                    refreshRcPrinterOptions(response.data.printers, selId);
                    printerForm[0].reset();
                    printerModal.modal('hide');
                    if (typeof showSuccessNotification === 'function') {
                        showSuccessNotification(response.message || 'Printer added successfully');
                    }
                } else if (typeof showErrorNotification === 'function') {
                    showErrorNotification(response.message || 'Something went wrong');
                }
            },
            error: function (xhr) {
                if (xhr.status === 422 && xhr.responseJSON && xhr.responseJSON.errors) {
                    const errors = xhr.responseJSON.errors;
                    Object.keys(errors).forEach(function (field) {
                        const input = printerForm.find('[name="' + field + '"]');
                        const formGroup = input.closest('.validate_wrapper');
                        input.addClass('is-invalid');
                        formGroup.append('<div class="invalid-feedback">' + errors[field][0] + '</div>');
                    });
                } else if (typeof showErrorNotification === 'function') {
                    showErrorNotification('An unexpected error occurred');
                }
            }
        });
    });
})(jQuery);
</script>
@endpush
