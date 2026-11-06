<?php

namespace App\Http\Requests\Auth;

use App\Domain\Access\Services\InvitationService;
use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class AcceptInvitationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * New invitees set their name and password; an existing account confirms its password
     * when the invitee is not already signed in.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $invitation = app(InvitationService::class)->findPendingByToken((string) $this->route('token'));
        $existing = User::query()->where('email', $invitation?->email)->exists();

        if ($existing) {
            return $this->user()?->email === $invitation?->email
                ? []
                : ['current_password' => ['required', 'string']];
        }

        return [
            'name_en' => ['required', 'string', 'max:120'],
            'name_ar' => ['nullable', 'string', 'max:120'],
            'password' => ['required', 'confirmed', Password::min(10)->letters()->numbers()],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'current_password.required' => __('access.invitations.password_to_confirm'),
        ];
    }
}
