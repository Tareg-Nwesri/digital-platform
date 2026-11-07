<?php

namespace App\Http\Requests\Console;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ToggleUserActiveRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('toggle-active', $this->route('user'));
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'active' => ['required', 'boolean'],
        ];
    }
}
