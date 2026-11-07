<?php

namespace App\Http\Requests\Console;

use App\Domain\Teams\Enums\TeamFeature;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator as ValidatorInstance;

/**
 * Overrides are sent as a list of {name, value}; a null value clears the
 * override so the team falls back to its tier (LLD 03 §8).
 */
class UpdateTeamFeaturesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('overrideFeatures', $this->route('team'));
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'features' => ['present', 'array'],
            'features.*.name' => ['required', 'string', 'distinct', Rule::in(TeamFeature::names())],
            'features.*.value' => ['nullable'],
        ];
    }

    /**
     * Each value is checked against its feature's type: a member limit of
     * 1–500, otherwise a boolean.
     *
     * @return list<callable(ValidatorInstance): void>
     */
    public function after(): array
    {
        return [
            function (ValidatorInstance $validator): void {
                foreach ((array) $this->input('features', []) as $index => $item) {
                    $feature = TeamFeature::tryFrom((string) ($item['name'] ?? ''));
                    $value = $item['value'] ?? null;

                    if ($feature === null || $value === null) {
                        continue;
                    }

                    $rules = $feature->isLimit() ? ['integer', 'min:1', 'max:500'] : ['boolean'];
                    $check = Validator::make(['value' => $value], ['value' => $rules]);

                    if ($check->fails()) {
                        $validator->errors()->add("features.{$index}.value", $check->errors()->first('value'));
                    }
                }
            },
        ];
    }

    /**
     * @return list<array{feature: TeamFeature, value: bool|int|null}>
     */
    public function overrides(): array
    {
        return collect($this->validated('features'))
            ->map(function (array $item): array {
                $feature = TeamFeature::from($item['name']);
                $value = $item['value'] ?? null;

                return ['feature' => $feature, 'value' => $value === null ? null : $feature->normalize($value)];
            })
            ->all();
    }
}
