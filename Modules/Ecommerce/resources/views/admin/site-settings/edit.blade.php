@extends('backend.backend_layout')
@section('page-title', __('Site Settings'))
@section('page-content')
<div class="container-xxl flex-grow-1 container-p-y">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-6 row-gap-4">
        <div class="d-flex flex-column justify-content-center">
            <h4 class="mb-0">{{ __('Site Settings') }}</h4>
        </div>
        @include('backend.components.breadcrumb', [
            'breadcrumbs' => [
                ['label' => '<i class="ti tabler-home"></i>', 'link' => route('dashboard')],
                ['label' => __('Ecommerce CMS'), 'link' => route('ecommerce.admin.site-settings.edit')],
                ['label' => __('Site Settings'), 'active' => true],
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
            <form method="POST" action="{{ route('ecommerce.admin.site-settings.update') }}" novalidate>
                @csrf
                @method('PUT')
                <div class="card">
                    <div class="card-body">
                        <div class="row">
                            <div class="col-12 col-md-6 col-lg-4 mb-5">
                                <label class="form-label" for="contact_phone">{{ __('Mobile Number') }} {!! requiredField() !!}</label>
                                <input type="text" name="contact_phone" id="contact_phone"
                                       class="form-control @error('contact_phone') is-invalid @enderror"
                                       value="{{ old('contact_phone', $setting->contact_phone) }}"
                                       placeholder="+01XXXXXXXXX">
                                @error('contact_phone')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-12 col-md-6 col-lg-4 mb-5">
                                <label class="form-label" for="contact_email">{{ __('Email') }} {!! requiredField() !!}</label>
                                <input type="email" name="contact_email" id="contact_email"
                                       class="form-control @error('contact_email') is-invalid @enderror"
                                       value="{{ old('contact_email', $setting->contact_email) }}"
                                       placeholder="support@example.com">
                                @error('contact_email')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-12 col-md-6 col-lg-4 mb-5">
                                <label class="form-label" for="app_link">{{ __('App Link') }}</label>
                                <input type="url" name="app_link" id="app_link"
                                       class="form-control @error('app_link') is-invalid @enderror"
                                       value="{{ old('app_link', $setting->app_link) }}"
                                       placeholder="https://play.google.com/store/apps/...">
                                <small class="text-muted">{{ __('Mobile app store or download URL shown on the storefront.') }}</small>
                                @error('app_link')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-12 col-md-6 col-lg-4 mb-5">
                                @php $selectedShowAdminLogin = old('show_admin_login_url', $setting->show_admin_login_url ?? 'No'); @endphp
                                <label class="form-label" for="show_admin_login_url">{{ __('Show Admin Login URL') }} {!! requiredField() !!}</label>
                                <select name="show_admin_login_url" id="show_admin_login_url"
                                        class="form-select select2 @error('show_admin_login_url') is-invalid @enderror"
                                        data-placeholder="{{ __('Select') }}">
                                    <option value="Yes" {{ $selectedShowAdminLogin === 'Yes' ? 'selected' : '' }}>{{ __('Yes') }}</option>
                                    <option value="No" {{ $selectedShowAdminLogin === 'No' ? 'selected' : '' }}>{{ __('No') }}</option>
                                </select>
                                <small class="text-muted">{{ __('If Yes, the storefront user icon shows Login as Customer and Login as Admin.') }}</small>
                                @error('show_admin_login_url')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-12 mb-5">
                                <label class="form-label" for="contact_address">{{ __('Address') }} {!! requiredField() !!}</label>
                                <textarea name="contact_address" id="contact_address" rows="3"
                                          class="form-control @error('contact_address') is-invalid @enderror"
                                          placeholder="{{ __('Store / office address') }}">{{ old('contact_address', $setting->contact_address) }}</textarea>
                                @error('contact_address')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-12 mb-5">
                                <label class="form-label" for="footer_description">{{ __('Footer Description') }} {!! requiredField() !!}</label>
                                <textarea name="footer_description" id="footer_description" rows="3"
                                          class="form-control @error('footer_description') is-invalid @enderror"
                                          placeholder="{{ __('Short description shown under the store name in the footer') }}">{{ old('footer_description', $setting->footer_description) }}</textarea>
                                @error('footer_description')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                            </div>

                            <div class="col-12 mb-2">
                                <hr class="my-2">
                                <h5 class="mb-1">{{ __('Social Links') }}</h5>
                                <p class="text-muted small mb-4">{{ __('Shown as icons in the ecommerce footer. Leave blank to hide.') }}</p>
                            </div>
                            <div class="col-12 col-md-6 col-lg-3 mb-5">
                                <label class="form-label" for="social_facebook">{{ __('Facebook') }}</label>
                                <input type="url" name="social_facebook" id="social_facebook"
                                       class="form-control @error('social_facebook') is-invalid @enderror"
                                       value="{{ old('social_facebook', $setting->social_facebook) }}"
                                       placeholder="https://facebook.com/...">
                                @error('social_facebook')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-12 col-md-6 col-lg-3 mb-5">
                                <label class="form-label" for="social_instagram">{{ __('Instagram') }}</label>
                                <input type="url" name="social_instagram" id="social_instagram"
                                       class="form-control @error('social_instagram') is-invalid @enderror"
                                       value="{{ old('social_instagram', $setting->social_instagram) }}"
                                       placeholder="https://instagram.com/...">
                                @error('social_instagram')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-12 col-md-6 col-lg-3 mb-5">
                                <label class="form-label" for="social_tiktok">{{ __('TikTok') }}</label>
                                <input type="url" name="social_tiktok" id="social_tiktok"
                                       class="form-control @error('social_tiktok') is-invalid @enderror"
                                       value="{{ old('social_tiktok', $setting->social_tiktok) }}"
                                       placeholder="https://tiktok.com/@...">
                                @error('social_tiktok')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-12 col-md-6 col-lg-3 mb-5">
                                <label class="form-label" for="social_linkedin">{{ __('LinkedIn') }}</label>
                                <input type="url" name="social_linkedin" id="social_linkedin"
                                       class="form-control @error('social_linkedin') is-invalid @enderror"
                                       value="{{ old('social_linkedin', $setting->social_linkedin) }}"
                                       placeholder="https://linkedin.com/company/...">
                                @error('social_linkedin')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-12 mb-2">
                                <label class="form-label d-block">{{ __('Storefront Languages') }} {!! requiredField() !!}</label>
                                <p class="text-muted small mb-3">{{ __('Only selected languages will appear in the ecommerce header language switcher.') }}</p>
                                <div class="ec-lang-checkboxes d-flex flex-wrap gap-2">
                                    @foreach($availableLocales as $locale)
                                        @php $isChecked = in_array($locale, $selectedLanguages ?? [], true); @endphp
                                        <label class="ec-lang-chip {{ $isChecked ? 'is-checked' : '' }}">
                                            <input type="checkbox" name="storefront_languages[]"
                                                   value="{{ $locale }}"
                                                   class="ec-lang-chip-input"
                                                   @checked($isChecked)>
                                            <span class="ec-lang-chip-name">{{ $localeNames[$locale] ?? strtoupper($locale) }}</span>
                                            <span class="ec-lang-chip-check" aria-hidden="true">
                                                <i class="ti tabler-check"></i>
                                            </span>
                                        </label>
                                    @endforeach
                                </div>
                                @error('storefront_languages')<div class="text-danger small mt-2">{{ $message }}</div>@enderror
                                @error('storefront_languages.*')<div class="text-danger small mt-2">{{ $message }}</div>@enderror
                            </div>

                            <div class="col-12 mt-4 mb-5">
                                <hr class="my-4">
                                <h5 class="mb-1">{{ __('Legal Pages') }}</h5>
                                <p class="text-muted small mb-4">{{ __('Content shown on the storefront Privacy Policy and Terms and Conditions pages. Use the editor to format text.') }}</p>

                                <div class="mb-5">
                                    <label class="form-label" for="privacy_policy">{{ __('Privacy Policy') }} {!! requiredField() !!}</label>
                                    <textarea name="privacy_policy" id="privacy_policy" class="d-none">{{ old('privacy_policy', $setting->privacy_policy) }}</textarea>
                                    <div id="privacy_policy_editor" class="ec-ckeditor-host border rounded"></div>
                                    @error('privacy_policy')<div class="text-danger small mt-2">{{ $message }}</div>@enderror
                                    @if(Route::has('ecommerce.privacy'))
                                        <small class="text-muted d-block mt-2">
                                            <a href="{{ route('ecommerce.privacy') }}" target="_blank" rel="noopener">{{ __('View Privacy Policy page') }}</a>
                                        </small>
                                    @endif
                                </div>

                                <div class="mb-2">
                                    <label class="form-label" for="terms_and_conditions">{{ __('Terms and Conditions') }} {!! requiredField() !!}</label>
                                    <textarea name="terms_and_conditions" id="terms_and_conditions" class="d-none">{{ old('terms_and_conditions', $setting->terms_and_conditions) }}</textarea>
                                    <div id="terms_and_conditions_editor" class="ec-ckeditor-host border rounded"></div>
                                    @error('terms_and_conditions')<div class="text-danger small mt-2">{{ $message }}</div>@enderror
                                    @if(Route::has('ecommerce.terms'))
                                        <small class="text-muted d-block mt-2">
                                            <a href="{{ route('ecommerce.terms') }}" target="_blank" rel="noopener">{{ __('View Terms and Conditions page') }}</a>
                                        </small>
                                    @endif
                                </div>
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

@push('page-css')
<style>
    .ec-lang-chip {
        position: relative;
        display: inline-flex;
        align-items: center;
        gap: 0.55rem;
        min-height: 2.65rem;
        padding: 0.45rem 0.85rem 0.45rem 0.55rem;
        margin: 0;
        border-radius: 0.85rem;
        border: 1px solid #e2e8f0;
        background: #fff;
        cursor: pointer;
        user-select: none;
        transition: border-color .15s ease, background .15s ease, box-shadow .15s ease, color .15s ease;
    }
    .ec-lang-chip:hover {
        border-color: #cbd5e1;
        background: #f8fafc;
    }
    .ec-lang-chip-input {
        position: absolute;
        opacity: 0;
        pointer-events: none;
    }
    .ec-lang-chip-code {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-width: 2.15rem;
        height: 1.7rem;
        padding: 0 0.4rem;
        border-radius: 0.5rem;
        background: #f1f5f9;
        color: #475569;
        font-size: 0.72rem;
        font-weight: 800;
        letter-spacing: 0.04em;
    }
    .ec-lang-chip-name {
        font-size: 0.9rem;
        font-weight: 600;
        color: #334155;
    }
    .ec-lang-chip-check {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 1.25rem;
        height: 1.25rem;
        margin-left: 0.15rem;
        border-radius: 999px;
        border: 1px solid #cbd5e1;
        color: transparent;
        background: #fff;
        font-size: 0.75rem;
        transition: all .15s ease;
    }
    .ec-lang-chip.is-checked,
    .ec-lang-chip:has(.ec-lang-chip-input:checked) {
        border-color: rgba(var(--bs-primary-rgb, 13, 110, 253), 0.45);
        background: rgba(var(--bs-primary-rgb, 13, 110, 253), 0.08);
        box-shadow: 0 0 0 3px rgba(var(--bs-primary-rgb, 13, 110, 253), 0.1);
    }
    .ec-lang-chip.is-checked .ec-lang-chip-code,
    .ec-lang-chip:has(.ec-lang-chip-input:checked) .ec-lang-chip-code {
        background: var(--bs-primary);
        color: #fff;
    }
    .ec-lang-chip.is-checked .ec-lang-chip-name,
    .ec-lang-chip:has(.ec-lang-chip-input:checked) .ec-lang-chip-name {
        color: var(--bs-primary);
    }
    .ec-lang-chip.is-checked .ec-lang-chip-check,
    .ec-lang-chip:has(.ec-lang-chip-input:checked) .ec-lang-chip-check {
        border-color: var(--bs-primary);
        background: var(--bs-primary);
        color: #fff;
    }
    .ec-ckeditor-host .ck-editor__editable {
        min-height: 260px;
    }
    .ec-ckeditor-host .ck.ck-editor {
        width: 100%;
    }
</style>
@endpush

@push('page-js')
<script src="https://cdn.ckeditor.com/ckeditor5/41.4.2/classic/ckeditor.js"></script>
<script>
    $(function () {
        $(document).on('change', '.ec-lang-chip-input', function () {
            $(this).closest('.ec-lang-chip').toggleClass('is-checked', this.checked);
        });

        function bindCkEditor(editorId, textareaId) {
            var host = document.getElementById(editorId);
            var textarea = document.getElementById(textareaId);
            if (!host || !textarea || typeof ClassicEditor === 'undefined') {
                return Promise.resolve(null);
            }

            return ClassicEditor.create(host, {
                toolbar: {
                    items: [
                        'heading', '|',
                        'bold', 'italic', 'link', '|',
                        'bulletedList', 'numberedList', 'blockQuote', '|',
                        'undo', 'redo'
                    ]
                }
            }).then(function (editor) {
                editor.setData(textarea.value || '');
                editor.model.document.on('change:data', function () {
                    textarea.value = editor.getData();
                });
                return editor;
            }).catch(function (error) {
                console.error('CKEditor init failed:', error);
                return null;
            });
        }

        var form = document.querySelector('form[action="{{ route('ecommerce.admin.site-settings.update') }}"]');
        Promise.all([
            bindCkEditor('privacy_policy_editor', 'privacy_policy'),
            bindCkEditor('terms_and_conditions_editor', 'terms_and_conditions')
        ]).then(function (editors) {
            if (!form) return;
            form.addEventListener('submit', function () {
                editors.forEach(function (editor, index) {
                    if (!editor) return;
                    var id = index === 0 ? 'privacy_policy' : 'terms_and_conditions';
                    var textarea = document.getElementById(id);
                    if (textarea) textarea.value = editor.getData();
                });
            });
        });
    });
</script>
@endpush
