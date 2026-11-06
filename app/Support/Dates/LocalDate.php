<?php

namespace App\Support\Dates;

use Carbon\CarbonInterface;

final class LocalDate
{
    /**
     * Format a date with the current locale's month and day names but Western digits.
     */
    public static function format(?CarbonInterface $date, string $format = 'j M Y', ?string $locale = null): ?string
    {
        if ($date === null) {
            return null;
        }

        $formatted = $date->copy()->locale($locale ?? app()->getLocale())->translatedFormat($format);

        return strtr($formatted, [
            '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4',
            '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
        ]);
    }
}
