<?php

use App\Domain\Access\Models\Workspace;
use App\Domain\Access\Services\WorkspaceContext;
use App\Domain\Catalogue\Models\PartCategory;
use App\Domain\Content\Services\SettingsRepository;
use App\Domain\Finance\Models\FinanceAccount;
use App\Domain\Organisation\Models\Department;
use App\Domain\Teams\Models\Tier;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\DemoSeeder;

/*
 * Production seeding: real reference data only (departments, part
 * categories, tiers, funds, settings), seeded once after the first
 * migration. Seeding again never duplicates and never overwrites what
 * admins changed since.
 */

beforeEach(function () {
    config(['platform.super_admin.email' => 'admin@seed.test']);
});

function referenceCounts(): array
{
    return [
        'departments' => Department::query()->count(),
        'categories' => PartCategory::query()->count(),
        'tiers' => Tier::query()->count(),
        'funds' => FinanceAccount::query()->count(),
        'admins' => User::query()->count(),
    ];
}

it('seeds the reference data with Arabic names and department ceilings', function () {
    $this->seed(DatabaseSeeder::class);

    expect(Department::query()->orderBy('slug')->pluck('slug')->all())->toBe(['events', 'finance', 'media', 'procurement'])
        ->and(Department::query()->where('slug', 'media')->value('name_ar'))->toBe('الإعلام')
        ->and(PartCategory::query()->count())->toBe(12)
        ->and(PartCategory::query()->where('slug', 'sensors')->value('name_ar'))->toBe('الحساسات')
        ->and(Department::query()->where('slug', 'events')->sole()->ceiling()->pluck('name')->sort()->values()->all())
        ->toBe(['events.edit', 'events.view', 'reports.view', 'teams.view']);

    $settings = app(SettingsRepository::class);
    expect($settings->get('inbox.department_id'))->toBe(Department::query()->where('slug', 'media')->value('id'))
        ->and($settings->get('finance.department_id'))->toBe(Department::query()->where('slug', 'finance')->value('id'));
});

it('seeds nothing twice when it runs again', function () {
    $this->seed(DatabaseSeeder::class);
    $first = referenceCounts();
    $ceiling = Department::query()->where('slug', 'events')->sole()->ceiling()->count();

    $this->seed(DatabaseSeeder::class);

    expect(referenceCounts())->toBe($first)
        ->and(Department::query()->where('slug', 'events')->sole()->ceiling()->count())->toBe($ceiling);
});

it('keeps what admins changed when it runs again', function () {
    $this->seed(DatabaseSeeder::class);

    $media = Department::query()->where('slug', 'media')->sole();
    $media->update(['name_en' => 'Media & Communication']);
    PartCategory::query()->where('slug', 'motors')->update(['name_en' => 'Motors and drivers']);
    $finance = Department::query()->where('slug', 'finance')->value('id');
    app(SettingsRepository::class)->set('inbox.department_id', $finance);

    $this->seed(DatabaseSeeder::class);

    expect($media->fresh()->name_en)->toBe('Media & Communication')
        ->and(PartCategory::query()->where('slug', 'motors')->value('name_en'))->toBe('Motors and drivers')
        ->and(app(SettingsRepository::class)->get('inbox.department_id'))->toBe($finance);
});

it('lets the demo data reuse the seeded departments locally', function () {
    $this->seed(DatabaseSeeder::class);
    $this->seed(DemoSeeder::class);

    expect(Department::query()->count())->toBe(4)
        ->and(Department::query()->where('slug', 'like', '%-2')->exists())->toBeFalse();
});

it('makes the super admin once', function () {
    $this->seed(DatabaseSeeder::class);
    $this->seed(DatabaseSeeder::class);

    $admin = User::query()->where('email', 'admin@seed.test')->sole();
    expect(app(WorkspaceContext::class)->within(Workspace::platform(), fn () => $admin->hasRole(User::SUPER_ADMIN)))->toBeTrue();
});
