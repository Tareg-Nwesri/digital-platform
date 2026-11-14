<?php

namespace App\Domain\Work\Enums;

/**
 * Informational only; any status may follow any other.
 */
enum RobotStatus: string
{
    case Design = 'design';
    case Building = 'building';
    case Ready = 'ready';
    case Retired = 'retired';

    public function label(): string
    {
        return __('work.robot_status.'.$this->value);
    }
}
