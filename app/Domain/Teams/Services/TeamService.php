<?php

namespace App\Domain\Teams\Services;

use App\Domain\Access\Enums\WorkspaceType;
use App\Domain\Access\Models\Workspace;
use App\Domain\Access\Services\WorkspaceContext;
use App\Domain\Teams\Enums\TeamRole;
use App\Domain\Teams\Enums\TeamStatus;
use App\Domain\Teams\Models\Team;
use App\Domain\Teams\Models\Tier;
use App\Exceptions\DomainRuleViolation;
use App\Models\User;
use App\Support\Slugger;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

final class TeamService
{
    public function __construct(
        private readonly WorkspaceContext $context,
        private readonly TeamFeatures $features,
    ) {}

    /**
     * Create a team with its workspace and its four roles (LLD 03 §5, CreateTeam).
     *
     * @param  array{name: string, slug?: string|null, tier_id?: int|null, department_id?: int|null, location?: string|null}  $data
     */
    public function create(array $data, ?User $by = null, bool $approved = true): Team
    {
        return DB::transaction(function () use ($data, $by, $approved): Team {
            $workspace = Workspace::open(WorkspaceType::Team);
            $tierId = $data['tier_id'] ?? Tier::default()?->getKey() ?? throw new \RuntimeException('No default tier is configured.');

            $team = Team::query()->create([
                'workspace_id' => $workspace->getKey(),
                'tier_id' => $tierId,
                'department_id' => $data['department_id'] ?? null,
                'name' => $data['name'],
                'slug' => filled($data['slug'] ?? null)
                    ? Slugger::unique(Team::class, (string) $data['slug'])
                    : Slugger::unique(Team::class, $data['name']),
                'location' => $data['location'] ?? null,
                'status' => $approved ? TeamStatus::Active : TeamStatus::Pending,
                'approved_at' => $approved ? now() : null,
                'approved_by' => $approved ? $by?->getKey() : null,
            ]);

            $this->createRoles($workspace);

            return $team;
        });
    }

    /**
     * Create the captain, coach, member and viewer roles in the team's workspace.
     */
    public function createRoles(Workspace $workspace): void
    {
        $this->context->within($workspace, function (): void {
            foreach (TeamRole::cases() as $role) {
                $permissions = collect($role->permissions())
                    ->map(fn (string $name): Permission => Permission::findOrCreate($name, 'web'));

                Role::findOrCreate($role->value, 'web')->syncPermissions($permissions);
            }
        });
    }

    public function changeStatus(Team $team, TeamStatus $to, ?string $reason, ?User $by = null): void
    {
        if ($to->requiresReason() && blank($reason)) {
            throw DomainRuleViolation::with(['reason' => __('teams.errors.reason_required')]);
        }

        $team->transitionTo($to);
        $team->status_reason = $reason;

        if ($to === TeamStatus::Active && $team->approved_at === null) {
            $team->approved_at = now();
            $team->approved_by = $by?->getKey();
        }

        $team->save();
    }

    public function assignTier(Team $team, Tier $tier, ?User $by = null): void
    {
        $team->tier()->associate($tier)->save();
        $this->features->forgetAll($team->fresh());
    }
}
