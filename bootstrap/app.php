<?php

use App\Http\Middleware\EnsureNotImpersonating;
use App\Http\Middleware\EnsureSuperAdmin;
use App\Http\Middleware\EnsureTeamActive;
use App\Http\Middleware\EnsureTwoFactorWhenRequired;
use App\Http\Middleware\HandleAppearance;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\ProtectAgainstBots;
use App\Http\Middleware\ResolveWorkspace;
use App\Http\Middleware\SetLocale;
use App\Http\Responses\ErrorPage;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->encryptCookies(except: ['appearance', 'sidebar_state', 'locale']);

        $middleware->alias([
            'workspace' => ResolveWorkspace::class,
            'team.active' => EnsureTeamActive::class,
            'super-admin' => EnsureSuperAdmin::class,
            'not-impersonating' => EnsureNotImpersonating::class,
            'bot-protected' => ProtectAgainstBots::class,
        ]);

        $middleware->web(append: [
            HandleAppearance::class,
            SetLocale::class,
            EnsureTwoFactorWhenRequired::class,
            ProtectAgainstBots::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        $exceptions->respond(function (Response $response, Throwable $exception, Request $request): Response {
            return ErrorPage::shouldRender($request, $response->getStatusCode())
                ? ErrorPage::render($request, $response->getStatusCode())
                : $response;
        });
    })->create();
