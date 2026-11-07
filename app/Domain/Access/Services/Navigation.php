<?php

namespace App\Domain\Access\Services;

use App\Domain\Organisation\Models\Department;
use App\Domain\Teams\Enums\TeamFeature;
use App\Domain\Teams\Models\Team;
use App\Domain\Teams\Services\TeamFeatures;
use App\Models\User;
use Illuminate\Support\Facades\Route;

/**
 * Builds the sidebar on the server (LLD 10 §3). An item appears only when its
 * route exists, the viewer has its permission and, for teams, the feature is on.
 * Modules add their items here as their routes land.
 */
final class Navigation
{
    /**
     * Team workspace items: key, route, permission, feature, icon.
     *
     * @var list<array{0: string, 1: string, 2: string, 3: TeamFeature|null, 4: string}>
     */
    private const TEAM_ITEMS = [
        ['dashboard', 'workspace.dashboard', 'team.view', null, 'layout-grid'],
        ['members', 'workspace.members.index', 'team.view', null, 'users'],
        ['projects', 'workspace.projects.index', 'team.view', TeamFeature::ProjectsCreate, 'folder-kanban'],
        ['robots', 'workspace.robots.index', 'team.view', TeamFeature::RobotsCreate, 'bot'],
        ['catalogue', 'workspace.catalogue.index', 'team.view', TeamFeature::PartsAccess, 'cpu'],
        ['events', 'workspace.events.index', 'team.view', TeamFeature::EventsRegister, 'trophy'],
        ['budget', 'workspace.budget.index', 'budget.manage', TeamFeature::BudgetRequest, 'wallet'],
        ['settings', 'workspace.settings.edit', 'team.settings', null, 'settings'],
    ];

    /**
     * Console areas: key, route, permission (in any of the viewer's departments), icon.
     *
     * @var list<array{0: string, 1: string, 2: string, 3: string}>
     */
    private const CONSOLE_ITEMS = [
        ['teams', 'console.teams.index', 'teams.view', 'users'],
        ['events', 'console.events.index', 'events.view', 'trophy'],
        ['catalogue', 'console.catalogue.parts.index', 'catalogue.view', 'cpu'],
        ['finance', 'console.finance.dashboard', 'finance.view', 'wallet'],
        ['content', 'console.posts.index', 'content.view', 'newspaper'],
        ['inbox', 'console.inbox.index', 'inbox.view', 'inbox'],
    ];

    /**
     * Super admin only: key, route, icon.
     *
     * @var list<array{0: string, 1: string, 2: string}>
     */
    private const PLATFORM_ITEMS = [
        ['console', 'console.dashboard', 'shield'],
        ['departments', 'console.departments.index', 'building-2'],
        ['tiers', 'console.tiers.index', 'layers'],
        ['users', 'console.users.index', 'user-cog'],
        ['audit', 'console.audit.index', 'scroll-text'],
        ['settings', 'console.settings.index', 'sliders-horizontal'],
    ];

    public function __construct(
        private readonly WorkspaceContext $context,
        private readonly TeamFeatures $features,
    ) {}

    /**
     * @return list<array{key: string, label: string, href: string, icon: string}>
     */
    public function for(?User $user): array
    {
        if ($user === null) {
            return [];
        }

        $owner = $this->context->owner();

        return $owner instanceof Team
            ? $this->team($user, $owner)
            : $this->console($user, $owner instanceof Department ? $owner : null);
    }

    /**
     * @return list<array{key: string, label: string, href: string, icon: string}>
     */
    private function team(User $user, Team $team): array
    {
        $items = [];

        foreach (self::TEAM_ITEMS as [$key, $route, $permission, $feature, $icon]) {
            if (! Route::has($route) || ! $user->can($permission)) {
                continue;
            }

            if ($feature !== null && ! $this->features->active($team, $feature)) {
                continue;
            }

            $items[] = $this->item('team.'.$key, $key, route($route, $team->slug), $icon);
        }

        return $items;
    }

    /**
     * @return list<array{key: string, label: string, href: string, icon: string}>
     */
    private function console(User $user, ?Department $current): array
    {
        $items = [];

        if ($user->isSuperAdmin()) {
            foreach (self::PLATFORM_ITEMS as [$key, $route, $icon]) {
                if (Route::has($route)) {
                    $items[] = $this->item('platform.'.$key, $key, route($route), $icon);
                }
            }
        }

        $departments = Department::query()
            ->whereHas('members', fn ($query) => $query->whereKey($user->getKey()))
            ->with('workspace')
            ->orderBy('name_en')
            ->get();

        foreach ($departments as $department) {
            $items[] = $this->item('department', 'department-'.$department->slug, route('console.departments.show', $department->slug), 'building-2', $department->localized('name'));
        }

        foreach (self::CONSOLE_ITEMS as [$key, $route, $permission, $icon]) {
            if (! Route::has($route)) {
                continue;
            }

            $allowed = $user->isSuperAdmin() || $departments->contains(
                fn (Department $department): bool => $this->context->within(
                    $department->workspace,
                    fn (): bool => $user->unsetRelation('permissions')->unsetRelation('roles')->can($permission),
                ),
            );

            if ($allowed) {
                $items[] = $this->item('console.'.$key, $key, route($route), $icon);
            }
        }

        return $items;
    }

    /**
     * @return array{key: string, label: string, href: string, icon: string}
     */
    private function item(string $labelKey, string $key, string $href, string $icon, ?string $label = null): array
    {
        return [
            'key' => $key,
            'label' => $label ?? __('nav.'.$labelKey),
            'href' => $href,
            'icon' => $icon,
        ];
    }
}
