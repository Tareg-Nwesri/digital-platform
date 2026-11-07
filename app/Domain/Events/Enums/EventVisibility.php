<?php

namespace App\Domain\Events\Enums;

enum EventVisibility: string
{
    case Public = 'public';
    case InviteOnly = 'invite_only';

    public function label(): string
    {
        return __('events.visibility.'.$this->value);
    }
}
