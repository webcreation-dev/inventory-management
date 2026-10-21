<?php

namespace App\Http\Controllers;

use App\Support\UserLocalePreference;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Session;

class LanguageController extends Controller
{
    /**
     * Switch the application language
     *
     * @param  string  $locale
     * @return \Illuminate\Http\RedirectResponse
     */
    public function switch($locale)
    {
        // Validate locale
        if (! in_array($locale, available_ui_locales(), true)) {
            abort(404, 'Language not found');
        }

        // Store locale in session
        Session::put('locale', $locale);

        if (Auth::check()) {
            Auth::user()->language = $locale;
            Auth::user()->save();
        }

        // Redirect back to the previous page
        return Redirect::back();
    }
}
