<?php

namespace Database\Factories;

use App\Domain\Teams\Models\Tier;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Tier>
 */
class TierFactory extends Factory
{
    protected $model = Tier::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->word();

        return [
            'name_en' => Str::title($name),
            'name_ar' => null,
            'slug' => Str::slug($name).'-'.Str::lower(Str::random(4)),
            'features' => self::starterFeatures(),
            'is_default' => false,
            'position' => 0,
        ];
    }

    /**
     * SRS Appendix B.2 "Starter" example.
     *
     * @return array<string, bool|int>
     */
    public static function starterFeatures(): array
    {
        return [
            'members.max' => 8,
            'projects.create' => true,
            'projects.publish' => false,
            'robots.create' => true,
            'parts.access' => true,
            'parts.suggest' => false,
            'events.register' => false,
            'public.profile' => false,
            'budget.request' => false,
        ];
    }

    /**
     * SRS Appendix B.2 "Competition" example.
     *
     * @return array<string, bool|int>
     */
    public static function competitionFeatures(): array
    {
        return array_merge(self::starterFeatures(), [
            'members.max' => 20,
            'projects.publish' => true,
            'parts.suggest' => true,
            'events.register' => true,
            'public.profile' => true,
            'budget.request' => true,
        ]);
    }

    public function competition(): static
    {
        return $this->state(fn () => ['features' => self::competitionFeatures()]);
    }

    public function asDefault(): static
    {
        return $this->state(fn () => ['is_default' => true]);
    }
}
