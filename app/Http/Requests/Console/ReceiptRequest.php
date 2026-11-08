<?php

namespace App\Http\Requests\Console;

use App\Domain\Finance\Enums\IncomeSource;
use App\Domain\Finance\Enums\PaymentMethod;
use App\Domain\Finance\Enums\SponsorshipStatus;
use App\Support\Enums\Currency;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * A receipt (LLD 07 §6). Sponsorship receipts take the payer from the
 * sponsor; other income names the payer. In-kind receipts list items.
 */
class ReceiptRequest extends FormRequest
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
        $inKind = $this->input('method') === PaymentMethod::InKind->value;

        return [
            'sponsorship_id' => ['nullable', 'integer', Rule::exists('sponsorships', 'id')->where('status', SponsorshipStatus::Active->value)],
            'source' => ['required', Rule::enum(IncomeSource::class)],
            'payer_name' => ['required_without:sponsorship_id', 'nullable', 'string', 'max:160'],
            'date' => ['required', 'date', 'before_or_equal:today'],
            'amount' => [$inKind ? 'nullable' : 'required', 'decimal:0,3', 'gt:0', 'max:999999999'],
            'currency' => ['required', Rule::in(Currency::values())],
            'rate_to_lyd' => ['nullable', 'required_unless:currency,LYD', 'numeric', 'gt:0'],
            'method' => ['required', Rule::enum(PaymentMethod::class)],
            'reference' => ['nullable', 'string', 'max:120'],
            'in_kind_items' => [$inKind ? 'required' : 'nullable', 'array', 'min:1', 'max:50'],
            'in_kind_items.*.description' => ['required', 'string', 'max:200'],
            'in_kind_items.*.quantity' => ['required', 'integer', 'min:1', 'max:100000'],
            'in_kind_items.*.value' => ['required', 'decimal:0,3', 'gt:0'],
        ];
    }
}
