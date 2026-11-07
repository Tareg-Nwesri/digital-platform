<?php

namespace App\Domain\Catalogue\Enums;

enum CatalogueStatus: string
{
    case Approved = 'approved';
    case Suggested = 'suggested';
    case Rejected = 'rejected';
    case Archived = 'archived';

    public function label(): string
    {
        return __('catalogue.status.'.$this->value);
    }
}
