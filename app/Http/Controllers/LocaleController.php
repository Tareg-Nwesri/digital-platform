<?php

namespace App\Http\Controllers;

use App\Http\Middleware\SetLocale;
use App\Support\Enums\Locale;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;

class LocaleController extends Controller
{
    /**
     * Switch the interface language. Signed-in users keep it on their account;
     * everyone gets a one-year cookie.
     */
    public function __invoke(Request $request, string $locale): RedirectResponse
    {
        $locale = Locale::tryFrom($locale) ?? abort(404);

        $request->user()?->forceFill(['locale' => $locale->value])->save();

        Cookie::queue(SetLocale::COOKIE, $locale->value, 60 * 24 * 365);

        return back();
    }
}
