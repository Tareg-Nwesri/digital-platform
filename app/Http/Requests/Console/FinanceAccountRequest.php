<?php

namespace App\Http\Requests\Console;

use App\Domain\Finance\Enums\AccountType;
use App\Support\Enums\Currency;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Staff open earmarked funds and event budgets by hand. Shared funds,
 * team and project budgets and external accounts are created by the
 * system when needed.
 */
class FinanceAccountRequest extends FormRequest
{
    public const CREATABLE = [AccountType::EarmarkedFund, AccountType::EventBudget];

    public function authorize(): bool
    {
        return $this->user()->can('edit-finance');
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'type' => ['required', Rule::in(array_map(fn (AccountType $type): string => $type->value, self::CREATABLE))],
            'name_en' => ['required', 'string', 'max:160'],
            'name_ar' => ['nullable', 'string', 'max:160'],
            'currency' => ['required', Rule::in(Currency::values())],
            'purpose' => ['nullable', 'string', 'max:500'],
        ];
    }
}
