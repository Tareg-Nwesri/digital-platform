<?php

namespace App\Domain\Finance\Enums;

enum TransactionType: string
{
    case Income = 'income';
    case Allocation = 'allocation';
    case Funding = 'funding';
    case Expense = 'expense';
    case Payout = 'payout';
    case Return = 'return';
    case Reversal = 'reversal';
    case Adjustment = 'adjustment';

    public function label(): string
    {
        return __('finance.transaction_type.'.$this->value);
    }
}
