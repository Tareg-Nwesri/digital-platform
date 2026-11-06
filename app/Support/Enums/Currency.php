<?php

namespace App\Support\Enums;

enum Currency: string
{
    case LYD = 'LYD';
    case USD = 'USD';
    case EUR = 'EUR';

    /**
     * Number of decimal places for the currency (ISO 4217 minor units).
     */
    public function scale(): int
    {
        return match ($this) {
            self::LYD => 3,
            self::USD, self::EUR => 2,
        };
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
