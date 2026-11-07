<?php

namespace App\Http\Requests\Console;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PartRequest extends FormRequest
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
     * Shared with the review dialog, which edits a suggestion before approval.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public static function fieldRules(string $prefix = ''): array
    {
        return [
            $prefix.'name' => ['required', 'string', 'max:160'],
            $prefix.'part_number' => ['nullable', 'string', 'max:80'],
            $prefix.'category_id' => ['nullable', 'integer', Rule::exists('part_categories', 'id')],
            $prefix.'description_en' => ['nullable', 'string', 'max:5000'],
            $prefix.'description_ar' => ['nullable', 'string', 'max:5000'],
            $prefix.'specs' => ['nullable', 'array', 'max:40'],
            $prefix.'specs.*.key' => ['required', 'string', 'max:60'],
            $prefix.'specs.*.value' => ['required', 'string', 'max:120'],
            $prefix.'specs.*.unit' => ['nullable', 'string', 'max:20'],
        ];
    }
}
