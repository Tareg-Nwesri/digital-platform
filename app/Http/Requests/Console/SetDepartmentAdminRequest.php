<?php

namespace App\Http\Requests\Console;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class SetDepartmentAdminRequest extends FormRequest
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
            'is_admin' => ['required', 'boolean'],
        ];
    }
}
