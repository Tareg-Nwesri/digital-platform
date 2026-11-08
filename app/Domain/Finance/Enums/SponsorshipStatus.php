<?php

namespace App\Domain\Finance\Enums;

enum SponsorshipStatus: string
{
    case Draft = 'draft';
    case Active = 'active';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return __('finance.sponsorship_status.'.$this->value);
    }
}
