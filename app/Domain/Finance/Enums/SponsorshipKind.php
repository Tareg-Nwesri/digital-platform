<?php

namespace App\Domain\Finance\Enums;

enum SponsorshipKind: string
{
    case Cash = 'cash';
    case InKind = 'in_kind';
    case Mixed = 'mixed';

    public function label(): string
    {
        return __('finance.sponsorship_kind.'.$this->value);
    }
}
