<?php

use App\Support\Dates\LocalDate;
use App\Support\Enums\Currency;
use App\Support\Money\MoneyFormatter;
use Brick\Money\Money;
use Carbon\CarbonImmutable;

it('formats money with grouped western digits and the iso code', function (int $minor, string $currency, string $expected) {
    expect(MoneyFormatter::format(Money::ofMinor($minor, $currency)))->toBe($expected);
})->with([
    'LYD three decimals' => [1250500, 'LYD', '1,250.500 LYD'],
    'USD two decimals' => [99, 'USD', '0.99 USD'],
    'large LYD' => [1234567890, 'LYD', '1,234,567.890 LYD'],
    'negative' => [-5000, 'LYD', '-5.000 LYD'],
]);

it('exposes the money dto shape used by vue', function () {
    expect(MoneyFormatter::toArray(Money::ofMinor(1500, 'LYD')))->toBe([
        'amountMinor' => 1500,
        'currency' => 'LYD',
        'formatted' => '1.500 LYD',
    ]);
});

it('knows the iso scale of each supported currency', function () {
    expect(Currency::LYD->scale())->toBe(3)
        ->and(Currency::USD->scale())->toBe(2)
        ->and(Money::ofMinor(1, 'LYD')->getCurrency()->getDefaultFractionDigits())->toBe(Currency::LYD->scale());
});

it('formats arabic dates with arabic month names and western digits', function () {
    $formatted = LocalDate::format(CarbonImmutable::parse('2026-11-06'), 'j F Y', 'ar');

    expect($formatted)->toContain('2026')->toContain('6')
        ->and(preg_match('/[٠-٩]/u', $formatted))->toBe(0)
        ->and($formatted)->not->toContain('November');
});
