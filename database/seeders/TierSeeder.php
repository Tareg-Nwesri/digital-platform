<?php

namespace Database\Seeders;

use App\Domain\Teams\Models\Tier;
use Database\Factories\TierFactory;
use Illuminate\Database\Seeder;

class TierSeeder extends Seeder
{
    public function run(): void
    {
        Tier::query()->updateOrCreate(['slug' => 'starter'], [
            'name_en' => 'Starter',
            'name_ar' => 'المبتدئ',
            'features' => TierFactory::starterFeatures(),
            'is_default' => true,
            'position' => 1,
        ]);

        Tier::query()->updateOrCreate(['slug' => 'competition'], [
            'name_en' => 'Competition',
            'name_ar' => 'المنافسات',
            'features' => TierFactory::competitionFeatures(),
            'is_default' => false,
            'position' => 2,
        ]);
    }
}
