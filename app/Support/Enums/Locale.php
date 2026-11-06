<?php

namespace App\Support\Enums;

enum Locale: string
{
    case En = 'en';
    case Ar = 'ar';

    public function dir(): string
    {
        return $this === self::Ar ? 'rtl' : 'ltr';
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
