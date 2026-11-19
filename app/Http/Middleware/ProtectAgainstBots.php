<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
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
        'public.contact.store',
    ];

    /**
     * The public forms that also ask Cloudflare Turnstile; sign-in and the
     * account forms rely on the honeypot (and Fortify's login throttle).
     *
     * @var list<string>
     */
    public const TURNSTILE_ROUTES = [
        'public.events.register.store',
        'public.contact.store',
    ];

    /**
     * The Inertia pages with those forms (<BotGuard with-turnstile>): the root
     * template starts loading Turnstile's script with these pages, so the
     * check is under way before the Vue app has even booted.
     *
     * @var list<string>
     */
    public const TURNSTILE_PAGES = [
        'public/Contact',
        'public/events/Register',
    ];

    public function __construct(private readonly SpamProtection $honeypot) {}

    public function handle(Request $request, Closure $next, string $mode = 'auto'): Response
    {
        if ($request->isMethodSafe() || ($mode === 'auto' && ! $request->routeIs(...self::PROTECTED_ROUTES))) {
            return $next($request);
        }

        $this->checkHoneypot($request);

        if ($request->routeIs(...self::TURNSTILE_ROUTES)) {
            $this->checkTurnstile($request);
        }

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

        if ($token === '' || ! $this->turnstileAccepts($token)) {
            throw ValidationException::withMessages([$field => __('access.spam.turnstile_failed')]);
        }
    }

    /**
     * Asks Cloudflare whether the token is genuine. The package's own call
     * has no timeout and four attempts, so a slow route to Cloudflare could
     * hold a sign-in for minutes; this one gives up within seconds and fails
     * closed.
     */
    private function turnstileAccepts(string $token): bool
    {
        try {
            return Http::asForm()
                ->acceptJson()
                ->connectTimeout(3)
                ->timeout(5)
                ->retry(2, 200, throw: false)
                ->post(config('turnstile.url'), [
                    'secret' => config('turnstile.secretkey'),
                    'response' => $token,
                ])
                ->json('success') === true;
        } catch (Throwable) {
            return false;
        }
    }
}
