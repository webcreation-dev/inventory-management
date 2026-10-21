@extends('install.layout')

@section('title', 'Business Type')
@section('container_class', 'max-w-4xl')

@section('steps')
    @include('install.partials.steps', ['step' => 'business-type'])
@endsection

@section('content')
    <h2 class="text-lg font-semibold text-slate-800 mb-2">{{ lp_is_bd() ? 'আপনার ব্যবসার ধরন নির্বাচন করুন' : 'Select your business type' }}</h2>
    <p class="text-slate-600 text-sm mb-6">
        {{ lp_is_bd()
            ? 'আপনার দোকানের সাথে সবচেয়ে মিল রেখে ব্যবসার ধরন বেছে নিন। সাধারণ মডিউল স্বয়ংক্রিয়ভাবে অন্তর্ভুক্ত থাকে। মূল ফিচারগুলো আগে থেকেই নির্বাচিত থাকে; প্রয়োজনে ঐচ্ছিক ফিচার চালু করতে পারবেন।'
            : 'Choose the business type that best matches your shop. Common modules are included automatically. Core features are pre-selected for your business. Optional features can be enabled as needed.' }}
    </p>

    <form action="{{ route('install.business-type.store') }}" method="POST" class="space-y-5" id="business-type-form">
        @csrf

        <div class="border border-slate-300 rounded-2xl p-4 bg-white space-y-4">
            <div>
                <label for="business_type" class="block text-sm font-medium text-slate-700 mb-1">{{ lp_is_bd() ? 'ব্যবসার ধরন' : 'Business Type' }}</label>
                <select
                    name="business_type"
                    id="business_type"
                    required
                    class="w-full rounded-xl border {{ $errors->has('business_type') ? 'border-red-500' : 'border-slate-300' }} shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                >
                    <option value="">{{ lp_is_bd() ? 'ব্যবসার ধরন নির্বাচন করুন' : 'Select business type' }}</option>
                    @foreach($businessTypeOptions as $slug => $label)
                        <option value="{{ $slug }}" @selected(old('business_type', $selectedType) === $slug)>{{ $label }}</option>
                    @endforeach
                </select>
                @error('business_type')
                    <p class="text-red-600 text-sm mt-1">{{ $message }}</p>
                @enderror
            </div>

            <div id="feature-section" class="{{ $featurePayload ? '' : 'hidden' }}">
                <div id="product-types-box" class="mb-4 p-3 rounded-xl bg-indigo-50 border border-indigo-100 text-sm text-indigo-900"></div>

                <div>
                    <h3 class="text-sm font-semibold text-slate-800 mb-2">{{ lp_is_bd() ? 'ব্যবসার ফিচার' : 'Business Features' }}</h3>
                    <p class="text-xs text-slate-500 mb-3">{{ lp_is_bd() ? 'মূল ফিচারগুলো আপনার ব্যবসার ধরনের জন্য সুপারিশকৃত। ঐচ্ছিক ফিচার চালু/বন্ধ করতে পারবেন।' : 'Core features are recommended for your business type. Optional features can be toggled.' }}</p>
                    <div id="feature-modules-list" class="grid grid-cols-1 sm:grid-cols-2 gap-2.5"></div>
                </div>
            </div>

            <div class="flex justify-between pt-2">
                <a href="{{ route('install.purchase') }}" class="inline-flex items-center px-4 py-2 rounded-xl border border-slate-300 text-slate-700 text-sm font-medium hover:bg-slate-50">
                    ← Previous
                </a>
                <button type="submit" id="business-type-submit" class="inline-flex items-center px-5 py-2.5 rounded-xl bg-indigo-600 text-white text-sm font-medium hover:bg-indigo-700 focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 {{ $featurePayload ? '' : 'opacity-50 cursor-not-allowed' }}" {{ $featurePayload ? '' : 'disabled' }}>
                    Next
                </button>
            </div>
        </div>
    </form>

    <div id="feature-help-modal" class="hidden" aria-hidden="true">
        <div class="install-feature-modal-backdrop" data-close-feature-modal></div>
        <div class="install-feature-modal-panel">
            <div class="install-feature-modal-card relative">
                <button type="button" class="install-feature-modal-close" data-close-feature-modal aria-label="{{ lp_is_bd() ? 'বন্ধ করুন' : 'Close' }}">&times;</button>
                <h3 id="feature-help-modal-title" class="install-feature-modal-title"></h3>
                <div class="install-feature-modal-section">
                    <h4 id="feature-help-purpose-label">{{ lp_is_bd() ? 'মূল উদ্দেশ্য' : 'Main purpose' }}</h4>
                    <p id="feature-help-purpose"></p>
                </div>
                <div class="install-feature-modal-section">
                    <h4 id="feature-help-when-label">{{ lp_is_bd() ? 'কখন দরকার' : 'When you need it' }}</h4>
                    <p id="feature-help-when"></p>
                </div>
            </div>
        </div>
    </div>

    <script>
        (function () {
            const select = document.getElementById('business_type');
            const featureSection = document.getElementById('feature-section');
            const productTypesBox = document.getElementById('product-types-box');
            const featureModulesList = document.getElementById('feature-modules-list');
            const submitButton = document.getElementById('business-type-submit');
            const featuresUrl = @json(route('install.business-type.features'));
            const initialPayload = @json($featurePayload);
            const isBengali = @json(lp_is_bd());
            const uiText = {
                productTypes: isBengali ? 'পণ্যের ধরন' : 'Product Types',
                included: isBengali ? 'এই ব্যবসার ধরনের জন্য অন্তর্ভুক্ত' : 'Included for this business type',
                optionalNote: isBengali ? 'প্রয়োজনে এটি চালু করতে পারেন' : 'You can enable this if needed',
                core: isBengali ? 'মূল' : 'Core',
                optional: isBengali ? 'ঐচ্ছিক' : 'Optional',
                learnMore: isBengali ? 'ফিচার সম্পর্কে বিস্তারিত দেখুন' : 'Learn about this feature',
            };
            const checkIcon = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>';
            const eyeIcon = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1 1 0 0 1 0-.644C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .638C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z"/><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z"/></svg>';
            const featureHelpModal = document.getElementById('feature-help-modal');
            const featureHelpTitle = document.getElementById('feature-help-modal-title');
            const featureHelpPurpose = document.getElementById('feature-help-purpose');
            const featureHelpWhen = document.getElementById('feature-help-when');

            function escapeHtml(value) {
                return String(value ?? '')
                    .replace(/&/g, '&amp;')
                    .replace(/</g, '&lt;')
                    .replace(/>/g, '&gt;')
                    .replace(/"/g, '&quot;')
                    .replace(/'/g, '&#039;');
            }

            function openFeatureHelpModal(label, help) {
                if (!featureHelpModal || !help) {
                    return;
                }

                featureHelpTitle.textContent = label || '';
                featureHelpPurpose.textContent = help.purpose || (isBengali ? 'বিস্তারিত উপলব্ধ নয়।' : 'No details available.');
                featureHelpWhen.textContent = help.when_needed || (isBengali ? 'বিস্তারিত উপলব্ধ নয়।' : 'No details available.');
                featureHelpModal.classList.remove('hidden');
                featureHelpModal.setAttribute('aria-hidden', 'false');
                document.body.classList.add('overflow-hidden');
            }

            function closeFeatureHelpModal() {
                if (!featureHelpModal) {
                    return;
                }

                featureHelpModal.classList.add('hidden');
                featureHelpModal.setAttribute('aria-hidden', 'true');
                document.body.classList.remove('overflow-hidden');
            }

            if (featureHelpModal) {
                featureHelpModal.querySelectorAll('[data-close-feature-modal]').forEach(function (el) {
                    el.addEventListener('click', closeFeatureHelpModal);
                });

                document.addEventListener('keydown', function (e) {
                    if (e.key === 'Escape' && !featureHelpModal.classList.contains('hidden')) {
                        closeFeatureHelpModal();
                    }
                });
            }

            function createCheckCard(options) {
                const {
                    name,
                    label,
                    note,
                    badge,
                    badgeClass,
                    checked,
                    disabled,
                    cardClass,
                    help
                } = options;

                const wrapper = document.createElement('div');
                wrapper.className = 'install-check-card-wrapper';

                const card = document.createElement('label');
                card.className = 'install-check-card ' + cardClass + (checked ? ' is-checked' : '') + (disabled ? ' is-locked' : '');

                if (disabled && name) {
                    const hidden = document.createElement('input');
                    hidden.type = 'hidden';
                    hidden.name = name;
                    hidden.value = '1';
                    wrapper.appendChild(hidden);
                }

                const box = document.createElement('span');
                box.className = 'install-check-box';

                const input = document.createElement('input');
                input.type = 'checkbox';
                if (name) {
                    input.name = name;
                    input.value = '1';
                }
                input.checked = !!checked;
                input.disabled = !!disabled;
                input.setAttribute('aria-label', label);

                const visual = document.createElement('span');
                visual.className = 'install-check-visual';
                visual.innerHTML = checkIcon;

                box.appendChild(input);
                box.appendChild(visual);
                card.appendChild(box);

                const content = document.createElement('span');
                content.className = 'install-check-content';
                content.innerHTML =
                    '<span class="install-check-title">' + escapeHtml(label) + '</span>' +
                    '<span class="install-check-meta"><span class="install-check-badge ' + badgeClass + '">' + escapeHtml(badge) + '</span></span>' +
                    (note ? '<span class="install-check-note">' + escapeHtml(note) + '</span>' : '');

                card.appendChild(content);

                if (!disabled) {
                    input.addEventListener('change', function () {
                        card.classList.toggle('is-checked', input.checked);
                    });
                }

                wrapper.appendChild(card);

                if (help && (help.purpose || help.when_needed)) {
                    const helpBtn = document.createElement('button');
                    helpBtn.type = 'button';
                    helpBtn.className = 'install-feature-help-btn';
                    helpBtn.setAttribute('aria-label', uiText.learnMore);
                    helpBtn.innerHTML = eyeIcon;
                    helpBtn.addEventListener('click', function (e) {
                        e.preventDefault();
                        e.stopPropagation();
                        openFeatureHelpModal(label, help);
                    });
                    wrapper.appendChild(helpBtn);
                }

                return wrapper;
            }

            function renderCheckbox(feature, container) {
                const isCore = feature.status === 'core';
                container.appendChild(createCheckCard({
                    name: feature.key,
                    label: feature.label,
                    note: isCore ? uiText.included : uiText.optionalNote,
                    badge: isCore ? uiText.core : uiText.optional,
                    badgeClass: isCore ? 'is-core' : 'is-optional',
                    checked: isCore ? true : !!feature.checked,
                    disabled: isCore,
                    cardClass: isCore ? 'is-core' : 'is-optional',
                    help: feature.help || null
                }));
            }

            function renderPayload(payload) {
                productTypesBox.innerHTML = payload.product_types.length
                    ? '<strong>' + escapeHtml(uiText.productTypes) + ':</strong> ' + escapeHtml(payload.product_types.join(', '))
                    : '';

                featureModulesList.innerHTML = '';

                (payload.features || []).forEach(function (feature) {
                    renderCheckbox(feature, featureModulesList);
                });

                featureSection.classList.remove('hidden');
                submitButton.disabled = false;
                submitButton.classList.remove('opacity-50', 'cursor-not-allowed');
            }

            async function loadFeatures(type) {
                if (!type) {
                    featureSection.classList.add('hidden');
                    submitButton.disabled = true;
                    submitButton.classList.add('opacity-50', 'cursor-not-allowed');
                    return;
                }

                const response = await fetch(featuresUrl + '?type=' + encodeURIComponent(type), {
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                });

                if (!response.ok) {
                    return;
                }

                renderPayload(await response.json());
            }

            select.addEventListener('change', function () {
                loadFeatures(select.value);
            });

            if (initialPayload) {
                renderPayload(initialPayload);
            }
        })();
    </script>
@endsection
