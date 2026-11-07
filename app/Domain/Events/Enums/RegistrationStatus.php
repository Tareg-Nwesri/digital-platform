<?php

namespace App\Domain\Events\Enums;

enum RegistrationStatus: string
{
    case Invited = 'invited';
    case Pending = 'pending';
    case Approved = 'approved';
    case Waitlisted = 'waitlisted';
    case Rejected = 'rejected';
    case Withdrawn = 'withdrawn';

    public function label(): string
    {
        return __('events.registration_status.'.$this->value);
    }

    public function isActive(): bool
    {
        return in_array($this, self::active(), true);
    }

    /**
     * @return list<self>
     */
    public static function active(): array
    {
        return [self::Invited, self::Pending, self::Approved, self::Waitlisted];
    }
}
