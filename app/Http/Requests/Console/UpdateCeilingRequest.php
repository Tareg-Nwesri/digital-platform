<?php

namespace App\Http\Requests\Console;

use App\Domain\Organisation\Services\PermissionCatalogue;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCeilingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('setCeiling', $this->route('department'));
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'permissions' => ['present', 'array'],
            'permissions.*' => ['string', 'distinct', Rule::in(app(PermissionCatalogue::class)->delegable()->all())],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'permissions.*.in' => __('organisation.errors.not_delegable', ['permission' => ':input']),
        ];
    }
}
