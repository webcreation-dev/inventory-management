<?php

namespace Modules\Ecommerce\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Ecommerce\Models\EcommerceSetting;
use Modules\Ecommerce\Services\EcommerceMediaService;

class BrandingController extends Controller
{
    public function __construct(
        protected EcommerceMediaService $media,
    ) {
    }

    public function edit(): View
    {
        $setting = EcommerceSetting::forCurrentCompany();

        return view('ecommerce::admin.branding.edit', compact('setting'));
    }

    public function update(Request $request): RedirectResponse
    {
        $setting = EcommerceSetting::forCurrentCompany();

        $logoRequired = ! $setting->logo || $request->boolean('remove_logo');
        $footerLogoRequired = ! $setting->footer_logo || $request->boolean('remove_footer_logo');
        $faviconRequired = ! $setting->favicon || $request->boolean('remove_favicon');

        $validated = $request->validate([
            'site_name' => ['required', 'string', 'max:150'],
            'brand_color' => ['required', 'string', 'max:20', 'regex:/^#?[0-9A-Fa-f]{3}([0-9A-Fa-f]{3})?$/'],
            'logo' => [($logoRequired ? 'required' : 'nullable'), 'image', 'mimes:jpg,jpeg,png,webp,gif,svg', 'max:2048'],
            'footer_logo' => [($footerLogoRequired ? 'required' : 'nullable'), 'image', 'mimes:jpg,jpeg,png,webp,gif,svg', 'max:2048'],
            'favicon' => [($faviconRequired ? 'required' : 'nullable'), 'image', 'mimes:jpg,jpeg,png,webp,gif,ico,svg', 'max:1024'],
            'remove_logo' => ['nullable', 'boolean'],
            'remove_footer_logo' => ['nullable', 'boolean'],
            'remove_favicon' => ['nullable', 'boolean'],
        ], [
            'site_name.required' => __('Please enter the site name.'),
            'brand_color.required' => __('Please select a brand color.'),
            'brand_color.regex' => __('Please enter a valid brand color (e.g. #0d9488).'),
            'logo.required' => __('Please upload a logo.'),
            'logo.image' => __('Logo must be an image file.'),
            'footer_logo.required' => __('Please upload a footer logo.'),
            'footer_logo.image' => __('Footer logo must be an image file.'),
            'favicon.required' => __('Please upload a favicon.'),
            'favicon.image' => __('Favicon must be an image file.'),
        ]);

        $setting->site_name = $validated['site_name'];
        $setting->brand_color = \Modules\Ecommerce\Support\BrandColor::normalize($validated['brand_color']);

        if ($request->boolean('remove_logo') && $setting->logo) {
            $this->media->delete($setting->logo);
            $setting->logo = null;
        }
        if ($request->hasFile('logo')) {
            if ($setting->logo) {
                $this->media->delete($setting->logo);
            }
            $setting->logo = $this->media->upload($request->file('logo'), 'branding');
        }

        if ($request->boolean('remove_footer_logo') && $setting->footer_logo) {
            $this->media->delete($setting->footer_logo);
            $setting->footer_logo = null;
        }
        if ($request->hasFile('footer_logo')) {
            if ($setting->footer_logo) {
                $this->media->delete($setting->footer_logo);
            }
            $setting->footer_logo = $this->media->upload($request->file('footer_logo'), 'branding');
        }

        if ($request->boolean('remove_favicon') && $setting->favicon) {
            $this->media->delete($setting->favicon);
            $setting->favicon = null;
        }
        if ($request->hasFile('favicon')) {
            if ($setting->favicon) {
                $this->media->delete($setting->favicon);
            }
            $setting->favicon = $this->media->upload($request->file('favicon'), 'branding');
        }

        $setting->save();

        return back()->with('success', __('Branding settings saved successfully.'));
    }
}
