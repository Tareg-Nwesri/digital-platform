<?php

namespace App\Http\Requests\Console;

use App\Domain\Organisation\Services\DepartmentAccess;
use App\Domain\Teams\Models\Team;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTeamRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Team::class);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'slug' => ['nullable', 'alpha_dash:ascii', 'max:80', Rule::unique('teams', 'slug')],
            'tier_id' => ['required', 'integer', Rule::exists('tiers', 'id')],
            'department_id' => $this->departmentRules(),
            'location' => ['nullable', 'string', 'max:120'],
            'captain_email' => ['nullable', 'email:rfc', 'max:190'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'department_id.required' => __('teams.errors.department_required'),
            'department_id.in' => __('teams.errors.department_not_allowed'),
        ];
    }

    /**
     * Staff must place the team under a department where they hold teams.edit;
     * the super admin may also leave it unsupervised.
     *
     * @return list<mixed>
     */
    protected function departmentRules(): array
    {
        if ($this->user()->isSuperAdmin()) {
            return ['nullable', 'integer', Rule::exists('departments', 'id')];
        }

        return ['required', 'integer', Rule::in(app(DepartmentAccess::class)->departmentIdsWith($this->user(), 'teams.edit'))];
    }
}
