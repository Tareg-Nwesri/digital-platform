<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Production seed data (LLD 11 §5): permissions, tiers, the super admin,
 * the funds and the foundation's reference data. Run once after the first
 * migration; every seeder matches existing rows by slug, key or email, so
 * running it again adds only what is missing and never duplicates.
 * Demo data lives in DemoSeeder.
 */
class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            PermissionCatalogueSeeder::class,
            TierSeeder::class,
            SuperAdminSeeder::class,
            FinanceSeeder::class,
            ReferenceDataSeeder::class,
        ]);
    }
}
