<?php

namespace App\Http\Requests\Console;

use App\Domain\Finance\Models\Receipt;
use Brick\Money\Money;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * The edited split. ReceiptService checks the accounts and that the lines
 * add up exactly, in minor units.
 */
class SubmitReceiptRequest extends FormRequest
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
            'lines' => ['required', 'array', 'min:1', 'max:30'],
            'lines.*.account_id' => ['required', 'integer', Rule::exists('finance_accounts', 'id')],
            'lines.*.amount' => ['required', 'decimal:0,3', 'gt:0'],
        ];
    }

    /**
     * @return list<array{account_id: int, amount: Money}>
     */
    public function lines(Receipt $receipt): array
    {
        return array_map(fn (array $line): array => [
            'account_id' => (int) $line['account_id'],
            'amount' => Money::of((string) $line['amount'], $receipt->currency),
        ], $this->validated('lines'));
    }
}
