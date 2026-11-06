<?php

use App\Domain\Access\Enums\WorkspaceType;
use App\Domain\Access\Models\Workspace;
use App\Domain\Access\Services\WorkspaceContext;
use App\Models\User;
use App\Support\Concerns\HasLocalizedFields;
use App\Support\LiteralLike;
use App\Support\Slugger;
use Illuminate\Support\Facades\Gate;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

it('scopes roles to the workspace set in the context', function () {
    $context = app(WorkspaceContext::class);
    $teamA = Workspace::open(WorkspaceType::Team);
    $teamB = Workspace::open(WorkspaceType::Team);
    $user = User::factory()->create();

    $context->within($teamA, function () use ($user, $teamA) {
        Role::create(['name' => 'captain', 'workspace_id' => $teamA->id]);
        $user->assignRole('captain');
    });
    $context->within($teamB, fn () => Role::create(['name' => 'captain', 'workspace_id' => $teamB->id]));

    $context->set($teamA, $teamA);
    expect($user->fresh()->hasRole('captain'))->toBeTrue();

    $context->set($teamB, $teamB);
    expect($user->fresh()->hasRole('captain'))->toBeFalse();

    $context->clear();
    expect(app(PermissionRegistrar::class)->getPermissionsTeamId())->toBeNull();
});

it('restores the previous scope after running within another workspace', function () {
    $context = app(WorkspaceContext::class);
    $team = Workspace::open(WorkspaceType::Team);
    $context->set($team, $team);

    $context->within(Workspace::platform(), fn () => null);

    expect(app(PermissionRegistrar::class)->getPermissionsTeamId())->toBe($team->id);
});

it('lets the super admin pass every gate through the platform workspace', function () {
    $admin = User::factory()->create();
    $member = User::factory()->create();
    $platform = Workspace::platform();

    app(WorkspaceContext::class)->within($platform, function () use ($admin, $platform) {
        Role::create(['name' => User::SUPER_ADMIN, 'workspace_id' => $platform->id]);
        $admin->assignRole(User::SUPER_ADMIN);
    });
    Gate::define('anything', fn () => false);

    expect($admin->fresh()->isSuperAdmin())->toBeTrue()
        ->and(Gate::forUser($admin->fresh())->allows('anything'))->toBeTrue()
        ->and(Gate::forUser($member)->allows('anything'))->toBeFalse()
        ->and($admin->fresh()->canImpersonate())->toBeTrue()
        ->and($admin->fresh()->canBeImpersonated())->toBeFalse()
        ->and($member->canBeImpersonated())->toBeTrue();
});

it('returns the arabic name on arabic pages and falls back to english', function () {
    $user = User::factory()->create(['name_en' => 'Ali', 'name_ar' => 'علي']);
    $noArabic = User::factory()->create(['name_en' => 'Sara', 'name_ar' => null]);

    app()->setLocale('ar');
    expect($user->name)->toBe('علي')->and($noArabic->name)->toBe('Sara');

    app()->setLocale('en');
    expect($user->name)->toBe('Ali');
});

it('builds unique slugs and escapes like wildcards', function () {
    User::factory()->create(['name_en' => '100% robots']);

    expect(Slugger::unique(Workspace::class, 'Robo Lions', column: 'type'))->toBe('robo-lions')
        ->and(LiteralLike::escape('100%_x'))->toBe('100\%\_x')
        ->and(User::query()->tap(fn ($q) => LiteralLike::where($q, 'name_en', '100%'))->count())->toBe(1)
        ->and(User::query()->tap(fn ($q) => LiteralLike::where($q, 'name_en', '1_0'))->count())->toBe(0)
        ->and(in_array(HasLocalizedFields::class, class_uses(User::class), true))->toBeTrue();
});
