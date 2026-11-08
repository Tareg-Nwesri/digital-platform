<?php

namespace App\Domain\Finance\Enums;

/**
 * "Reversed" is not stored: a posted transaction with reversed_by_id set.
 */
enum TransactionStatus: string
{
    case Draft = 'draft';
    case Submitted = 'submitted';
    case Posted = 'posted';
    case Rejected = 'rejected';

    public function label(): string
    {
        return __('finance.transaction_status.'.$this->value);
    }
}
