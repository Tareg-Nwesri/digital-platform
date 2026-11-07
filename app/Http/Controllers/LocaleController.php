<?php

namespace App\Http\Controllers;

use App\Http\Middleware\SetLocale;
use App\Support\Enums\Locale;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

class LocaleController extends Controller
{
    /**
     * Switch the interface language. Signed-in users keep it on their account;
     * everyone gets a one-year cookie. From the app it forces a full page
     * load, because <html lang dir> and the loaded translations only change
     * when the root document is rendered again.
     */
    public function __invoke(Request $request, string $locale): RedirectResponse|Response
    {
        $locale = Locale::tryFrom($locale) ?? abort(404);

        $request->user()?->forceFill(['locale' => $locale->value])->save();

        Cookie::queue(SetLocale::COOKIE, $locale->value, 60 * 24 * 365);

        return $request->header('X-Inertia')
            ? Inertia::location(url()->previous())
            : back();
    }
}
