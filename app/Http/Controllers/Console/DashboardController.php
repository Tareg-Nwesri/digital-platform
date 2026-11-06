<?php

namespace App\Http\Controllers\Console;

use App\Domain\Organisation\Models\Department;
use App\Domain\Teams\Enums\TeamStatus;
use App\Domain\Teams\Models\Team;
use App\Http\Controllers\Controller;
use App\Models\User;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Super admin overview. Department dashboards live in DepartmentDashboardController.
 */
class DashboardController extends Controller
{
    public function __invoke(): Response
    {
        return Inertia::render('console/Dashboard', [
            'stats' => [
                'teams' => [
                    'active' => Team::query()->where('status', TeamStatus::Active)->count(),
                    'pending' => Team::query()->where('status', TeamStatus::Pending)->count(),
                    'suspended' => Team::query()->where('status', TeamStatus::Suspended)->count(),
                ],
                'departments' => Department::query()->count(),
                'users' => User::query()->where('can_login', true)->count(),
            ],
        ]);
    }
}
