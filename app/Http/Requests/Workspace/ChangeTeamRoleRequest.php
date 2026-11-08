<?php

namespace App\Http\Requests\Workspace;

use App\Domain\Teams\Enums\TeamRole;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ChangeTeamRoleRequest extends FormRequest
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
            'role' => ['required', Rule::in([TeamRole::Coach->value, TeamRole::Member->value, TeamRole::Viewer->value])],
        ];
    }
}
