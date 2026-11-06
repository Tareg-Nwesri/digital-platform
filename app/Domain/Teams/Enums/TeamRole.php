<?php

namespace App\Domain\Teams\Enums;

enum TeamRole: string
{
    case Captain = 'captain';
    case Coach = 'coach';
    case Member = 'member';
    case Viewer = 'viewer';

    /**
     * Team-scope permissions carried by this role (config/permissions.php).
     *
     * @return list<string>
     */
    public function permissions(): array
    {
        return config('permissions.team_roles.'.$this->value, []);
    }

    /**
     * Roles that a member without a login may hold.
     */
    public function allowedWithoutLogin(): bool
    {
        return in_array($this, [self::Member, self::Viewer], true);
    }

    public function label(): string
    {
        return __('teams.roles.'.$this->value);
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
