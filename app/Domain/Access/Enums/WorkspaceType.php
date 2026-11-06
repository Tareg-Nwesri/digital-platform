<?php

namespace App\Domain\Access\Enums;

enum WorkspaceType: string
{
    case Platform = 'platform';
    case Team = 'team';
    case Department = 'department';
}
