<?php

namespace App\Domain\Catalogue\Policies;

use App\Domain\Organisation\Services\DepartmentAccess;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

/**
 * Console abilities on the catalogue (LLD 05 §5). The catalogue is global,
 * so holding the permission in any department is enough. Registered as the
 * gates view-catalogue, manage-catalogue and review-catalogue; the super
 * admin passes through Gate::before.
 */
class CataloguePolicy
{
    public function __construct(private readonly DepartmentAccess $access) {}

    public static function register(): void
    {
        Gate::define('view-catalogue', [self::class, 'view']);
        Gate::define('manage-catalogue', [self::class, 'manage']);
        Gate::define('review-catalogue', [self::class, 'review']);
    }

    public function view(User $user): bool
    {
        return $this->access->can($user, 'catalogue.view')
            || $this->access->can($user, 'catalogue.edit')
            || $this->access->can($user, 'catalogue.approve');
    }

    public function manage(User $user): bool
    {
        return $this->access->can($user, 'catalogue.edit');
    }

    public function review(User $user): bool
    {
        return $this->access->can($user, 'catalogue.approve');
    }
}
