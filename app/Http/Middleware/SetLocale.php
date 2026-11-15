<?php

namespace App\Http\Middleware;

use App\Support\Enums\Locale;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\Response;

/**
 * Picks the interface language: the signed-in user's setting, else the
 * "locale" cookie, else the platform default, Arabic (SRS FR-ACC-10, LLD 11 §2). Public pages take
 * it from their URL prefix first.
 */
class SetLocale
{
    public const COOKIE = 'locale';

    public function handle(Request $request, Closure $next): Response
    {
        $locale = Locale::tryFrom((string) $request->route('locale'))
            ?? Locale::tryFrom((string) $request->user()?->locale)
            ?? Locale::tryFrom((string) $request->cookie(self::COOKIE))
            ?? Locale::tryFrom((string) config('app.locale'))
            ?? Locale::Ar;

        App::setLocale($locale->value);
        View::share(['locale' => $locale->value, 'dir' => $locale->dir()]);

        return $next($request);
    }
}
