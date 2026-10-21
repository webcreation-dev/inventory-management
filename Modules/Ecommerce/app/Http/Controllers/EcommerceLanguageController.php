<?php

namespace Modules\Ecommerce\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Session;
use Modules\Ecommerce\Services\EcommerceContentService;

class EcommerceLanguageController extends Controller
{
    public function switch(string $locale, EcommerceContentService $content): RedirectResponse
    {
        $allowed = $content->storefrontLanguages();

        if (! in_array($locale, $allowed, true)) {
            abort(404, 'Language not available for storefront');
        }

        Session::put('locale', $locale);

        return redirect()->back();
    }
}
