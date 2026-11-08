<?php

namespace App\Domain\Content\Enums;

enum MessageStatus: string
{
    case New = 'new';
    case InProgress = 'in_progress';
    case Closed = 'closed';

    public function label(): string
    {
        return __('content.message_status.'.$this->value);
    }
}
