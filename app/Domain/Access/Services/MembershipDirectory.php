<?php

namespace App\Domain\Access\Services;

use App\Domain\Organisation\Models\Department;
use App\Domain\Teams\Enums\TeamStatus;
use App\Domain\Teams\Models\Team;
use App\Domain\Teams\Services\TeamMemberService;
use App\Models\User;

/**
 * Lists a user's teams and departments for the hub and the workspace switcher.
 */
final class MembershipDirectory
{
    public function __construct(private readonly TeamMemberService $teamMembers) {}

    /**
     * @return list<array{slug: string, name: string, role: string|null, status: string, logoUrl: string|null}>
     */
    public function teams(User $user): array
    {
        return Team::query()
            ->whereHas('members', fn ($query) => $query->whereKey($user->getKey()))
            ->where('status', '!=', TeamStatus::Archived)
            ->with('workspace')
            ->orderBy('name')
            ->get()
            ->map(fn (Team $team): array => [
                'slug' => $team->slug,
                'name' => $team->name,
                'role' => $this->teamMembers->roleOf($team, $user)?->value,
                'status' => $team->status->value,
                'logoUrl' => null,
            ])
            ->all();
    }

    /**
     * @return list<array{slug: string, name: string, isAdmin: bool}>
     */
    public function departments(User $user): array
    {
        return Department::query()
            ->whereHas('members', fn ($query) => $query->whereKey($user->getKey()))
            ->orderBy('name_en')
            ->get()
            ->map(fn (Department $department): array => [
                'slug' => $department->slug,
                'name' => $department->localized('name'),
                'isAdmin' => $department->isAdmin($user),
            ])
            ->all();
    }

    /**
     * Where a user should land after signing in (LLD 01 §4, AuthenticatedRedirect).
     */
    public function homeFor(User $user): string
    {
        if ($user->isSuperAdmin()) {
            return route('console.dashboard');
        }

        $teams = $this->teams($user);
        $departments = $this->departments($user);

        if (count($teams) === 1 && $departments === []) {
            return route('workspace.dashboard', $teams[0]['slug']);
        }

        if ($teams === [] && count($departments) === 1) {
            return route('console.departments.show', $departments[0]['slug']);
        }

        return route('hub');
    }
}
