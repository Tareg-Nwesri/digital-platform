<?php

namespace App\Domain\Work\Services;

use App\Domain\Events\Enums\RegistrationStatus;
use App\Domain\Teams\Enums\TeamFeature;
use App\Domain\Teams\Models\Team;
use App\Domain\Teams\Services\TeamFeatures;
use App\Domain\Work\Models\Project;
use App\Domain\Work\Models\Robot;
use App\Exceptions\DomainRuleViolation;
use App\Models\User;
use App\Support\Slugger;

/**
 * Team robots (LLD 04 §4). A robot may only belong to its own team's project.
 */
final class RobotService
{
    private const FIELDS = ['project_id', 'name', 'description_en', 'description_ar', 'status', 'weight_g', 'length_mm', 'width_mm', 'height_mm', 'is_public'];

    public function __construct(private readonly TeamFeatures $features) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(Team $team, array $data, User $by): Robot
    {
        $this->features->assertActive($team, TeamFeature::RobotsCreate);
        $this->assertOwnProject($team, $data['project_id'] ?? null);

        return Robot::query()->create([
            ...array_intersect_key($data, array_flip(self::FIELDS)),
            'team_id' => $team->getKey(),
            'slug' => Slugger::unique(Robot::class, (string) $data['name']),
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Robot $robot, array $data, User $by): Robot
    {
        $this->assertOwnProject($robot->team, $data['project_id'] ?? null);
        $robot->update(array_intersect_key($data, array_flip(self::FIELDS)));

        return $robot;
    }

    public function delete(Robot $robot, User $by): void
    {
        if ($robot->registrations()->where('status', RegistrationStatus::Approved)->exists()) {
            throw DomainRuleViolation::with(['robot' => __('work.errors.robot_registered')]);
        }

        $robot->delete();
    }

    private function assertOwnProject(Team $team, mixed $projectId): void
    {
        if (filled($projectId) && ! Project::query()->whereKey($projectId)->where('team_id', $team->getKey())->exists()) {
            throw DomainRuleViolation::with(['project_id' => __('work.errors.other_team_project')]);
        }
    }
}
