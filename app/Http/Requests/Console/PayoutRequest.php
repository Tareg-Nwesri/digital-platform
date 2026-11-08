<?php

namespace App\Http\Requests\Console;

use App\Domain\Finance\Enums\PayoutType;
use App\Support\Enums\Currency;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * A payout draft (LLD 07 §6). PayoutService checks the source account and
 * guardian details for payees under 18.
 */
class PayoutRequest extends FormRequest
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
            'user_id' => ['required', 'integer', Rule::exists('users', 'id')->whereNull('deleted_at')],
            'type' => ['required', Rule::enum(PayoutType::class)],
            'amount' => ['required', 'decimal:0,3', 'gt:0', 'max:999999999'],
            'currency' => ['required', Rule::in(Currency::values())],
            'rate_to_lyd' => ['nullable', 'required_unless:currency,LYD', 'numeric', 'gt:0'],
            'source_account_id' => ['required', 'integer', Rule::exists('finance_accounts', 'id')->where('is_archived', false)],
            'reason' => ['required', 'string', 'max:500'],
            'guardian_name' => ['nullable', 'string', 'max:160'],
            'guardian_contact' => ['nullable', 'string', 'max:160'],
        ];
    }
}
