@extends('ecommerce::layouts.app')

@push('styles')
    <link rel="stylesheet" href="{{ asset('public/backend_assets/vendor/css/pages/cropper.min.css') }}">
@endpush

@section('content')
    @php
        $tab = $tab ?? 'dashboard';
        $customer = $customer ?? ($ecommerceCustomer ?? []);
        $menu = [
            'dashboard' => ['label' => __('Dashboard'), 'icon' => 'M3 12l9-9 9 9M5 10v10h14V10'],
            'orders' => ['label' => __('My Orders'), 'icon' => 'M9 5h12M9 12h12M9 19h12M4 5h.01M4 12h.01M4 19h.01'],
            'profile' => ['label' => __('Profile Settings'), 'icon' => 'M12 12a4 4 0 1 0 0-8 4 4 0 0 0 0 8Zm-7 9a7 7 0 0 1 14 0'],
            'password' => ['label' => __('Password'), 'icon' => 'M12 15v2m-6 4h12a2 2 0 0 0 2-2v-6a2 2 0 0 0-2-2H6a2 2 0 0 0-2 2v6a2 2 0 0 0 2 2Zm10-10V7a4 4 0 1 0-8 0v2'],
        ];
        $photoUrl = $customer['photo_url'] ?? null;
        $defaultAvatar = asset('public/uploads/dummy_images/default-picture.png');
    @endphp
    <section class="mx-auto max-w-7xl px-4 py-14 sm:px-6 lg:px-8">
        @if(session('success'))
            <div class="mb-6 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">{{ session('success') }}</div>
        @endif
        @if(session('error'))
            <div class="mb-6 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">{{ session('error') }}</div>
        @endif

        <div class="grid gap-8 lg:grid-cols-4">
            <aside class="ec-panel h-fit p-5">
                <h2 class="ec-display text-lg font-bold text-slate-900">{{ __('Customer Panel') }}</h2>
                @if(!empty($customer['name']))
                    <p class="mt-2 text-sm text-slate-500">{{ __('Welcome') }}, {{ $customer['name'] }}</p>
                @endif
                <ul class="mt-5 space-y-1.5 text-sm">
                    @foreach($menu as $key => $item)
                        <li>
                            <a href="{{ route('ecommerce.customer-panel', ['tab' => $key]) }}"
                               class="flex items-center gap-2.5 rounded-xl px-3 py-2.5 font-semibold transition {{ $tab === $key ? 'bg-[var(--accent-soft)] text-[var(--accent-strong)]' : 'text-slate-600 hover:bg-slate-50' }}">
                                <svg class="h-4 w-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="{{ $item['icon'] }}"/>
                                </svg>
                                {{ $item['label'] }}
                            </a>
                        </li>
                    @endforeach
                    <li>
                        <a href="{{ route('ecommerce.wishlist') }}" class="flex items-center gap-2.5 rounded-xl px-3 py-2.5 font-semibold text-slate-600 hover:bg-slate-50">
                            <svg class="h-4 w-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="m21.752 7.516c0 4.653-4.807 8.19-9.033 12.068a1.1 1.1 0 0 1-1.438 0C7.056 15.706 2.248 12.169 2.248 7.516A5.392 5.392 0 0 1 7.64 2.124c1.46 0 2.86.595 3.893 1.649a5.47 5.47 0 0 1 3.894-1.649 5.392 5.392 0 0 1 5.325 5.392Z"/>
                            </svg>
                            {{ __('Wishlist') }}
                            <span class="ml-auto rounded-full bg-slate-100 px-2 py-0.5 text-xs font-bold text-slate-600">{{ $wishlistCount ?? 0 }}</span>
                        </a>
                    </li>
                    <li>
                        <form method="POST" action="{{ route('ecommerce.logout') }}">
                            @csrf
                            <button type="submit" class="flex w-full items-center gap-2.5 rounded-xl px-3 py-2.5 text-left font-semibold text-rose-600 hover:bg-rose-50">
                                <svg class="h-4 w-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M10 17l5-5-5-5M15 12H3m12-7h4a1 1 0 0 1 1 1v14a1 1 0 0 1-1 1h-4"/>
                                </svg>
                                {{ __('Logout') }}
                            </button>
                        </form>
                    </li>
                </ul>
            </aside>

            <div class="space-y-6 lg:col-span-3">
                @if($tab === 'dashboard')
                    <div class="grid gap-4 sm:grid-cols-3">
                        <div class="ec-panel p-5">
                            <p class="text-xs font-bold uppercase tracking-wide text-slate-500">{{ __('Total Orders') }}</p>
                            <p class="mt-2 text-2xl font-bold text-slate-900">{{ $totalOrders ?? 0 }}</p>
                        </div>
                        <div class="ec-panel p-5">
                            <p class="text-xs font-bold uppercase tracking-wide text-slate-500">{{ __('Pending Orders') }}</p>
                            <p class="mt-2 text-2xl font-bold text-amber-600">{{ $pendingOrders ?? 0 }}</p>
                        </div>
                        <div class="ec-panel p-5">
                            <p class="text-xs font-bold uppercase tracking-wide text-slate-500">{{ __('Wishlist Items') }}</p>
                            <p class="mt-2 text-2xl font-bold text-[var(--accent-strong)]">{{ $wishlistCount ?? 0 }}</p>
                        </div>
                    </div>
                    <div class="ec-panel overflow-hidden">
                        <div class="border-b border-slate-200 px-5 py-4">
                            <h3 class="ec-display text-base font-bold text-slate-900">{{ __('Recent Orders') }}</h3>
                        </div>
                        @include('ecommerce::partials.customer-orders-table', ['recentOrders' => $recentOrders ?? []])
                    </div>
                @endif

                @if($tab === 'orders')
                    <div class="ec-panel overflow-hidden">
                        <div class="border-b border-slate-200 px-5 py-4">
                            <h3 class="ec-display text-base font-bold text-slate-900">{{ __('My Orders') }}</h3>
                        </div>
                        @include('ecommerce::partials.customer-orders-table', ['recentOrders' => $recentOrders ?? []])
                        @if(!empty($ordersPaginator) && $ordersPaginator->hasPages())
                            <div class="border-t border-slate-200 px-5 py-4">
                                {{ $ordersPaginator->links() }}
                            </div>
                        @endif
                    </div>
                @endif

                @if($tab === 'profile')
                    <div class="ec-panel p-6">
                        <h3 class="ec-display text-base font-bold text-slate-900">{{ __('Profile Settings') }}</h3>
                        <form method="POST" action="{{ route('ecommerce.customer-panel.profile') }}" class="mt-6 grid gap-4 sm:grid-cols-2" id="customerProfileForm">
                            @csrf
                            <input type="hidden" name="tab" value="profile">
                            <input type="hidden" name="photo" id="customerPhotoData" value="">

                            <div class="sm:col-span-2 flex flex-wrap items-center gap-4">
                                <img id="customerPhotoPreview"
                                     src="{{ $photoUrl ?: $defaultAvatar }}"
                                     alt="{{ __('Profile photo') }}"
                                     class="ec-avatar-preview">
                                <div>
                                    <label class="ec-btn ec-btn-ghost cursor-pointer">
                                        <input type="file" id="customerPhotoInput" accept="image/jpeg,image/png,image/webp" class="hidden">
                                        {{ __('Upload Photo') }}
                                    </label>
                                    <p class="mt-2 text-xs text-slate-500">{{ __('Crop to 250×250px. Max size 500KB.') }}</p>
                                    @error('photo')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
                                </div>
                            </div>

                            <div>
                                <label class="ec-label">{{ __('First Name') }}</label>
                                <input type="text" name="first_name" value="{{ old('first_name', $customer['first_name'] ?? '') }}" required class="ec-input">
                                @error('first_name')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
                            </div>
                            <div>
                                <label class="ec-label">{{ __('Last Name') }}</label>
                                <input type="text" name="last_name" value="{{ old('last_name', $customer['last_name'] ?? '') }}" class="ec-input">
                            </div>
                            <div>
                                <label class="ec-label">{{ __('Email') }}</label>
                                <input type="email" name="email" value="{{ old('email', $customer['email'] ?? '') }}" required class="ec-input">
                                @error('email')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
                            </div>
                            <div>
                                <label class="ec-label">{{ __('Phone') }}</label>
                                <input type="text" name="phone" value="{{ old('phone', $customer['phone'] ?? '') }}" required class="ec-input">
                                @error('phone')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
                            </div>
                            <div class="sm:col-span-2">
                                <label class="ec-label">{{ __('Address') }}</label>
                                <textarea name="address" rows="3" class="ec-input">{{ old('address', $customer['address'] ?? '') }}</textarea>
                            </div>
                            <div class="sm:col-span-2">
                                <button type="submit" class="ec-btn ec-btn-primary">
                                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                    {{ __('Save Profile') }}
                                </button>
                            </div>
                        </form>
                    </div>
                @endif

                @if($tab === 'password')
                    <div class="ec-panel p-6">
                        <h3 class="ec-display text-base font-bold text-slate-900">{{ __('Password') }}</h3>
                        <p class="mt-2 text-sm text-slate-500">{{ __('Update your account password.') }}</p>
                        <form method="POST" action="{{ route('ecommerce.customer-panel.profile') }}" class="mt-6 grid gap-4 sm:grid-cols-2" novalidate>
                            @csrf
                            <input type="hidden" name="tab" value="password">
                            <div>
                                <label class="ec-label">{{ __('New Password') }}</label>
                                <div class="ec-password-field">
                                    <input type="password" name="password" class="ec-input @error('password') border-rose-400 @enderror" placeholder="{{ __('Enter new password') }}">
                                    <button type="button" class="ec-password-toggle" data-ec-password-toggle aria-label="{{ __('Show password') }}">
                                        <svg class="ec-eye-open" viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M2.5 12s3.5-7 9.5-7 9.5 7 9.5 7-3.5 7-9.5 7-9.5-7-9.5-7Z"/><circle cx="12" cy="12" r="3" stroke-width="1.8"/></svg>
                                        <svg class="ec-eye-closed hidden" viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M3 3l18 18M10.6 10.6a3 3 0 0 0 4.2 4.2M9.9 5.1A10.4 10.4 0 0 1 12 5c6 0 9.5 7 9.5 7a16.6 16.6 0 0 1-3.1 3.9M6.1 6.1A16.6 16.6 0 0 0 2.5 12S6 19 12 19c1.1 0 2.1-.2 3.1-.5"/></svg>
                                    </button>
                                </div>
                                @error('password')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
                            </div>
                            <div>
                                <label class="ec-label">{{ __('Confirm Password') }}</label>
                                <div class="ec-password-field">
                                    <input type="password" name="password_confirmation" class="ec-input" placeholder="{{ __('Confirm new password') }}">
                                    <button type="button" class="ec-password-toggle" data-ec-password-toggle aria-label="{{ __('Show password') }}">
                                        <svg class="ec-eye-open" viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M2.5 12s3.5-7 9.5-7 9.5 7 9.5 7-3.5 7-9.5 7-9.5-7-9.5-7Z"/><circle cx="12" cy="12" r="3" stroke-width="1.8"/></svg>
                                        <svg class="ec-eye-closed hidden" viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M3 3l18 18M10.6 10.6a3 3 0 0 0 4.2 4.2M9.9 5.1A10.4 10.4 0 0 1 12 5c6 0 9.5 7 9.5 7a16.6 16.6 0 0 1-3.1 3.9M6.1 6.1A16.6 16.6 0 0 0 2.5 12S6 19 12 19c1.1 0 2.1-.2 3.1-.5"/></svg>
                                    </button>
                                </div>
                            </div>
                            <div class="sm:col-span-2">
                                <button type="submit" class="ec-btn ec-btn-primary">
                                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 0 0 2-2v-6a2 2 0 0 0-2-2H6a2 2 0 0 0-2 2v6a2 2 0 0 0 2 2Zm10-10V7a4 4 0 1 0-8 0v2"/></svg>
                                    {{ __('Update Password') }}
                                </button>
                            </div>
                        </form>
                    </div>
                @endif
            </div>
        </div>
    </section>

    <div id="ecCropperModal" class="ec-cropper-modal" aria-hidden="true">
        <div class="ec-cropper-dialog">
            <div class="flex items-center justify-between border-b border-slate-200 px-4 py-3">
                <h4 class="text-sm font-bold text-slate-900">{{ __('Crop Photo') }}</h4>
                <button type="button" id="ecCropperClose" class="text-slate-500 hover:text-slate-800" aria-label="{{ __('Close') }}">×</button>
            </div>
            <div class="ec-cropper-stage p-3">
                <img id="ecCropperImage" alt="">
            </div>
            <div class="flex justify-end gap-2 border-t border-slate-200 px-4 py-3">
                <button type="button" id="ecCropperCancel" class="ec-btn ec-btn-ghost">{{ __('Cancel') }}</button>
                <button type="button" id="ecCropperApply" class="ec-btn ec-btn-primary">{{ __('Apply') }}</button>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script src="{{ asset('public/backend_assets/vendor/js/cropper.js') }}"></script>
    <script>
        (function () {
            const input = document.getElementById('customerPhotoInput');
            if (!input || typeof Cropper === 'undefined') return;

            const preview = document.getElementById('customerPhotoPreview');
            const hidden = document.getElementById('customerPhotoData');
            const modal = document.getElementById('ecCropperModal');
            const cropImg = document.getElementById('ecCropperImage');
            const maxBytes = 500 * 1024;
            let cropper = null;

            const openModal = () => {
                modal.classList.add('is-open');
                modal.setAttribute('aria-hidden', 'false');
            };
            const closeModal = () => {
                modal.classList.remove('is-open');
                modal.setAttribute('aria-hidden', 'true');
                if (cropper) {
                    cropper.destroy();
                    cropper = null;
                }
                cropImg.removeAttribute('src');
                input.value = '';
            };

            input.addEventListener('change', function () {
                const file = this.files && this.files[0];
                if (!file) return;
                if (!file.type.match(/^image\/(jpeg|png|webp)$/)) {
                    alert(@json(__('Please select a JPG, PNG, or WEBP image.')));
                    this.value = '';
                    return;
                }
                if (file.size > maxBytes) {
                    alert(@json(__('Image must be 500KB or smaller.')));
                    this.value = '';
                    return;
                }

                const reader = new FileReader();
                reader.onload = function (e) {
                    cropImg.src = e.target.result;
                    openModal();
                    cropper = new Cropper(cropImg, {
                        aspectRatio: 1,
                        viewMode: 1,
                        dragMode: 'move',
                        autoCropArea: 1,
                        responsive: true,
                        background: false,
                    });
                };
                reader.readAsDataURL(file);
            });

            const applyCrop = () => {
                if (!cropper) return;
                const canvas = cropper.getCroppedCanvas({
                    width: 250,
                    height: 250,
                    imageSmoothingEnabled: true,
                    imageSmoothingQuality: 'high',
                });
                if (!canvas) return;

                let quality = 0.9;
                let dataUrl = canvas.toDataURL('image/jpeg', quality);
                while (dataUrl.length > maxBytes * 1.37 && quality > 0.5) {
                    quality -= 0.1;
                    dataUrl = canvas.toDataURL('image/jpeg', quality);
                }

                const approxBytes = Math.ceil((dataUrl.length - 'data:image/jpeg;base64,'.length) * 0.75);
                if (approxBytes > maxBytes) {
                    alert(@json(__('Cropped image exceeds 500KB. Try a smaller source image.')));
                    return;
                }

                preview.src = dataUrl;
                hidden.value = dataUrl;
                closeModal();
            };

            document.getElementById('ecCropperApply')?.addEventListener('click', applyCrop);
            document.getElementById('ecCropperCancel')?.addEventListener('click', closeModal);
            document.getElementById('ecCropperClose')?.addEventListener('click', closeModal);
            modal?.addEventListener('click', function (e) {
                if (e.target === modal) closeModal();
            });
        })();
    </script>
@endpush
