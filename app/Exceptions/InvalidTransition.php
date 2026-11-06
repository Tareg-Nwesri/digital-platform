<?php

namespace App\Exceptions;

use BackedEnum;
use RuntimeException;

final class InvalidTransition extends RuntimeException
{
    public static function between(?BackedEnum $from, BackedEnum $to): self
    {
        return new self(__('This change is not allowed from the current status (:from → :to).', [
            'from' => $from?->value ?? 'none',
            'to' => $to->value,
        ]));
    }
}
