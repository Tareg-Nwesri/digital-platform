<?php

namespace App\Http\Requests\Workspace;

use App\Domain\Teams\Enums\TeamRole;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Invite someone to the team. Captaincy is only ever handed over, never
 * granted by invitation.
 */
class InviteTeamMemberRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('members.invite');
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'email:rfc', 'max:190'],
            'role' => ['required', Rule::in([TeamRole::Coach->value, TeamRole::Member->value, TeamRole::Viewer->value])],
        ];
    }
}
