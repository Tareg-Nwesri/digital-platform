<?php

namespace App\Domain\Teams\Services;

use App\Domain\Teams\Enums\TeamFeature;
use App\Domain\Teams\Models\Team;
use App\Domain\Teams\Models\TeamFeatureOverride;
use App\Domain\Teams\Models\Tier;
use App\Exceptions\FeatureUnavailable;
use App\Models\User;
use Laravel\Pennant\Feature;

/**
 * Team features: the tier sets the default, a stored override wins (LLD 03 §3).
 * Pennant caches the resolved value; changes here forget that cache.
 */
final class TeamFeatures
{
    /**
     * Register every team feature with Pennant.
     */
    public static function define(): void
    {
        foreach (TeamFeature::cases() as $feature) {
            Feature::define($feature->value, fn (mixed $scope): bool|int => $scope instanceof Team
                ? self::resolve($scope, $feature)
                : $feature->normalize(false));
        }
    }

    /**
     * Resolve a feature without Pennant's cache: override, else tier value, else fallback.
     */
    public static function resolve(Team $team, TeamFeature $feature): bool|int
    {
        $override = TeamFeatureOverride::query()
            ->where('team_id', $team->getKey())
            ->where('feature', $feature->value)
            ->first();

        if ($override !== null) {
            return $feature->normalize($override->value);
        }

        return $team->tier?->value($feature) ?? $feature->fallback();
    }

    public function value(Team $team, TeamFeature $feature): bool|int
    {
        return $feature->normalize(Feature::for($team)->value($feature->value));
    }

    public function active(Team $team, TeamFeature $feature): bool
    {
        $value = $this->value($team, $feature);

        return is_bool($value) ? $value : $value > 0;
    }

    public function assertActive(Team $team, TeamFeature $feature): void
    {
        if (! $this->active($team, $feature)) {
            throw FeatureUnavailable::for($feature->value);
        }
    }

    /**
     * Effective values for every feature.
     *
     * @return array<string, bool|int>
     */
    public function effective(Team $team): array
    {
        return collect(TeamFeature::cases())
            ->mapWithKeys(fn (TeamFeature $feature): array => [$feature->value => $this->value($team, $feature)])
            ->all();
    }

    /**
     * Each feature's tier value, override and effective value, for the console screen.
     *
     * @return list<array{name: string, type: string, tierValue: bool|int, override: bool|int|null, effective: bool|int}>
     */
    public function breakdown(Team $team): array
    {
        $overrides = $team->featureOverrides()->pluck('value', 'feature');

        return collect(TeamFeature::cases())->map(fn (TeamFeature $feature): array => [
            'name' => $feature->value,
            'type' => $feature->isLimit() ? 'number' : 'boolean',
            'tierValue' => $team->tier->value($feature),
            'override' => $overrides->has($feature->value) ? $feature->normalize($overrides[$feature->value]) : null,
            'effective' => $this->value($team, $feature),
        ])->all();
    }

    public function override(Team $team, TeamFeature $feature, bool|int $value, ?User $by = null): void
    {
        TeamFeatureOverride::query()->updateOrCreate(
            ['team_id' => $team->getKey(), 'feature' => $feature->value],
            ['value' => $feature->normalize($value), 'set_by' => $by?->getKey()],
        );

        Feature::for($team)->forget($feature->value);

        activity('teams')->performedOn($team)->causedBy($by)
            ->withProperties(['feature' => $feature->value, 'value' => $feature->normalize($value)])
            ->event('feature.overridden')
            ->log('Team feature overridden');
    }

    public function clearOverride(Team $team, TeamFeature $feature, ?User $by = null): void
    {
        TeamFeatureOverride::query()
            ->where('team_id', $team->getKey())
            ->where('feature', $feature->value)
            ->delete();

        Feature::for($team)->forget($feature->value);

        activity('teams')->performedOn($team)->causedBy($by)
            ->withProperties(['feature' => $feature->value])
            ->event('feature.override_cleared')
            ->log('Team feature override cleared');
    }

    /**
     * After a tier changes, teams on it must re-resolve their features.
     */
    public function onTierChanged(Tier $tier): void
    {
        $tier->teams()->each(fn (Team $team) => Feature::for($team)->forget(TeamFeature::names()));
    }

    public function forgetAll(Team $team): void
    {
        Feature::for($team)->forget(TeamFeature::names());
    }
}
