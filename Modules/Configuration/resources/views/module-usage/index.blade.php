@extends('backend.backend_layout')
@section('page-title', __('Modules Usages'))
@section('page-content')
<div class="container-xxl flex-grow-1 container-p-y">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-6 row-gap-4">
        <div class="d-flex flex-column justify-content-center">
            <h4 class="mb-0">{{ __('Modules Usages') }}</h4>
            <p class="mb-0 text-muted">{{ __('Enable only the unique modules your business needs.') }}</p>
            @if(!empty($isCompanyScoped))
                <p class="mb-0 text-muted small">{{ __('These settings apply only to your company.') }}</p>
            @endif
        </div>
        @include('backend.components.breadcrumb', [
            'breadcrumbs' => [
                [
                    'label' => '<i class="ti tabler-home"></i>',
                    'link' => '#'
                ],
                [
                    'label' => __('Settings'),
                    'link' => '#'
                ],
                [
                    'label' => __('Modules Usages'),
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
            <form action="{{ route('module-usage.update') }}" method="POST">
                @csrf
                @method('PUT')
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">{{ __('Available Modules') }}</h5>
                        @if(!empty($businessTypeLabel))
                            <p class="mb-0 mt-2 text-muted small">
                                {{ __('Business Type') }}: <strong>{{ $businessTypeLabel }}</strong>
                                @if(!empty($productTypes))
                                    — {{ __('Product Types') }}: {{ implode(', ', $productTypes) }}
                                @endif
                            </p>
                        @endif
                    </div>
                    <div class="card-body">
                        <div class="row">
                            @foreach($definitions as $key => $definition)
                                @if(lp_is_bd() && in_array($key, ['zatca', 'gst', 'multiple_currency', 'denomination', 'booking', 'fixed_assets'], true))
                                    @continue
                                @endif
                                <div class="col-12 col-md-6 col-lg-4 mb-4">
                                    <input type="hidden" name="{{ $key }}" value="0">
                                    <div class="form-check form-switch">
                                        <input
                                            class="form-check-input"
                                            type="checkbox"
                                            role="switch"
                                            name="{{ $key }}"
                                            id="module_{{ $key }}"
                                            value="1"
                                            @checked(old($key, $modules[$key] ?? false))
                                        >
                                        <label class="form-check-label fw-medium" for="module_{{ $key }}">
                                            {{ __($definition['label']) }}
                                        </label>
                                    </div>
                                    <p class="text-muted small mb-0 mt-1 ms-1">
                                        {{ __($definition['type']) }}
                                        @if(!empty($definition['description']))
                                            — {{ __($definition['description']) }}
                                        @endif
                                    </p>
                                </div>
                            @endforeach
                        </div>
                    </div>
                    <div class="card-footer d-flex justify-content-end gap-2">
                        <button type="submit" class="btn btn-primary">
                            <i class="ti tabler-device-floppy me-1"></i>{{ __('Save') }}
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
