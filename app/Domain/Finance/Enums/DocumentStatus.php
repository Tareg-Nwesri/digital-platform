<?php

namespace App\Domain\Finance\Enums;

enum DocumentStatus: string
{
    case Draft = 'draft';
    case Submitted = 'submitted';
    case Posted = 'posted';
    case Rejected = 'rejected';

    public function label(): string
    {
        return __('finance.document_status.'.$this->value);
    }
}
