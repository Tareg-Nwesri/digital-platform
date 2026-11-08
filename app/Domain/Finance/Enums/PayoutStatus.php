<?php

namespace App\Domain\Finance\Enums;

enum PayoutStatus: string
{
    case Draft = 'draft';
    case Submitted = 'submitted';
    case Approved = 'approved';
    case Paid = 'paid';
    case Rejected = 'rejected';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return __('finance.payout_status.'.$this->value);
    }
}
