@extends('backend.backend_layout')
@section('page-title', __('Branding'))
@section('page-content')
@php
    $media = app(\Modules\Ecommerce\Services\EcommerceMediaService::class);
@endphp
<div class="container-xxl flex-grow-1 container-p-y">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-6 row-gap-4">
        <div class="d-flex flex-column justify-content-center">
            <h4 class="mb-0">{{ __('Branding') }}</h4>
        </div>
        @include('backend.components.breadcrumb', [
            'breadcrumbs' => [
                ['label' => '<i class="ti tabler-home"></i>', 'link' => route('dashboard')],
                ['label' => __('Ecommerce CMS'), 'link' => route('ecommerce.admin.branding.edit')],
                ['label' => __('Branding'), 'active' => true],
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
            <form method="POST" action="{{ route('ecommerce.admin.branding.update') }}" enctype="multipart/form-data" novalidate>
                @csrf
                @method('PUT')
                <div class="card">
                    <div class="card-body">
                        <div class="row">
                            <div class="col-12 col-md-6 col-lg-4 mb-5">
                                <label class="form-label" for="site_name">{{ __('Site Name') }} {!! requiredField() !!}</label>
                                <input type="text" name="site_name" id="site_name" class="form-control @error('site_name') is-invalid @enderror" value="{{ old('site_name', $setting->site_name) }}">
                                @error('site_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-12 col-md-6 col-lg-4 mb-5">
                                @php
                                    $brandColor = old('brand_color', $setting->brand_color ?: '#0d9488');
                                    if ($brandColor && $brandColor[0] !== '#') {
                                        $brandColor = '#' . $brandColor;
                                    }
                                @endphp
                                <label class="form-label" for="brand_color">{{ __('Brand Color') }} {!! requiredField() !!}</label>
                                <div class="d-flex align-items-center gap-3">
                                    <input type="color" id="brand_color_picker" value="{{ strlen($brandColor) === 7 ? $brandColor : '#0d9488' }}" class="form-control form-control-color" style="width:52px;height:42px;padding:4px;">
                                    <input type="text" name="brand_color" id="brand_color" class="form-control @error('brand_color') is-invalid @enderror"
                                           value="{{ $brandColor }}" placeholder="#0d9488" maxlength="7">
                                </div>
                                <small class="text-muted d-block mt-1">{{ __('Used for storefront buttons, links, hover states, and accents.') }}</small>
                                @error('brand_color')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                                <div class="mt-3 d-flex flex-wrap gap-2">
                                    <span class="badge rounded-pill px-3 py-2 text-white" id="brand_preview_btn" style="background:{{ $brandColor }};">{{ __('Button') }}</span>
                                    <span class="badge rounded-pill px-3 py-2 border" id="brand_preview_soft" style="background:color-mix(in srgb, {{ $brandColor }} 14%, white); color:{{ $brandColor }}; border-color:color-mix(in srgb, {{ $brandColor }} 35%, white);">{{ __('Soft') }}</span>
                                </div>
                            </div>
                            <div class="col-12 col-md-6 col-lg-4 mb-5">
                                <label class="form-label" for="logo">{{ __('Logo') }} {!! requiredField() !!}</label>
                                <input type="file" name="logo" id="logo" class="form-control @error('logo') is-invalid @enderror" accept="image/*">
                                <small class="text-muted d-block mt-1">{{ __('Recommended size: 150px × 50px') }}</small>
                                @error('logo')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                @if($setting->logo)
                                    <div class="mt-3 branding-preview" id="logo-preview-wrap">
                                        <img src="{{ $media->url($setting->logo) }}" alt="Logo" style="max-height:64px;" class="d-block mb-2">
                                        <input type="hidden" name="remove_logo" id="remove_logo" value="0">
                                        <button type="button" class="btn btn-sm btn-danger remove-branding-asset"
                                                data-input="#remove_logo" data-wrap="#logo-preview-wrap">
                                            <i class="ti tabler-trash me-1"></i>{{ __('Remove Logo') }}
                                        </button>
                                    </div>
                                @endif
                            </div>
                            <div class="col-12 col-md-6 col-lg-4 mb-5">
                                <label class="form-label" for="footer_logo">{{ __('Footer Logo') }} {!! requiredField() !!}</label>
                                <input type="file" name="footer_logo" id="footer_logo" class="form-control @error('footer_logo') is-invalid @enderror" accept="image/*">
                                <small class="text-muted d-block mt-1">{{ __('Recommended size: 150px × 50px') }}</small>
                                <small class="text-muted d-block">{{ __('Shown in the storefront footer.') }}</small>
                                @error('footer_logo')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                @if($setting->footer_logo)
                                    <div class="mt-3 branding-preview" id="footer-logo-preview-wrap">
                                        <img src="{{ $media->url($setting->footer_logo) }}" alt="Footer Logo" style="max-height:64px;" class="d-block mb-2">
                                        <input type="hidden" name="remove_footer_logo" id="remove_footer_logo" value="0">
                                        <button type="button" class="btn btn-sm btn-danger remove-branding-asset"
                                                data-input="#remove_footer_logo" data-wrap="#footer-logo-preview-wrap">
                                            <i class="ti tabler-trash me-1"></i>{{ __('Remove Footer Logo') }}
                                        </button>
                                    </div>
                                @endif
                            </div>
                            <div class="col-12 col-md-6 col-lg-4 mb-5">
                                <label class="form-label" for="favicon">{{ __('Favicon') }} {!! requiredField() !!}</label>
                                <input type="file" name="favicon" id="favicon" class="form-control @error('favicon') is-invalid @enderror" accept="image/*,.ico">
                                @error('favicon')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                @if($setting->favicon)
                                    <div class="mt-3 branding-preview" id="favicon-preview-wrap">
                                        <img src="{{ $media->url($setting->favicon) }}" alt="Favicon" style="max-height:32px;" class="d-block mb-2">
                                        <input type="hidden" name="remove_favicon" id="remove_favicon" value="0">
                                        <button type="button" class="btn btn-sm btn-danger remove-branding-asset"
                                                data-input="#remove_favicon" data-wrap="#favicon-preview-wrap">
                                            <i class="ti tabler-trash me-1"></i>{{ __('Remove Favicon') }}
                                        </button>
                                    </div>
                                @endif
                            </div>
                        </div>
                    </div>
                    <div class="card-footer">
                        <div class="d-flex">
                            <button type="submit" class="btn btn-primary waves-effect waves-light me-4">
                                {!! submitIconWithText($setting) !!}
                            </button>
                            @if(Route::has('ecommerce.home'))
                                <a href="{{ route('ecommerce.home') }}" target="_blank" class="btn btn-primary waves-effect waves-light">
                                    <i class="ti tabler-external-link me-1"></i>{{ __('View Storefront') }}
                                </a>
                            @endif
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
    $(function () {
        $(document).on('click', '.remove-branding-asset', function () {
            const input = $($(this).data('input'));
            const wrap = $($(this).data('wrap'));
            input.val('1');
            wrap.slideUp(150);
        });

        const picker = $('#brand_color_picker');
        const text = $('#brand_color');
        const previewBtn = $('#brand_preview_btn');
        const previewSoft = $('#brand_preview_soft');

        const syncPreview = (value) => {
            let hex = (value || '').trim();
            if (hex && hex[0] !== '#') hex = '#' + hex;
            if (!/^#[0-9A-Fa-f]{6}$/.test(hex)) return;
            previewBtn.css('background', hex);
            previewSoft.css({
                background: hex + '24',
                color: hex,
                borderColor: hex + '55'
            });
        };

        picker.on('input change', function () {
            text.val(this.value);
            syncPreview(this.value);
        });
        text.on('input change', function () {
            let hex = (this.value || '').trim();
            if (hex && hex[0] !== '#') hex = '#' + hex;
            if (/^#[0-9A-Fa-f]{6}$/.test(hex)) {
                picker.val(hex);
            }
            syncPreview(hex);
        });
    });
</script>
@endpush
