<?php

namespace App\Http\Requests\Console;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class VendorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('manage-catalogue');
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return self::fieldRules();
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public static function fieldRules(string $prefix = ''): array
    {
        return [
            $prefix.'name' => ['required', 'string', 'max:160'],
            $prefix.'website' => ['nullable', 'url:http,https', 'max:255'],
            $prefix.'email' => ['nullable', 'email:rfc', 'max:190'],
            $prefix.'phone' => ['nullable', 'string', 'max:32'],
            $prefix.'city' => ['nullable', 'string', 'max:120'],
            $prefix.'address' => ['nullable', 'string', 'max:255'],
            $prefix.'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            $prefix.'longitude' => ['nullable', 'numeric', 'between:-180,180'],
        ];
    }
}
