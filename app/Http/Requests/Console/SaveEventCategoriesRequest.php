<?php

namespace App\Http\Requests\Console;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class SaveEventCategoriesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('event'));
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'categories' => ['required', 'array', 'min:1', 'max:30'],
            'categories.*.id' => ['nullable', 'integer'],
            'categories.*.name_en' => ['required', 'string', 'max:120'],
            'categories.*.name_ar' => ['nullable', 'string', 'max:120'],
            'categories.*.age_min' => ['nullable', 'integer', 'between:3,99'],
            'categories.*.age_max' => ['nullable', 'integer', 'between:3,99'],
            'categories.*.members_min' => ['required', 'integer', 'between:1,20'],
            'categories.*.members_max' => ['required', 'integer', 'between:1,20'],
            'categories.*.robots_per_team' => ['required', 'integer', 'between:0,10'],
            'categories.*.capacity' => ['nullable', 'integer', 'between:1,1000'],
            'categories.*.max_weight_g' => ['nullable', 'integer', 'min:1'],
            'categories.*.max_length_mm' => ['nullable', 'integer', 'min:1'],
            'categories.*.max_width_mm' => ['nullable', 'integer', 'min:1'],
            'categories.*.max_height_mm' => ['nullable', 'integer', 'min:1'],
        ];
    }

    /**
     * Maximums may not be below their minimums.
     *
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                foreach ((array) $this->input('categories', []) as $index => $category) {
                    foreach (['members', 'age'] as $field) {
                        $min = $category[$field.'_min'] ?? null;
                        $max = $category[$field.'_max'] ?? null;

                        if (is_numeric($min) && is_numeric($max) && (int) $max < (int) $min) {
                            $validator->errors()->add("categories.{$index}.{$field}_max", __('events.errors.max_below_min'));
                        }
                    }
                }
            },
        ];
    }
}
