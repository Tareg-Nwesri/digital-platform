<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Super admins and department admins must confirm 2FA before using the app
 * (SRS FR-ACC-04, NFR-SEC-03). Security settings, password confirmation,
 * the 2FA endpoints and logout stay reachable so they can turn it on.
 */
class EnsureTwoFactorWhenRequired
{
    /**
     * @var list<string>
     */
    private const ALLOWED_ROUTES = [
        'security.edit',
        'user-password.update',
        'password.confirm',
        'password.confirmation',
        'password.confirm.store',
        'two-factor.*',
        'logout',
        'impersonate.leave',
        'profile.edit',
        'profile.update',
        'appearance.edit',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null
            || app('impersonate')->isImpersonating()
            || $user->two_factor_confirmed_at !== null
            || ! $user->requiresTwoFactor()
            || $request->routeIs(...self::ALLOWED_ROUTES)) {
            return $next($request);
        }

        if ($request->expectsJson()) {
            abort(403, __('access.two_factor_required'));
        }

        return redirect()->route('security.edit')->with('status', 'two-factor-required');
    }
}
