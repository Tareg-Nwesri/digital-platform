<?php

namespace App\Domain\Events\Enums;

enum RegistrationSource: string
{
    case Organiser = 'organiser';
    case Applied = 'applied';
}
