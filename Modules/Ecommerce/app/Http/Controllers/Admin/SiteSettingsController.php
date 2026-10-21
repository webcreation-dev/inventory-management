<?php

namespace Modules\Ecommerce\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Ecommerce\Models\EcommerceSetting;

class SiteSettingsController extends Controller
{
    public function edit(): View
    {
        $setting = EcommerceSetting::forCurrentCompany();
        $availableLocales = config('app.available_locales', ['en']);
        $localeNames = config('app.locale_names', []);
        $selectedLanguages = old('storefront_languages', $setting->enabledStorefrontLanguages());

        return view('ecommerce::admin.site-settings.edit', compact(
            'setting',
            'availableLocales',
            'localeNames',
            'selectedLanguages'
        ));
    }

    public function update(Request $request): RedirectResponse
    {
        foreach (['app_link', 'social_facebook', 'social_instagram', 'social_tiktok', 'social_linkedin'] as $field) {
            if (! $request->filled($field)) {
                $request->merge([$field => null]);
            }
        }

        $validated = $request->validate([
            'contact_phone' => ['required', 'string', 'max:50'],
            'contact_email' => ['required', 'email', 'max:150'],
            'contact_address' => ['required', 'string', 'max:500'],
            'footer_description' => ['required', 'string', 'max:1000'],
            'app_link' => ['nullable', 'url', 'max:255'],
            'show_admin_login_url' => ['required', 'in:Yes,No'],
            'social_facebook' => ['nullable', 'url', 'max:255'],
            'social_instagram' => ['nullable', 'url', 'max:255'],
            'social_tiktok' => ['nullable', 'url', 'max:255'],
            'social_linkedin' => ['nullable', 'url', 'max:255'],
            'privacy_policy' => ['required', 'string', function ($attribute, $value, $fail) {
                if ($this->isEmptyEditorHtml($value)) {
                    $fail(__('Please enter the privacy policy content.'));
                }
            }],
            'terms_and_conditions' => ['required', 'string', function ($attribute, $value, $fail) {
                if ($this->isEmptyEditorHtml($value)) {
                    $fail(__('Please enter the terms and conditions content.'));
                }
            }],
            'storefront_languages' => ['required', 'array', 'min:1'],
            'storefront_languages.*' => ['string', 'in:' . implode(',', config('app.available_locales', ['en']))],
        ], [
            'contact_phone.required' => __('Please enter the mobile number.'),
            'contact_email.required' => __('Please enter the email address.'),
            'contact_email.email' => __('Please enter a valid email address.'),
            'contact_address.required' => __('Please enter the address.'),
            'footer_description.required' => __('Please enter the footer description.'),
            'app_link.url' => __('Please enter a valid app link URL.'),
            'show_admin_login_url.required' => __('Please select Show Admin Login URL.'),
            'show_admin_login_url.in' => __('Please select Yes or No for Show Admin Login URL.'),
            'social_facebook.url' => __('Please enter a valid Facebook URL.'),
            'social_instagram.url' => __('Please enter a valid Instagram URL.'),
            'social_tiktok.url' => __('Please enter a valid TikTok URL.'),
            'social_linkedin.url' => __('Please enter a valid LinkedIn URL.'),
            'privacy_policy.required' => __('Please enter the privacy policy content.'),
            'terms_and_conditions.required' => __('Please enter the terms and conditions content.'),
            'storefront_languages.required' => __('Please select at least one storefront language.'),
            'storefront_languages.min' => __('Please select at least one storefront language.'),
        ]);

        $setting = EcommerceSetting::forCurrentCompany();
        $setting->contact_phone = $validated['contact_phone'];
        $setting->contact_email = $validated['contact_email'];
        $setting->contact_address = $validated['contact_address'];
        $setting->footer_description = $validated['footer_description'];
        $setting->app_link = $validated['app_link'] ?? null;
        $setting->show_admin_login_url = $validated['show_admin_login_url'];
        $setting->social_facebook = $validated['social_facebook'] ?? null;
        $setting->social_instagram = $validated['social_instagram'] ?? null;
        $setting->social_tiktok = $validated['social_tiktok'] ?? null;
        $setting->social_linkedin = $validated['social_linkedin'] ?? null;
        $setting->privacy_policy = $this->sanitizeEditorHtml($validated['privacy_policy'] ?? null);
        $setting->terms_and_conditions = $this->sanitizeEditorHtml($validated['terms_and_conditions'] ?? null);

        $langs = array_values(array_unique($validated['storefront_languages'] ?? ['en']));
        $available = config('app.available_locales', ['en']);
        $setting->storefront_languages = array_values(array_intersect($available, $langs)) ?: ['en'];

        $setting->save();

        return back()->with('success', __('Site settings saved successfully.'));
    }

    protected function isEmptyEditorHtml(?string $html): bool
    {
        if ($html === null) {
            return true;
        }

        $text = trim(html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        $text = str_replace("\xC2\xA0", '', $text);

        return $text === '';
    }

    protected function sanitizeEditorHtml(?string $html): ?string
    {
        if ($this->isEmptyEditorHtml($html)) {
            return null;
        }

        return trim((string) $html);
    }
}
