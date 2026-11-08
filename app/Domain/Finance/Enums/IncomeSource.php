<?php

namespace App\Domain\Finance\Enums;

enum IncomeSource: string
{
    case Sponsorship = 'sponsorship';
    case Donation = 'donation';
    case Prize = 'prize';
    case EventFee = 'event_fee';
    case Grant = 'grant';
    case Other = 'other';

    public function label(): string
    {
        return __('finance.income_source.'.$this->value);
    }
}
