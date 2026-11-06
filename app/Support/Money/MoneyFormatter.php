<?php

namespace App\Support\Money;

use Brick\Money\Money;

final class MoneyFormatter
{
    /**
     * Format with grouped Western digits and the ISO code, e.g. "1,250.500 LYD".
     */
    public static function format(Money $money, bool $withCurrency = true): string
    {
        $amount = (string) $money->getAmount();
        $negative = str_starts_with($amount, '-');
        [$whole, $fraction] = array_pad(explode('.', ltrim($amount, '-')), 2, '');

        $grouped = strrev(implode(',', str_split(strrev($whole), 3)));
        $formatted = ($negative ? '-' : '').$grouped.($fraction !== '' ? '.'.$fraction : '');

        return $withCurrency ? $formatted.' '.$money->getCurrency()->getCurrencyCode() : $formatted;
    }

    /**
     * The shape sent to Vue as MoneyDTO.
     *
     * @return array{amountMinor: int, currency: string, formatted: string}
     */
    public static function toArray(Money $money): array
    {
        return [
            'amountMinor' => $money->getMinorAmount()->toInt(),
            'currency' => $money->getCurrency()->getCurrencyCode(),
            'formatted' => self::format($money),
        ];
    }
}
