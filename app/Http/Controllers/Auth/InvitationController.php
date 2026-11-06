<?php

namespace App\Http\Controllers\Auth;

use App\Domain\Access\Models\Invitation;
use App\Domain\Access\Services\InvitationService;
use App\Exceptions\DomainRuleViolation;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\AcceptInvitationRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class InvitationController extends Controller
{
    public function __construct(private readonly InvitationService $invitations) {}

    public function show(Request $request, string $token): Response
    {
        $invitation = $this->pendingOrFail($token);
        $owner = $invitation->owner();

        return Inertia::render('auth/Invitation', [
            'token' => $token,
            'invitation' => [
                'email' => $invitation->email,
                'role' => $invitation->role,
                'roleLabel' => __('access.roles.'.$invitation->role),
                'workspaceType' => $invitation->workspace->type->value,
                'workspaceName' => $owner?->name ?? $owner?->name_en,
                'expiresAt' => $invitation->expires_at->toDateString(),
            ],
            'existingAccount' => User::query()->where('email', $invitation->email)->exists(),
            'signedInAsInvitee' => $request->user()?->email === $invitation->email,
        ]);
    }

    public function accept(AcceptInvitationRequest $request, string $token): RedirectResponse
    {
        $invitation = $this->pendingOrFail($token);
        $existing = User::query()->where('email', $invitation->email)->first();

        if ($existing !== null && ($request->user() === null || $request->user()->isNot($existing))) {
            if (! $existing->can_login || ! Hash::check((string) $request->input('current_password'), (string) $existing->password)) {
                throw ValidationException::withMessages(['current_password' => __('auth.password')]);
            }
        }

        try {
            $user = $this->invitations->accept($invitation, $existing, $request->safe()->only(['name_en', 'name_ar', 'password']));
        } catch (DomainRuleViolation $violation) {
            throw $violation->toValidationException();
        }

        Auth::login($user);
        $request->session()->regenerate();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('access.invitations.accepted')]);

        return redirect()->route('dashboard');
    }

    private function pendingOrFail(string $token): Invitation
    {
        $invitation = $this->invitations->findPendingByToken($token);

        abort_if($invitation === null, 404, __('access.invitations.invalid'));

        return $invitation;
    }
}
