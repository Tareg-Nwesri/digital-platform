<?php

namespace App\Http\Responses;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

/**
 * One translated Vue error page for 403, 404, 419, 429, 500 and 503 (SRS UI-07).
 * Exception messages are never passed to the page.
 */
final class ErrorPage
{
    /**
     * @var list<int>
     */
    public const HANDLED = [403, 404, 419, 429, 500, 503];

    public static function shouldRender(Request $request, int $status): bool
    {
        if ($request->expectsJson() && ! $request->header('X-Inertia')) {
            return false;
        }

        if ($status === 500) {
            return ! config('app.debug');
        }

        return in_array($status, self::HANDLED, true);
    }

    public static function render(Request $request, int $status): Response
    {
        $key = in_array($status, self::HANDLED, true) ? (string) $status : '500';

        return Inertia::render('Error', [
            'status' => $status,
            'title' => __("errors.{$key}.title"),
            'message' => __("errors.{$key}.message"),
            'homeUrl' => $request->user() !== null ? route('hub') : route('home'),
            'homeLabel' => $request->user() !== null ? __('errors.back_to_hub') : __('errors.back_home'),
        ])->toResponse($request)->setStatusCode($status);
    }
}
