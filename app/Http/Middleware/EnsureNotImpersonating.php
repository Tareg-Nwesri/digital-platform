<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Sensitive actions are blocked while the super admin is acting as someone else
 * (SRS FR-ACC-14): security settings, password changes, permission grants,
 * finance approvals and starting another impersonation.
 */
class EnsureNotImpersonating
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_if(app('impersonate')->isImpersonating(), 403, __('access.impersonation.blocked'));

        return $next($request);
    }
}
