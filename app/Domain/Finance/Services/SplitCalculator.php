<?php

namespace App\Domain\Finance\Services;

use App\Domain\Finance\Enums\AccountType;
use App\Domain\Finance\Models\FinanceAccount;
use App\Domain\Finance\Models\SplitRule;
use App\Domain\Finance\Models\Sponsorship;
use Brick\Money\AllocationMode;
use Brick\Money\Money;

/**
 * Proposes how incoming money is divided (LLD 07 §3). Pure apart from
 * finding the general fund. The lines always add up to the amount exactly:
 * brick/money's allocate() gives leftover minor units to the first parts.
 */
final class SplitCalculator
{
    public function __construct(private readonly FinanceAccounts $accounts) {}

    /**
     * @return list<array{account: FinanceAccount, amount: Money}>
     */
    public function propose(Money $amount, ?Sponsorship $sponsorship): array
    {
        $currency = $amount->getCurrency()->getCurrencyCode();
        $remaining = $amount;
        $lines = [];
        $rules = $sponsorship?->splitRules()->with('account')->get() ?? collect();

        foreach ($rules->whereNotNull('fixed_minor') as $rule) {
            if (! $remaining->isPositive()) {
                break;
            }

            $take = Money::ofMinor($rule->fixed_minor, $currency);
            $take = $take->isGreaterThan($remaining) ? $remaining : $take;
            $lines[] = ['account' => $rule->account, 'amount' => $take];
            $remaining = $remaining->minus($take);
        }

        $percentRules = $rules->whereNotNull('percent')->values();

        if ($remaining->isPositive() && $percentRules->isNotEmpty()) {
            $weights = $percentRules->map(fn (SplitRule $rule): int => (int) round((float) $rule->percent * 100))->all();
            $rest = max(0, 10_000 - array_sum($weights));
            $parts = $remaining->allocate([...$weights, $rest], AllocationMode::FloorToFirst);

            foreach ($percentRules as $index => $rule) {
                $lines[] = ['account' => $rule->account, 'amount' => $parts[$index]];
            }

            $remaining = $parts[count($weights)];
        }

        if ($remaining->isPositive()) {
            $lines[] = [
                'account' => $sponsorship?->defaultAccount ?? $this->accounts->fund(AccountType::GeneralFund, $currency),
                'amount' => $remaining,
            ];
        }

        return $this->merge($lines);
    }

    /**
     * @param  list<array{account: FinanceAccount, amount: Money}>  $lines
     * @return list<array{account: FinanceAccount, amount: Money}>
     */
    private function merge(array $lines): array
    {
        $merged = [];

        foreach ($lines as $line) {
            if ($line['amount']->isZero()) {
                continue;
            }

            $key = $line['account']->getKey();
            $merged[$key] = isset($merged[$key])
                ? ['account' => $merged[$key]['account'], 'amount' => $merged[$key]['amount']->plus($line['amount'])]
                : $line;
        }

        return array_values($merged);
    }
}
