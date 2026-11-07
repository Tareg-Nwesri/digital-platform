<?php

namespace App\Http\Requests\Console;

use App\Domain\Access\Enums\DepartmentRole;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDepartmentInvitationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('manageMembers', $this->route('department'));
    }

    /**
     * Only the super admin may invite another department admin (LLD 02 §6).
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $roles = $this->user()->isSuperAdmin()
            ? DepartmentRole::values()
            : [DepartmentRole::Member->value];

        return [
            'email' => ['required', 'email:rfc', 'max:190'],
            'role' => ['required', Rule::in($roles)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'role.in' => __('access.invitations.invalid_role'),
        ];
    }
}
