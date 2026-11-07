<?php

namespace App\Http\Requests\Console;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AssignTeamTierRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('assignTier', $this->route('team'));
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'tier_id' => ['required', 'integer', Rule::exists('tiers', 'id')],
        ];
    }
}
