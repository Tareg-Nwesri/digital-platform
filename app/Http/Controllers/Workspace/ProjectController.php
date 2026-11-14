<?php

namespace App\Http\Controllers\Workspace;

use App\Domain\Teams\Enums\TeamFeature;
use App\Domain\Teams\Models\Team;
use App\Domain\Teams\Services\TeamFeatures;
use App\Domain\Work\Models\Project;
use App\Domain\Work\Services\ProjectService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Workspace\ProjectRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * LLD 04 §6: the team's projects.
 */
class ProjectController extends Controller
{
    public function __construct(
        private readonly ProjectService $projects,
        private readonly TeamFeatures $features,
    ) {}

    public function index(Request $request, Team $team): Response
    {
        return Inertia::render('workspace/projects/Index', [
            'team' => ['slug' => $team->slug, 'name' => $team->name],
            'projects' => $team->projects()->withCount('robots')->latest('updated_at')->get()
                ->map(fn (Project $project): array => [
                    'id' => $project->getKey(),
                    'slug' => $project->slug,
                    'title' => (string) $project->localized('title'),
                    'status' => $project->status->value,
                    'robots' => (int) $project->robots_count,
                    'publishedAt' => $project->published_at?->toIso8601String(),
                ])->all(),
            'can' => ['create' => $request->user()->can('projects.edit') && $this->features->active($team, TeamFeature::ProjectsCreate)],
        ]);
    }

    public function store(ProjectRequest $request, Team $team): RedirectResponse
    {
        $project = $this->projects->create($team, $request->validated(), $request->user());

        return $this->done('project_saved', to_route('workspace.projects.edit', [$team, $project]));
    }

    public function edit(Request $request, Team $team, Project $project): Response
    {
        $this->assertTeam($team, $project);
        $user = $request->user();

        return Inertia::render('workspace/projects/Edit', [
            'team' => ['slug' => $team->slug, 'name' => $team->name],
            'project' => [
                'id' => $project->getKey(),
                'slug' => $project->slug,
                'titleEn' => $project->title_en,
                'titleAr' => $project->title_ar,
                'excerptEn' => $project->excerpt_en,
                'excerptAr' => $project->excerpt_ar,
                'bodyEn' => $project->body_en,
                'bodyAr' => $project->body_ar,
                'status' => $project->status->value,
                'publishedAt' => $project->published_at?->toIso8601String(),
                'robots' => $project->robots()->orderBy('name')->get(['id', 'name', 'slug'])->toArray(),
            ],
            'can' => [
                'edit' => $user->can('projects.edit'),
                'publish' => $user->can('projects.publish') && $this->features->active($team, TeamFeature::ProjectsPublish),
            ],
        ]);
    }

    public function update(ProjectRequest $request, Team $team, Project $project): RedirectResponse
    {
        $this->assertTeam($team, $project);
        $this->projects->update($project, $request->validated(), $request->user());

        return $this->done('project_saved', back());
    }

    public function publish(Request $request, Team $team, Project $project): RedirectResponse
    {
        $this->assertTeam($team, $project);
        abort_unless($request->user()->can('projects.publish'), 403);
        $this->projects->publish($project, $request->user());

        return $this->done('project_published', back());
    }

    public function unpublish(Request $request, Team $team, Project $project): RedirectResponse
    {
        $this->assertTeam($team, $project);
        abort_unless($request->user()->can('projects.publish'), 403);
        $this->projects->unpublish($project, $request->user());

        return $this->done('project_unpublished', back());
    }

    public function destroy(Request $request, Team $team, Project $project): RedirectResponse
    {
        $this->assertTeam($team, $project);
        abort_unless($request->user()->can('projects.edit'), 403);
        $project->delete();

        return $this->done('project_deleted', to_route('workspace.projects.index', $team));
    }

    private function assertTeam(Team $team, Project $project): void
    {
        abort_unless($project->team_id === $team->getKey(), 404);
    }

    private function done(string $key, RedirectResponse $response): RedirectResponse
    {
        Inertia::flash('toast', ['type' => 'success', 'message' => __('work.flash.'.$key)]);

        return $response;
    }
}
