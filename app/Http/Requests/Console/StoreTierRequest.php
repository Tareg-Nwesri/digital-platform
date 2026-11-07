<?php

namespace App\Http\Requests\Console;

use App\Domain\Teams\Enums\TeamFeature;
use App\Domain\Teams\Models\Tier;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator as ValidatorInstance;

/**
 * Features are sent as a list of {name, value}, because feature names
 * contain dots that validation would read as nesting.
 */
class StoreTierRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->isSuperAdmin();
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name_en' => ['required', 'string', 'max:80'],
            'name_ar' => ['nullable', 'string', 'max:80'],
            'slug' => ['nullable', 'string', 'alpha_dash:ascii', 'max:80', Rule::unique(Tier::class, 'slug')->ignore($this->route('tier'))],
            'features' => ['required', 'array'],
            'features.*.name' => ['required', 'string', 'distinct', Rule::in(TeamFeature::names())],
            'features.*.value' => ['present'],
        ];
    }

    /**
     * A member limit is 1–500; every other feature is on or off.
     *
     * @return list<callable(ValidatorInstance): void>
     */
    public function after(): array
    {
        return [
            function (ValidatorInstance $validator): void {
                foreach ((array) $this->input('features', []) as $index => $item) {
                    $feature = TeamFeature::tryFrom((string) ($item['name'] ?? ''));

                    if ($feature === null) {
                        continue;
                    }

                    $rules = $feature->isLimit() ? ['required', 'integer', 'min:1', 'max:500'] : ['required', 'boolean'];
                    $check = Validator::make(['value' => $item['value'] ?? null], ['value' => $rules]);

                    if ($check->fails()) {
                        $validator->errors()->add("features.{$index}.value", $check->errors()->first('value'));
                    }
                }
            },
        ];
    }

    /**
     * @return array{name_en: string, name_ar: string|null, slug: string|null, features: array<string, mixed>}
     */
    public function tierData(): array
    {
        $validated = $this->validated();

        return [
            'name_en' => $validated['name_en'],
            'name_ar' => $validated['name_ar'] ?? null,
            'slug' => $validated['slug'] ?? null,
            'features' => collect($validated['features'])->pluck('value', 'name')->all(),
        ];
    }
}
