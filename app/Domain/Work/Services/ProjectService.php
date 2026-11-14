<?php

namespace App\Domain\Work\Services;

use App\Domain\Teams\Enums\TeamFeature;
use App\Domain\Teams\Models\Team;
use App\Domain\Teams\Services\TeamFeatures;
use App\Domain\Work\Enums\ProjectStatus;
use App\Domain\Work\Models\Project;
use App\Models\User;
use App\Support\Html\RichText;
use App\Support\Slugger;

/**
 * Team projects (LLD 04 §4). Bodies are sanitised on every save; publishing
 * needs the team's projects.publish feature.
 */
final class ProjectService
{
    private const FIELDS = ['title_en', 'title_ar', 'excerpt_en', 'excerpt_ar', 'body_en', 'body_ar'];

    public function __construct(private readonly TeamFeatures $features) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(Team $team, array $data, User $by): Project
    {
        $this->features->assertActive($team, TeamFeature::ProjectsCreate);

        return Project::query()->create([
            ...$this->clean($data),
            'team_id' => $team->getKey(),
            'slug' => Slugger::unique(Project::class, (string) $data['title_en']),
            'status' => ProjectStatus::Draft,
            'created_by' => $by->getKey(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Project $project, array $data, User $by): Project
    {
        $project->update($this->clean($data));

        return $project;
    }

    public function publish(Project $project, User $by): void
    {
        $this->features->assertActive($project->team, TeamFeature::ProjectsPublish);

        $project->forceFill(['status' => ProjectStatus::Published, 'published_at' => $project->published_at ?? now()])->save();
    }

    public function unpublish(Project $project, User $by): void
    {
        $project->forceFill(['status' => ProjectStatus::Draft])->save();
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function clean(array $data): array
    {
        $values = array_intersect_key($data, array_flip(self::FIELDS));

        foreach (['body_en', 'body_ar'] as $field) {
            if (array_key_exists($field, $values)) {
                $values[$field] = RichText::clean($values[$field]);
            }
        }

        return $values;
    }
}
