<?php

namespace App\Domain\Access\Services;

use App\Domain\Organisation\Models\Department;
use App\Domain\Organisation\Services\PermissionCatalogue;
use App\Domain\Teams\Models\Team;
use App\Domain\Teams\Services\TeamFeatures;
use App\Models\User;

/**
 * The workspace-related shared Inertia props (LLD 10 §2). Evaluated after the
 * workspace middleware has run, so permissions are scoped to the URL's workspace.
 */
final class WorkspaceProps
{
    public function __construct(
        private readonly WorkspaceContext $context,
        private readonly TeamFeatures $features,
        private readonly PermissionCatalogue $catalogue,
        private readonly MembershipDirectory $directory,
    ) {}

    /**
     * @return array{type: string, slug: string, name: string}|null
     */
    public function workspace(): ?array
    {
        $owner = $this->context->owner();

        return match (true) {
            $owner instanceof Team => ['type' => 'team', 'slug' => $owner->slug, 'name' => $owner->name],
            $owner instanceof Department => ['type' => 'department', 'slug' => $owner->slug, 'name' => (string) $owner->localized('name')],
            default => null,
        };
    }

    /**
     * The viewer's permissions in the current workspace.
     *
     * @return array<string, bool>
     */
    public function can(?User $user): array
    {
        $owner = $this->context->owner();

        if ($user === null || (! $owner instanceof Team && ! $owner instanceof Department)) {
            return [];
        }

        $names = $owner instanceof Team
            ? collect(config('permissions.team_roles', []))->flatten()->unique()->values()
            : $this->catalogue->areaPermissions()->merge(config('permissions.department_admin_only', []))->unique()->values();

        $user->unsetRelation('roles')->unsetRelation('permissions');

        return $names->mapWithKeys(fn (string $name): array => [$name => $user->can($name)])->all();
    }

    /**
     * Effective team features when inside a team workspace.
     *
     * @return array<string, bool|int>
     */
    public function features(): array
    {
        $owner = $this->context->owner();

        return $owner instanceof Team ? $this->features->effective($owner) : [];
    }

    /**
     * Teams and departments for the workspace switcher.
     *
     * @return list<array{type: string, slug: string, name: string}>
     */
    public function switcher(?User $user): array
    {
        if ($user === null) {
            return [];
        }

        $teams = array_map(fn (array $team): array => ['type' => 'team', 'slug' => $team['slug'], 'name' => $team['name']], $this->directory->teams($user));
        $departments = array_map(fn (array $department): array => ['type' => 'department', 'slug' => $department['slug'], 'name' => $department['name']], $this->directory->departments($user));

        return [...$teams, ...$departments];
    }
}
