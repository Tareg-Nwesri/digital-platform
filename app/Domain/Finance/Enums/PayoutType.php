<?php

namespace App\Domain\Finance\Enums;

enum PayoutType: string
{
    case Bonus = 'bonus';
    case Stipend = 'stipend';
    case Payment = 'payment';
    case PrizeShare = 'prize_share';
    case Reimbursement = 'reimbursement';

    public function label(): string
    {
        return __('finance.payout_type.'.$this->value);
    }
}
