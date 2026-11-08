<?php

namespace App\Domain\Finance\Enums;

enum SponsorType: string
{
    case Company = 'company';
    case Organisation = 'organisation';
    case Individual = 'individual';
    case Government = 'government';

    public function label(): string
    {
        return __('finance.sponsor_type.'.$this->value);
    }
}
