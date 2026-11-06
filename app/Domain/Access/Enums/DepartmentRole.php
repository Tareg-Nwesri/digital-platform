<?php

namespace App\Domain\Access\Enums;

/**
 * Roles an invitation can carry for a department workspace.
 */
enum DepartmentRole: string
{
    case Admin = 'department-admin';
    case Member = 'department-member';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
