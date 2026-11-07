<?php

namespace App\Http\Requests\Console;

use App\Support\Enums\Currency;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * The full list of a part's approved offers; rows left out are removed.
 */
class SyncOffersRequest extends FormRequest
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
        return [
            'offers' => ['present', 'array', 'max:50'],
            'offers.*.id' => ['nullable', 'integer'],
            ...self::fieldRules('offers.*.'),
        ];
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public static function fieldRules(string $prefix = ''): array
    {
        return [
            $prefix.'vendor_id' => ['required', 'integer', Rule::exists('vendors', 'id')],
            $prefix.'price' => ['required', 'decimal:0,3', 'min:0', 'max:99999999'],
            $prefix.'currency' => ['required', Rule::in(Currency::values())],
            $prefix.'url' => ['nullable', 'url:http,https', 'max:500'],
            $prefix.'in_stock' => ['sometimes', 'boolean'],
            $prefix.'checked_at' => ['nullable', 'date'],
        ];
    }
}
