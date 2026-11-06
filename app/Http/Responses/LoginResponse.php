<?php

namespace App\Http\Responses;

use App\Domain\Access\Services\MembershipDirectory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Laravel\Fortify\Contracts\LoginResponse as LoginResponseContract;
use Laravel\Fortify\Contracts\TwoFactorLoginResponse as TwoFactorLoginResponseContract;

/**
 * Super admin → console; one workspace → that workspace; otherwise the hub.
 */
class LoginResponse implements LoginResponseContract, TwoFactorLoginResponseContract
{
    public function __construct(private readonly MembershipDirectory $directory) {}

    public function toResponse($request): JsonResponse|RedirectResponse
    {
        $user = $request->user();
        $user->forceFill(['last_login_at' => now()])->saveQuietly();

        if ($request->wantsJson()) {
            return new JsonResponse(['two_factor' => false], 200);
        }

        return redirect()->intended($this->directory->homeFor($user));
    }
}
