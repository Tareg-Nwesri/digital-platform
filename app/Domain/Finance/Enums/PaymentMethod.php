<?php

namespace App\Domain\Finance\Enums;

enum PaymentMethod: string
{
    case Bank = 'bank';
    case Cash = 'cash';
    case Cheque = 'cheque';
    case InKind = 'in_kind';
    case Other = 'other';

    public function label(): string
    {
        return __('finance.payment_method.'.$this->value);
    }
}
