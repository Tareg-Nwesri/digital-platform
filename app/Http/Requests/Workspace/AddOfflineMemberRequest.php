<?php

namespace App\Http\Requests\Workspace;

use App\Domain\Teams\Enums\TeamRole;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * A member without a login (often a young student). The birthdate is
 * needed for event age rules.
 */
class AddOfflineMemberRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('members.manage');
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name_en' => ['required', 'string', 'max:120'],
            'name_ar' => ['nullable', 'string', 'max:120'],
            'birthdate' => ['required', 'date', 'before:today', 'after:1920-01-01'],
            'role' => ['required', Rule::in(array_values(array_map(
                fn (TeamRole $role): string => $role->value,
                array_filter(TeamRole::cases(), fn (TeamRole $role): bool => $role->allowedWithoutLogin()),
            )))],
        ];
    }
}
