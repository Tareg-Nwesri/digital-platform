<?php

use App\Http\Controllers\Auth\InvitationController;
use App\Http\Controllers\Console\DashboardController as ConsoleDashboardController;
use App\Http\Controllers\Console\DepartmentDashboardController;
use App\Http\Controllers\Console\ImpersonationController;
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

        Route::prefix('departments/{department:slug}')->name('departments.')->middleware('workspace:department')->group(function () {
            Route::get('/', DepartmentDashboardController::class)->name('dashboard');
        });
    });

    Route::prefix('t/{team:slug}')->name('workspace.')->middleware(['workspace:team', 'team.active'])->group(function () {
        Route::get('/', WorkspaceDashboardController::class)->name('dashboard');
    });
});

require __DIR__.'/settings.php';
