<?php

use App\Http\Controllers\Auth\InvitationController;
use App\Http\Controllers\Console\DashboardController as ConsoleDashboardController;
use App\Http\Controllers\Console\DepartmentCeilingController;
use App\Http\Controllers\Console\DepartmentController;
use App\Http\Controllers\Console\DepartmentInvitationController;
use App\Http\Controllers\Console\DepartmentMemberController;
use App\Http\Controllers\Console\DepartmentPermissionController;
use App\Http\Controllers\Console\ImpersonationController;
use App\Http\Controllers\Console\TeamController;
use App\Http\Controllers\Console\TeamFeatureController;
use App\Http\Controllers\Console\TeamStatusController;
use App\Http\Controllers\Console\TeamTierController;
use App\Http\Controllers\HubController;
use App\Http\Controllers\LocaleController;
use App\Http\Controllers\Workspace\DashboardController as WorkspaceDashboardController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'Welcome')->name('home');

Route::post('locale/{locale}', LocaleController::class)
    ->whereIn('locale', ['en', 'ar'])
    ->name('locale.update');

Route::middleware('throttle:auth')->group(function () {
    Route::get('invitations/{token}', [InvitationController::class, 'show'])->name('invitations.show');
    Route::post('invitations/{token}', [InvitationController::class, 'accept'])->name('invitations.accept');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('hub', HubController::class)->name('hub');
    Route::get('dashboard', [HubController::class, 'home'])->name('dashboard');

    Route::post('admin/users/{user}/impersonate', [ImpersonationController::class, 'take'])
        ->middleware('not-impersonating')
        ->name('impersonate.take');
    Route::post('impersonate/leave', [ImpersonationController::class, 'leave'])->name('impersonate.leave');

    Route::prefix('admin')->name('console.')->group(function () {
        Route::get('/', ConsoleDashboardController::class)
            ->middleware(['super-admin', 'workspace:platform'])
            ->name('dashboard');

        Route::middleware(['super-admin', 'workspace:platform'])->group(function () {
            Route::get('departments', [DepartmentController::class, 'index'])->name('departments.index');
            Route::post('departments', [DepartmentController::class, 'store'])->name('departments.store');
        });

        Route::prefix('teams')->name('teams.')->middleware('workspace:platform')->group(function () {
            Route::get('/', [TeamController::class, 'index'])->name('index');
            Route::post('/', [TeamController::class, 'store'])->name('store');
            Route::get('{team:slug}', [TeamController::class, 'show'])->name('show');
            Route::patch('{team:slug}', [TeamController::class, 'update'])->name('update');
            Route::patch('{team:slug}/status', TeamStatusController::class)->name('status');
            Route::patch('{team:slug}/tier', TeamTierController::class)->name('tier');
            Route::put('{team:slug}/features', TeamFeatureController::class)->name('features');
        });

        Route::prefix('departments/{department:slug}')->name('departments.')->middleware('workspace:department')->group(function () {
            Route::get('/', [DepartmentController::class, 'show'])->name('show');
            Route::patch('/', [DepartmentController::class, 'update'])->name('update');
            Route::put('ceiling', DepartmentCeilingController::class)->name('ceiling');
            Route::patch('members/{user}/admin', [DepartmentMemberController::class, 'setAdmin'])->name('members.admin');
            Route::delete('members/{user}', [DepartmentMemberController::class, 'destroy'])->name('members.destroy');
            Route::put('members/{user}/permissions', DepartmentPermissionController::class)
                ->middleware('not-impersonating')
                ->name('permissions.sync');
            Route::post('invitations', [DepartmentInvitationController::class, 'store'])->name('invitations.store');
            Route::delete('invitations/{invitation}', [DepartmentInvitationController::class, 'destroy'])->name('invitations.destroy');
        });
    });

    Route::prefix('t/{team:slug}')->name('workspace.')->middleware(['workspace:team', 'team.active'])->group(function () {
        Route::get('/', WorkspaceDashboardController::class)->name('dashboard');
    });
});

require __DIR__.'/settings.php';

// Unknown URLs still run the web middleware, so the 404 page knows the
// viewer's language and whether to link them to the hub.
Route::any('{fallbackPlaceholder}', fn () => abort(404))
    ->where('fallbackPlaceholder', '.*')
    ->fallback();
