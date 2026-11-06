<?php

namespace App\Domain\Teams\Enums;

enum TeamStatus: string
{
    case Pending = 'pending';
    case Active = 'active';
    case Suspended = 'suspended';
    case Archived = 'archived';

    public function label(): string
    {
        return __('teams.status.'.$this->value);
    }

    public function requiresReason(): bool
    {
        return in_array($this, [self::Suspended, self::Archived], true);
    }
}
