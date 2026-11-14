<?php

namespace App\Domain\Work\Enums;

enum ProjectStatus: string
{
    case Draft = 'draft';
    case Published = 'published';
    case Archived = 'archived';

    public function label(): string
    {
        return __('work.project_status.'.$this->value);
    }
}
