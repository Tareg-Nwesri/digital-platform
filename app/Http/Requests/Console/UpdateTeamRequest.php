<?php

namespace App\Http\Requests\Console;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

class UpdateTeamRequest extends StoreTeamRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('team'));
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'slug' => ['nullable', 'alpha_dash:ascii', 'max:80', Rule::unique('teams', 'slug')->ignore($this->route('team'))],
            'department_id' => $this->departmentRules(),
            'location' => ['nullable', 'string', 'max:120'],
        ];
    }
}
