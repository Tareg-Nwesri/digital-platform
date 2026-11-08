<?php

namespace App\Domain\Finance\Policies;

use App\Domain\Organisation\Services\DepartmentAccess;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

/**
 * Console abilities on finance (LLD 07 §4). Finance is global: the
 * permission in any department is enough. LedgerService checks approvals
 * again (maker-checker, overrides). Registered as gates.
 */
class FinancePolicy
{
    public function __construct(private readonly DepartmentAccess $access) {}

    public static function register(): void
    {
        Gate::define('view-finance', [self::class, 'view']);
        Gate::define('edit-finance', [self::class, 'edit']);
        Gate::define('approve-finance', [self::class, 'approve']);
    }

    public function view(User $user): bool
    {
        return $this->access->can($user, 'finance.view')
            || $this->access->can($user, 'finance.edit')
            || $this->access->can($user, 'finance.approve');
    }

    public function edit(User $user): bool
    {
        return $this->access->can($user, 'finance.edit');
    }

    public function approve(User $user): bool
    {
        return $this->access->can($user, 'finance.approve');
    }
}
