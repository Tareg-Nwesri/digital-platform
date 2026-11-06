<?php

namespace Database\Seeders;

use App\Domain\Access\Models\Workspace;
use App\Domain\Access\Services\WorkspaceContext;
use App\Domain\Organisation\Services\PermissionCatalogue;
use App\Models\User;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class PermissionCatalogueSeeder extends Seeder
{
    public function run(PermissionCatalogue $catalogue, WorkspaceContext $context): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $teamPermissions = collect(config('permissions.team_roles', []))->flatten()->unique();

        foreach ($catalogue->all()->merge($teamPermissions)->unique() as $name) {
            Permission::findOrCreate($name, 'web');
        }

        $context->within(Workspace::platform(), function () use ($catalogue): void {
            Role::findOrCreate(User::SUPER_ADMIN, 'web')->syncPermissions($catalogue->reserved()->all());
        });
    }
}
