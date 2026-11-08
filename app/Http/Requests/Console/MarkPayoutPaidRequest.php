<?php

namespace App\Http\Requests\Console;

use App\Domain\Finance\Enums\PaymentMethod;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MarkPayoutPaidRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('approve-finance');
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'method' => ['required', Rule::enum(PaymentMethod::class)->except([PaymentMethod::InKind])],
            'reference' => ['required', 'string', 'max:120'],
            'paid_on' => ['required', 'date', 'before_or_equal:today'],
        ];
    }
}
