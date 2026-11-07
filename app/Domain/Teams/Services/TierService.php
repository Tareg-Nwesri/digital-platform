<?php

namespace App\Domain\Teams\Services;

use App\Domain\Teams\Enums\TeamFeature;
use App\Domain\Teams\Models\Tier;
use App\Exceptions\DomainRuleViolation;
use App\Models\User;
use App\Support\Slugger;
use Illuminate\Support\Facades\DB;

/**
 * Tiers set the default features of every team on them (LLD 03 §5).
 */
final class TierService
{
    public function __construct(private readonly TeamFeatures $features) {}

    /**
     * @param  array{name_en: string, name_ar?: string|null, slug?: string|null, features: array<string, mixed>}  $data
     */
    public function create(array $data, ?User $by = null): Tier
    {
        $tier = Tier::query()->create([
            'name_en' => $data['name_en'],
            'name_ar' => $data['name_ar'] ?? null,
            'slug' => Slugger::unique(Tier::class, (string) (filled($data['slug'] ?? null) ? $data['slug'] : $data['name_en'])),
            'features' => self::normalize($data['features']),
            'is_default' => false,
            'position' => (int) Tier::query()->max('position') + 1,
        ]);

        activity('teams')->performedOn($tier)->causedBy($by)
            ->withProperties(['features' => $tier->features])
            ->event('tier.created')->log('Tier created');

        return $tier;
    }

    /**
     * @param  array{name_en: string, name_ar?: string|null, slug?: string|null, features: array<string, mixed>}  $data
     */
    public function update(Tier $tier, array $data, ?User $by = null): Tier
    {
        $before = $tier->features;

        $tier->update([
            'name_en' => $data['name_en'],
            'name_ar' => $data['name_ar'] ?? null,
            'slug' => filled($data['slug'] ?? null) ? $data['slug'] : $tier->slug,
            'features' => self::normalize($data['features']),
        ]);

        $this->features->onTierChanged($tier);

        activity('teams')->performedOn($tier)->causedBy($by)
            ->withProperties(['old' => $before, 'features' => $tier->features])
            ->event('tier.updated')->log('Tier updated');

        return $tier;
    }

    public function setDefault(Tier $tier, ?User $by = null): void
    {
        DB::transaction(function () use ($tier): void {
            Tier::query()->whereKeyNot($tier->getKey())->update(['is_default' => false]);
            $tier->forceFill(['is_default' => true])->save();
        });

        activity('teams')->performedOn($tier)->causedBy($by)
            ->event('tier.default')->log('Default tier changed');
    }

    public function delete(Tier $tier, ?User $by = null): void
    {
        if ($tier->is_default) {
            throw DomainRuleViolation::with(['tier' => __('teams.errors.tier_is_default')]);
        }

        if ($tier->teams()->exists()) {
            throw DomainRuleViolation::with(['tier' => __('teams.errors.tier_in_use')]);
        }

        activity('teams')->causedBy($by)
            ->withProperties(['slug' => $tier->slug, 'name' => $tier->name_en])
            ->event('tier.deleted')->log('Tier deleted');

        $tier->delete();
    }

    /**
     * Keep only known features, each in its type, with fallbacks for any left out.
     *
     * @param  array<string, mixed>  $features
     * @return array<string, bool|int>
     */
    public static function normalize(array $features): array
    {
        return collect(TeamFeature::cases())
            ->mapWithKeys(fn (TeamFeature $feature): array => [
                $feature->value => $feature->normalize($features[$feature->value] ?? $feature->fallback()),
            ])
            ->all();
    }
}
