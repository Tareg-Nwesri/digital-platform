<?php

use App\Domain\Access\Models\Workspace;
use App\Domain\Access\Services\WorkspaceContext;
use App\Domain\Finance\Enums\SponsorType;
use App\Domain\Finance\Models\Sponsor;
use App\Domain\Organisation\Services\DepartmentPermissionService;
use App\Domain\Organisation\Services\DepartmentService;
use App\Models\User;
use Database\Seeders\PermissionCatalogueSeeder;
use Database\Seeders\TierSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

/*
 * Sponsors keep their logo. Finance editors upload, replace or remove it;
 * the logo shows in the console, and on the public home page only for
 * active sponsors who agreed to be shown.
 */

beforeEach(function () {
    Storage::fake('public');
    $this->seed([PermissionCatalogueSeeder::class, TierSeeder::class]);

    $superAdmin = User::factory()->create();
    app(WorkspaceContext::class)->within(Workspace::platform(), fn () => $superAdmin->assignRole(User::SUPER_ADMIN));
    $departments = app(DepartmentService::class);
    $finance = $departments->create(['name_en' => 'Finance']);
    app(DepartmentPermissionService::class)->setCeiling($finance, ['finance.view', 'finance.edit'], $superAdmin->fresh());
    $this->editor = User::factory()->create();
    $this->viewer = User::factory()->create();
    $admin = User::factory()->create();
    $departments->addMember($finance, $admin, isAdmin: true);
    $departments->addMember($finance, $this->editor);
    $departments->addMember($finance, $this->viewer);
    app(DepartmentPermissionService::class)->syncMember($finance, $this->editor, ['finance.view', 'finance.edit'], $admin);
    app(DepartmentPermissionService::class)->syncMember($finance, $this->viewer, ['finance.view'], $admin);

    $this->sponsor = Sponsor::query()->create(['name_en' => 'Libyana', 'type' => SponsorType::Company, 'public_consent' => true, 'is_active' => true]);
});

it('uploads, replaces and removes a sponsor logo', function () {
    $this->actingAs($this->editor)
        ->post(route('console.finance.sponsors.logo', $this->sponsor), ['logo' => UploadedFile::fake()->image('logo.png', 600, 300)])
        ->assertRedirect();
    $this->actingAs($this->editor)
        ->post(route('console.finance.sponsors.logo', $this->sponsor), ['logo' => UploadedFile::fake()->image('new.png', 400, 400)])
        ->assertRedirect();

    $logo = $this->sponsor->fresh()->getMedia('logo');
    expect($logo)->toHaveCount(1)
        ->and($logo->first()->file_name)->toBe('new.png')
        ->and($logo->first()->hasGeneratedConversion('thumb'))->toBeTrue();

    $this->actingAs($this->editor)
        ->get(route('console.finance.sponsors.show', $this->sponsor))
        ->assertInertia(fn (Assert $page) => $page->whereType('sponsor.logo', 'string'));

    $this->actingAs($this->editor)->delete(route('console.finance.sponsors.logo.destroy', $this->sponsor))->assertRedirect();
    expect($this->sponsor->fresh()->getMedia('logo'))->toBeEmpty();
});

it('refuses SVG and other non-image files', function (UploadedFile $file) {
    $this->actingAs($this->editor)
        ->post(route('console.finance.sponsors.logo', $this->sponsor), ['logo' => $file])
        ->assertSessionHasErrors('logo');

    expect($this->sponsor->fresh()->getMedia('logo'))->toBeEmpty();
})->with([
    'svg (can carry scripts)' => fn () => UploadedFile::fake()->createWithContent('logo.svg', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>'),
    'pdf' => fn () => UploadedFile::fake()->create('logo.pdf', 10, 'application/pdf'),
]);

it('lets only finance editors change logos', function () {
    $this->actingAs($this->viewer)
        ->post(route('console.finance.sponsors.logo', $this->sponsor), ['logo' => UploadedFile::fake()->image('logo.png', 300, 300)])
        ->assertForbidden();
});

it('shows the logos of consenting active sponsors on the home page', function () {
    $this->sponsor->addMedia(UploadedFile::fake()->image('logo.png', 400, 200))->toMediaCollection('logo');
    $hidden = Sponsor::query()->create(['name_en' => 'Quiet donor', 'type' => SponsorType::Individual, 'public_consent' => false, 'is_active' => true]);
    $hidden->addMedia(UploadedFile::fake()->image('quiet.png', 400, 200))->toMediaCollection('logo');
    Sponsor::query()->create(['name_en' => 'No logo yet', 'type' => SponsorType::Company, 'public_consent' => true, 'is_active' => true]);

    $this->get('/en')
        ->assertInertia(fn (Assert $page) => $page
            ->has('sponsors', 1)
            ->where('sponsors.0.name', 'Libyana')
            ->whereType('sponsors.0.logo', 'string'));
});
