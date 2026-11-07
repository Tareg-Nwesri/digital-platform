<?php

namespace App\Domain\Events\Enums;

enum EventStatus: string
{
    case Draft = 'draft';
    case Open = 'open';
    case Closed = 'closed';
    case Running = 'running';
    case Finished = 'finished';
    case Archived = 'archived';

    public function label(): string
    {
        return __('events.status.'.$this->value);
    }

    /**
     * Organisers may add or approve teams in these statuses.
     */
    public function acceptsOrganiserEntries(): bool
    {
        return in_array($this, [self::Open, self::Closed, self::Running], true);
    }
}
