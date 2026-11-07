<?php

namespace App\Http\Controllers\Console;

use App\Domain\Teams\Enums\TeamFeature;
use App\Domain\Teams\Models\Tier;
use App\Domain\Teams\Services\TierService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Console\StoreTierRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * LLD 03 §8–9: tiers, for the super admin only (tiers.manage is reserved).
 */
class TierController extends Controller
{
    public function __construct(private readonly TierService $tiers) {}

    public function index(): Response
    {
        $tiers = Tier::query()->withCount('teams')->orderBy('position')->get()
            ->map(fn (Tier $tier): array => [
                'id' => $tier->getKey(),
                'slug' => $tier->slug,
                'name' => $tier->localized('name'),
                'nameEn' => $tier->name_en,
                'nameAr' => $tier->name_ar,
                'isDefault' => (bool) $tier->is_default,
                'teamsCount' => (int) $tier->teams_count,
                'features' => collect(TeamFeature::cases())->map(fn (TeamFeature $feature): array => [
                    'name' => $feature->value,
                    'type' => $feature->isLimit() ? 'number' : 'boolean',
                    'value' => $tier->value($feature),
                ])->all(),
            ]);

        return Inertia::render('console/tiers/Index', [
            'tiers' => $tiers,
            'featureNames' => collect(TeamFeature::cases())->map(fn (TeamFeature $feature): array => [
                'name' => $feature->value,
                'type' => $feature->isLimit() ? 'number' : 'boolean',
                'fallback' => $feature->fallback(),
            ])->all(),
        ]);
    }

    public function store(StoreTierRequest $request): RedirectResponse
    {
        $tier = $this->tiers->create($request->tierData(), $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('teams.flash.tier_created', ['name' => $tier->localized('name')])]);

        return to_route('console.tiers.index');
    }

    public function update(StoreTierRequest $request, Tier $tier): RedirectResponse
    {
        $this->tiers->update($tier, $request->tierData(), $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('teams.flash.tier_updated', ['name' => $tier->localized('name')])]);

        return to_route('console.tiers.index');
    }

    public function setDefault(Request $request, Tier $tier): RedirectResponse
    {
        $this->tiers->setDefault($tier, $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('teams.flash.tier_default', ['name' => $tier->localized('name')])]);

        return to_route('console.tiers.index');
    }

    public function destroy(Request $request, Tier $tier): RedirectResponse
    {
        $this->tiers->delete($tier, $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('teams.flash.tier_deleted', ['name' => $tier->localized('name')])]);

        return to_route('console.tiers.index');
    }
}
