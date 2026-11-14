<?php

namespace App\Http\Controllers\Workspace;

use App\Domain\Catalogue\Models\VendorOffer;
use App\Domain\Catalogue\Services\CatalogueSearch;
use App\Domain\Teams\Enums\TeamFeature;
use App\Domain\Teams\Models\Team;
use App\Domain\Teams\Services\TeamFeatures;
use App\Domain\Work\Enums\RobotStatus;
use App\Domain\Work\Models\Robot;
use App\Domain\Work\Services\BomCalculator;
use App\Domain\Work\Services\BomService;
use App\Domain\Work\Services\RobotService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Workspace\RobotRequest;
use App\Http\Requests\Workspace\SyncBomRequest;
use App\Support\Money\MoneyFormatter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * LLD 04 §6: the team's robots and their parts lists.
 */
class RobotController extends Controller
{
    public function __construct(
        private readonly RobotService $robots,
        private readonly TeamFeatures $features,
    ) {}

    public function index(Request $request, Team $team): Response
    {
        return Inertia::render('workspace/robots/Index', [
            'team' => ['slug' => $team->slug, 'name' => $team->name],
            'robots' => $team->robots()->with('project')->withCount('bomLines')->orderBy('name')->get()
                ->map(fn (Robot $robot): array => [
                    'id' => $robot->getKey(),
                    'slug' => $robot->slug,
                    'name' => $robot->name,
                    'status' => $robot->status->value,
                    'project' => $robot->project?->localized('title'),
                    'parts' => (int) $robot->bom_lines_count,
                    'isPublic' => $robot->is_public,
                ])->all(),
            'statuses' => array_column(RobotStatus::cases(), 'value'),
            'projects' => $team->projects()->orderBy('title_en')->get()->map(fn ($project): array => ['id' => $project->getKey(), 'title' => (string) $project->localized('title')])->all(),
            'can' => ['create' => $request->user()->can('robots.edit') && $this->features->active($team, TeamFeature::RobotsCreate)],
        ]);
    }

    public function store(RobotRequest $request, Team $team): RedirectResponse
    {
        $robot = $this->robots->create($team, $request->validated(), $request->user());

        return $this->done('robot_saved', to_route('workspace.robots.show', [$team, $robot]));
    }

    public function show(Request $request, Team $team, Robot $robot, BomCalculator $calculator): Response
    {
        $this->assertTeam($team, $robot);
        $bom = $calculator->forRobot($robot);

        return Inertia::render('workspace/robots/Show', [
            'team' => ['slug' => $team->slug, 'name' => $team->name],
            'robot' => [
                ...$robot->only(['id', 'name', 'slug', 'description_en', 'description_ar', 'weight_g', 'length_mm', 'width_mm', 'height_mm', 'is_public', 'project_id']),
                'status' => $robot->status->value,
            ],
            'bom' => [
                'lines' => array_map(fn (array $row): array => [
                    'partId' => $row['line']->part_id,
                    'part' => $row['line']->part->name,
                    'partNumber' => $row['line']->part->part_number,
                    'offerId' => $row['line']->vendor_offer_id,
                    'vendor' => $row['line']->offer?->vendor?->name,
                    'quantity' => $row['line']->quantity,
                    'notes' => $row['line']->notes,
                    'unit' => $row['unit'] === null ? null : MoneyFormatter::toArray($row['unit']),
                    'total' => $row['total'] === null ? null : MoneyFormatter::toArray($row['total']),
                    'availability' => $row['availability']->value,
                    'offers' => $this->offersFor($row['line']->part_id),
                ], $bom['lines']),
                'totals' => array_values(array_map(fn ($money) => MoneyFormatter::toArray($money), $bom['totals'])),
                'byVendor' => array_map(fn (array $group): array => [
                    'vendor' => $group['vendor']->name,
                    'phone' => $group['vendor']->phone,
                    'total' => MoneyFormatter::toArray($group['total']),
                    'lines' => array_map(fn ($line): array => ['part' => $line->part->name, 'quantity' => $line->quantity], $group['lines']),
                ], $bom['byVendor']),
                'unpriced' => $bom['unpriced'],
            ],
            'statuses' => array_column(RobotStatus::cases(), 'value'),
            'projects' => $team->projects()->orderBy('title_en')->get()->map(fn ($project): array => ['id' => $project->getKey(), 'title' => (string) $project->localized('title')])->all(),
            'can' => ['edit' => $request->user()->can('robots.edit')],
        ]);
    }

    public function update(RobotRequest $request, Team $team, Robot $robot): RedirectResponse
    {
        $this->assertTeam($team, $robot);
        $this->robots->update($robot, $request->validated(), $request->user());

        return $this->done('robot_saved', back());
    }

    public function bom(SyncBomRequest $request, Team $team, Robot $robot, BomService $bom): RedirectResponse
    {
        $this->assertTeam($team, $robot);
        $bom->sync($robot, $request->validated('lines'), $request->user());

        return $this->done('bom_saved', back());
    }

    public function destroy(Request $request, Team $team, Robot $robot): RedirectResponse
    {
        $this->assertTeam($team, $robot);
        abort_unless($request->user()->can('robots.edit'), 403);
        $this->robots->delete($robot, $request->user());

        return $this->done('robot_deleted', to_route('workspace.robots.index', $team));
    }

    /**
     * Parts search for the parts-list editor (JSON).
     */
    public function search(Request $request, Team $team, CatalogueSearch $search): JsonResponse
    {
        $parts = $search->search($team, ['q' => $request->string('q')->toString()], 15);

        return response()->json(['data' => $parts->getCollection()->map(fn ($part): array => [
            'id' => $part->getKey(),
            'name' => $part->name,
            'partNumber' => $part->part_number,
            'offers' => $this->offersFor($part->getKey()),
        ])->all()]);
    }

    /**
     * @return list<array{id: int, vendor: string, formatted: string, inStock: bool}>
     */
    private function offersFor(int $partId): array
    {
        return VendorOffer::query()->approved()->where('part_id', $partId)->with('vendor')->orderBy('price_minor')->get()
            ->map(fn (VendorOffer $offer): array => [
                'id' => $offer->getKey(),
                'vendor' => $offer->vendor->name,
                'formatted' => MoneyFormatter::format($offer->price),
                'inStock' => $offer->in_stock,
            ])->all();
    }

    private function assertTeam(Team $team, Robot $robot): void
    {
        abort_unless($robot->team_id === $team->getKey(), 404);
    }

    private function done(string $key, RedirectResponse $response): RedirectResponse
    {
        Inertia::flash('toast', ['type' => 'success', 'message' => __('work.flash.'.$key)]);

        return $response;
    }
}
