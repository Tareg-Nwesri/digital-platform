<?php

namespace App\Http\Requests\Console;

use App\Domain\Finance\Enums\SponsorshipKind;
use App\Domain\Finance\Enums\SponsorshipStatus;
use App\Domain\Finance\Models\FinanceAccount;
use App\Support\Enums\Currency;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * A sponsorship and its split rules (LLD 07 §6): each rule targets an open
 * foundation account in the sponsorship's currency and has exactly one of
 * percent or fixed; percentages total at most 100.
 */
class SponsorshipRequest extends FormRequest
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
            'title' => ['required', 'string', 'max:160'],
            'kind' => ['required', Rule::enum(SponsorshipKind::class)],
            'pledged' => ['required', 'decimal:0,3', 'min:0', 'max:999999999'],
            'currency' => ['required', Rule::in(Currency::values())],
            'starts_on' => ['nullable', 'date'],
            'ends_on' => ['nullable', 'date', 'after_or_equal:starts_on'],
            'purpose' => ['nullable', 'string', 'max:5000'],
            'restrictions' => ['nullable', 'string', 'max:5000'],
            'status' => ['required', Rule::enum(SponsorshipStatus::class)],
            'default_account_id' => ['nullable', 'integer', Rule::exists('finance_accounts', 'id')],
            'rules' => ['present', 'array', 'max:20'],
            'rules.*.account_id' => ['required', 'integer', Rule::exists('finance_accounts', 'id')],
            'rules.*.percent' => ['nullable', 'decimal:0,2', 'gt:0', 'max:100'],
            'rules.*.fixed' => ['nullable', 'decimal:0,3', 'gt:0'],
        ];
    }

    /**
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $currency = (string) $this->input('currency');
                $percentTotal = 0.0;

                foreach ((array) $this->input('rules', []) as $index => $rule) {
                    $hasPercent = filled($rule['percent'] ?? null);
                    $hasFixed = filled($rule['fixed'] ?? null);

                    if ($hasPercent === $hasFixed) {
                        $validator->errors()->add("rules.{$index}.percent", __('finance.errors.rule_one_kind'));
                    }

                    $percentTotal += $hasPercent ? (float) $rule['percent'] : 0;

                    $account = FinanceAccount::query()->find($rule['account_id'] ?? null);

                    if ($account !== null && (! $account->type->isInternal() || $account->currency !== $currency || $account->is_archived)) {
                        $validator->errors()->add("rules.{$index}.account_id", __('finance.errors.split_account'));
                    }
                }

                if ($percentTotal > 100) {
                    $validator->errors()->add('rules', __('finance.errors.percent_total'));
                }

                $default = FinanceAccount::query()->find($this->input('default_account_id'));

                if ($default !== null && (! $default->type->isInternal() || $default->currency !== $currency)) {
                    $validator->errors()->add('default_account_id', __('finance.errors.split_account'));
                }
            },
        ];
    }
}
