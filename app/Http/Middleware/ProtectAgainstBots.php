<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use NjoguAmos\Turnstile\Turnstile;
use Spatie\Honeypot\Exceptions\SpamException;
use Spatie\Honeypot\SpamProtection;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Honeypot + Cloudflare Turnstile for forms bots target (SRS NFR-SEC-04).
 *
 * Unlike spatie's default, the honeypot fields are required here: a request
 * that omits them is treated as spam. Applied in the web group, it only acts on
 * the POST routes listed below and on routes with the "bot-protected" alias.
 */
class ProtectAgainstBots
{
    /**
     * @var list<string>
     */
    public const PROTECTED_ROUTES = [
        'login.store',
        'password.email',
        'password.update',
        'invitations.accept',
        'public.events.register.store',
    ];

    public function __construct(private readonly SpamProtection $honeypot) {}

    public function handle(Request $request, Closure $next, string $mode = 'auto'): Response
    {
        if ($request->isMethodSafe() || ($mode === 'auto' && ! $request->routeIs(...self::PROTECTED_ROUTES))) {
            return $next($request);
        }

        $this->checkHoneypot($request);
        $this->checkTurnstile($request);

        return $next($request);
    }

    private function checkHoneypot(Request $request): void
    {
        if (! config('honeypot.enabled')) {
            return;
        }

        $fields = $request->all();

        try {
            if (! array_key_exists(config('honeypot.valid_from_field_name'), $fields)) {
                throw new SpamException;
            }

            $this->honeypot->check($fields);
        } catch (SpamException) {
            throw ValidationException::withMessages(['form' => __('access.spam.rejected')]);
        }
    }

    private function checkTurnstile(Request $request): void
    {
        if (! config('turnstile.enabled')) {
            return;
        }

        $field = config('turnstile.field', 'cf-turnstile-response');
        $token = (string) $request->input($field, '');

        try {
            $passed = $token !== '' && (new Turnstile)->validate($token);
        } catch (Throwable) {
            $passed = false;
        }

        if (! $passed) {
            throw ValidationException::withMessages([$field => __('access.spam.turnstile_failed')]);
        }
    }
}
