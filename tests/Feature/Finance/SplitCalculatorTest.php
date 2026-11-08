<?php

use App\Domain\Finance\Enums\AccountType;
use App\Domain\Finance\Models\FinanceAccount;
use App\Domain\Finance\Models\Sponsor;
use App\Domain\Finance\Models\Sponsorship;
use App\Domain\Finance\Services\FinanceAccounts;
use App\Domain\Finance\Services\SplitCalculator;
use Brick\Money\Money;

/*
 * LLD 07 §3 SplitCalculator: fixed rules first, percentages of what is
 * left, the remainder to the default account (else the general fund).
 * The lines always add up to the receipt exactly.
 */

beforeEach(function () {
    $accounts = app(FinanceAccounts::class);
    $this->general = $accounts->fund(AccountType::GeneralFund, 'LYD');
    $this->reinvestment = $accounts->fund(AccountType::ReinvestmentPool, 'LYD');
    $this->lab = $accounts->create(AccountType::EarmarkedFund, 'Robotics lab', null, 'LYD');
    $this->cup = $accounts->create(AccountType::EventBudget, 'Robot Cup 2027', null, 'LYD');

    $this->sponsorship = Sponsorship::factory()->for(Sponsor::factory())->create(['currency' => 'LYD']);
});

/**
 * @param  list<array{0: FinanceAccount, 1: ?string, 2: ?string}>  $rules  account, percent, fixed
 * @return array<string, string> account name => amount
 */
function split(string $amount, array $rules, ?FinanceAccount $default = null): array
{
    $sponsorship = test()->sponsorship;
    $sponsorship->update(['default_account_id' => $default?->id]);
    $sponsorship->splitRules()->delete();

    foreach ($rules as $position => [$account, $percent, $fixed]) {
        $sponsorship->splitRules()->create([
            'position' => $position,
            'account_id' => $account->id,
            'percent' => $percent,
            'fixed_minor' => $fixed === null ? null : Money::of($fixed, 'LYD')->getMinorAmount()->toInt(),
        ]);
    }

    $lines = app(SplitCalculator::class)->propose(Money::of($amount, 'LYD'), $sponsorship->fresh());

    $total = array_reduce($lines, fn (Money $sum, array $line) => $sum->plus($line['amount']), Money::zero('LYD'));
    expect((string) $total->getAmount())->toBe((string) Money::of($amount, 'LYD')->getAmount());

    return collect($lines)->mapWithKeys(fn (array $line) => [$line['account']->name_en => (string) $line['amount']->getAmount()])->all();
}

it('sends everything to the general fund without rules', function () {
    expect(split('1000', []))->toBe(['General fund' => '1000.000']);
});

it('sends the remainder to the sponsorship default account', function () {
    expect(split('1000', [[test()->lab, '30', null]], test()->cup))->toBe([
        'Robotics lab' => '300.000',
        'Robot Cup 2027' => '700.000',
    ]);
});

it('takes fixed amounts first and percentages of what is left', function () {
    expect(split('1000', [
        [test()->cup, null, '400'],
        [test()->lab, '50', null],
    ]))->toBe([
        'Robot Cup 2027' => '400.000',
        'Robotics lab' => '300.000',
        'General fund' => '300.000',
    ]);
});

it('never takes more than is there for fixed rules', function () {
    expect(split('250', [
        [test()->cup, null, '200'],
        [test()->lab, null, '200'],
        [test()->reinvestment, '50', null],
    ]))->toBe([
        'Robot Cup 2027' => '200.000',
        'Robotics lab' => '50.000',
    ]);
});

it('never loses a dirham to rounding', function (string $amount, array $expected) {
    expect(split($amount, [
        [test()->lab, '33.33', null],
        [test()->cup, '33.33', null],
    ], test()->reinvestment))->toBe($expected);
})->with([
    'even' => ['300', ['Robotics lab' => '99.990', 'Robot Cup 2027' => '99.990', 'Reinvestment pool' => '100.020']],
    'odd thousandths' => ['0.007', ['Robotics lab' => '0.003', 'Robot Cup 2027' => '0.002', 'Reinvestment pool' => '0.002']],
    'awkward' => ['100.001', ['Robotics lab' => '33.331', 'Robot Cup 2027' => '33.330', 'Reinvestment pool' => '33.340']],
]);

it('merges lines that go to the same account', function () {
    expect(split('1000', [
        [test()->lab, null, '100'],
        [test()->lab, '10', null],
    ]))->toBe([
        'Robotics lab' => '190.000',
        'General fund' => '810.000',
    ]);
});
