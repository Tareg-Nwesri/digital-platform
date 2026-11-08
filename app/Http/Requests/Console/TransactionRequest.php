<?php

namespace App\Http\Requests\Console;

use App\Domain\Finance\Models\FinanceAccount;
use Brick\Money\Money;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * A manual adjustment between two accounts (LLD 07 §6). The currency comes
 * from the accounts, which must match.
 */
class TransactionRequest extends FormRequest
{
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
            'date' => ['required', 'date'],
            'from_account_id' => ['required', 'integer', Rule::exists('finance_accounts', 'id')->where('is_archived', false)],
            'to_account_id' => ['required', 'integer', 'different:from_account_id', Rule::exists('finance_accounts', 'id')->where('is_archived', false)],
            'amount' => ['required', 'decimal:0,3', 'gt:0', 'max:999999999'],
            'rate_to_lyd' => ['nullable', 'numeric', 'gt:0'],
            'description' => ['required', 'string', 'max:500'],
            'reference' => ['nullable', 'string', 'max:120'],
        ];
    }

    /**
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $from = $this->fromAccount();
                $to = FinanceAccount::query()->find($this->integer('to_account_id'));

                if ($from->currency !== $to?->currency) {
                    $validator->errors()->add('to_account_id', __('finance.errors.currency_mismatch'));
                } elseif ($from->currency !== 'LYD' && ! filled($this->input('rate_to_lyd'))) {
                    $validator->errors()->add('rate_to_lyd', __('finance.errors.rate_required'));
                }
            },
        ];
    }

    public function fromAccount(): FinanceAccount
    {
        return FinanceAccount::query()->findOrFail($this->integer('from_account_id'));
    }

    public function amount(): Money
    {
        return Money::of((string) $this->validated('amount'), $this->fromAccount()->currency);
    }
}
