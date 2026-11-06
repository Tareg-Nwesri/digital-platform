<?php

namespace App\Http\Middleware;

use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Middleware;
use Spatie\Honeypot\Honeypot;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'auth' => [
                'user' => $request->user(),
                'impersonating' => $this->impersonating(),
            ],
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
            'honeypot' => fn (): array => (new Honeypot(config('honeypot')))->toArray(),
            'turnstile' => fn (): array => [
                'enabled' => (bool) config('turnstile.enabled'),
                'siteKey' => config('turnstile.sitekey'),
            ],
        ];
    }

    /**
     * Data for the "acting as" banner while the super admin impersonates someone.
     *
     * @return array{originalName: string}|null
     */
    private function impersonating(): ?array
    {
        $manager = app('impersonate');

        if (! $manager->isImpersonating()) {
            return null;
        }

        $original = User::query()->find($manager->getImpersonatorId());

        return $original === null ? null : ['originalName' => $original->name];
    }
}
