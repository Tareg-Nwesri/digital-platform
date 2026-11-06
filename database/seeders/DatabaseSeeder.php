<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Production-safe seed data (LLD 11 §5). Demo data lives in DemoSeeder.
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
        ]);
    }
}
