<?php

namespace App\Domain\Reports\Policies;

use App\Domain\Organisation\Models\Department;
use App\Domain\Organisation\Services\DepartmentAccess;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

/**
 * Department reports (LLD 08 §4): reports.view in that department, or its
 * admin; exporting needs reports.edit. The audit log is the super admin's
 * (who passes through Gate::before).
 */
class ReportPolicy
{
    public function __construct(private readonly DepartmentAccess $access) {}

    public static function register(): void
    {
        Gate::define('view-department-report', [self::class, 'view']);
        Gate::define('export-department-report', [self::class, 'export']);
    }

    public function view(User $user, Department $department): bool
    {
        return $department->isAdmin($user) || $this->access->can($user, 'reports.view', $department) || $this->export($user, $department);
    }

    public function export(User $user, Department $department): bool
    {
        return $department->isAdmin($user) || $this->access->can($user, 'reports.edit', $department);
    }
}
