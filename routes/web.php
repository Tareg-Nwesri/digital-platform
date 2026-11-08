<?php

use App\Http\Controllers\Auth\InvitationController;
use App\Http\Controllers\Console\CatalogueCategoryController;
use App\Http\Controllers\Console\CataloguePartController;
use App\Http\Controllers\Console\CatalogueSuggestionController;
use App\Http\Controllers\Console\CatalogueVendorController;
use App\Http\Controllers\Console\DashboardController as ConsoleDashboardController;
use App\Http\Controllers\Console\DepartmentCeilingController;
use App\Http\Controllers\Console\DepartmentController;
use App\Http\Controllers\Console\DepartmentInvitationController;
use App\Http\Controllers\Console\DepartmentMemberController;
use App\Http\Controllers\Console\DepartmentPermissionController;
use App\Http\Controllers\Console\EventController;
use App\Http\Controllers\Console\EventRegistrationController;
use App\Http\Controllers\Console\EventSetupController;
use App\Http\Controllers\Console\FinanceAccountController;
use App\Http\Controllers\Console\FinanceController;
use App\Http\Controllers\Console\ImpersonationController;
use App\Http\Controllers\Console\InboxController;
use App\Http\Controllers\Console\PostController;
use App\Http\Controllers\Console\SettingsController;
use App\Http\Controllers\Console\TeamController;
use App\Http\Controllers\Console\TeamFeatureController;
use App\Http\Controllers\Console\TeamStatusController;
use App\Http\Controllers\Console\TeamTierController;
use App\Http\Controllers\Console\TierController;
use App\Http\Controllers\Console\TransactionController;
use App\Http\Controllers\Console\UserController;
use App\Http\Controllers\HubController;
use App\Http\Controllers\LocaleController;
use App\Http\Controllers\Workspace\DashboardController as WorkspaceDashboardController;
use Illuminate\Auth\Middleware\RequirePassword;
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
            Route::get('users', [UserController::class, 'index'])->name('users.index');
            Route::get('tiers', [TierController::class, 'index'])->name('tiers.index');
            Route::get('settings', [SettingsController::class, 'index'])->name('settings.index');
            Route::put('settings', [SettingsController::class, 'update'])->name('settings.update');
            Route::post('tiers', [TierController::class, 'store'])->name('tiers.store');
            Route::patch('tiers/{tier:slug}', [TierController::class, 'update'])->name('tiers.update');
            Route::patch('tiers/{tier:slug}/default', [TierController::class, 'setDefault'])->name('tiers.default');
            Route::delete('tiers/{tier:slug}', [TierController::class, 'destroy'])->name('tiers.destroy');
            Route::patch('users/{user}/active', [UserController::class, 'toggleActive'])
                ->middleware('not-impersonating')
                ->name('users.active');
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

        Route::prefix('events')->name('events.')->middleware('workspace:platform')->group(function () {
            Route::get('/', [EventController::class, 'index'])->name('index');
            Route::post('/', [EventController::class, 'store'])->name('store');
            Route::get('{event:slug}', [EventController::class, 'show'])->name('show');
            Route::patch('{event:slug}', [EventController::class, 'update'])->name('update');
            Route::delete('{event:slug}', [EventController::class, 'destroy'])->name('destroy');
            Route::patch('{event:slug}/status', [EventSetupController::class, 'status'])->name('status');
            Route::put('{event:slug}/categories', [EventSetupController::class, 'categories'])->name('categories');
            Route::post('{event:slug}/invitations', [EventSetupController::class, 'invite'])->name('invitations.store');
            Route::delete('{event:slug}/invitations/{team}', [EventSetupController::class, 'uninvite'])->name('invitations.destroy');
            Route::post('{event:slug}/registrations', [EventRegistrationController::class, 'store'])->name('registrations.store');
            Route::post('{event:slug}/waitlist/promote', [EventRegistrationController::class, 'promote'])->name('waitlist.promote');
            Route::put('{event:slug}/results', [EventSetupController::class, 'results'])->name('results');
            Route::post('{event:slug}/results/publish', [EventSetupController::class, 'publishResults'])->name('results.publish');
        });

        Route::prefix('finance')->name('finance.')->middleware('workspace:platform')->group(function () {
            Route::get('/', [FinanceController::class, 'dashboard'])->name('dashboard');
            Route::get('accounts', [FinanceAccountController::class, 'index'])->name('accounts.index');
            Route::post('accounts', [FinanceAccountController::class, 'store'])->name('accounts.store');
            Route::get('accounts/{account}', [FinanceAccountController::class, 'show'])->name('accounts.show');
            Route::get('transactions', [TransactionController::class, 'index'])->name('transactions.index');
            Route::post('transactions', [TransactionController::class, 'store'])->name('transactions.store');
            Route::post('transactions/{transaction}/submit', [TransactionController::class, 'submit'])->name('transactions.submit');
            Route::get('periods', [FinanceController::class, 'periods'])->name('periods.index');

            Route::middleware(['not-impersonating', RequirePassword::class])->group(function () {
                Route::get('approvals', [FinanceController::class, 'approvals'])->name('approvals');
                Route::post('transactions/{transaction}/approve', [TransactionController::class, 'approve'])->name('transactions.approve');
                Route::post('transactions/{transaction}/reject', [TransactionController::class, 'reject'])->name('transactions.reject');
                Route::post('transactions/{transaction}/reverse', [TransactionController::class, 'reverse'])->name('transactions.reverse');
                Route::post('periods/{period}/close', [FinanceController::class, 'closePeriod'])->name('periods.close');
            });
        });

        Route::prefix('posts')->name('posts.')->middleware('workspace:platform')->group(function () {
            Route::get('/', [PostController::class, 'index'])->name('index');
            Route::post('/', [PostController::class, 'store'])->name('store');
            Route::get('{post}', [PostController::class, 'edit'])->name('edit');
            Route::patch('{post}', [PostController::class, 'update'])->name('update');
            Route::delete('{post}', [PostController::class, 'destroy'])->name('destroy');
            Route::post('{post}/submit', [PostController::class, 'submit'])->name('submit');
            Route::post('{post}/publish', [PostController::class, 'publish'])->name('publish');
            Route::post('{post}/unpublish', [PostController::class, 'unpublish'])->name('unpublish');
        });

        Route::prefix('inbox')->name('inbox.')->middleware('workspace:platform')->group(function () {
            Route::get('/', [InboxController::class, 'index'])->name('index');
            Route::get('{message}', [InboxController::class, 'show'])->name('show');
            Route::patch('{message}', [InboxController::class, 'update'])->name('update');
            Route::post('{message}/notes', [InboxController::class, 'notes'])->name('notes');
        });

        Route::prefix('catalogue')->name('catalogue.')->middleware('workspace:platform')->group(function () {
            Route::get('parts', [CataloguePartController::class, 'index'])->name('parts.index');
            Route::post('parts', [CataloguePartController::class, 'store'])->name('parts.store');
            Route::get('parts/{part}', [CataloguePartController::class, 'show'])->name('parts.show');
            Route::patch('parts/{part}', [CataloguePartController::class, 'update'])->name('parts.update');
            Route::delete('parts/{part}', [CataloguePartController::class, 'destroy'])->name('parts.destroy');
            Route::patch('parts/{part}/restore', [CataloguePartController::class, 'restore'])->name('parts.restore');
            Route::put('parts/{part}/offers', [CataloguePartController::class, 'offers'])->name('parts.offers');

            Route::get('vendors', [CatalogueVendorController::class, 'index'])->name('vendors.index');
            Route::post('vendors', [CatalogueVendorController::class, 'store'])->name('vendors.store');
            Route::patch('vendors/{vendor}', [CatalogueVendorController::class, 'update'])->name('vendors.update');
            Route::delete('vendors/{vendor}', [CatalogueVendorController::class, 'destroy'])->name('vendors.destroy');
            Route::patch('vendors/{vendor}/restore', [CatalogueVendorController::class, 'restore'])->name('vendors.restore');

            Route::get('categories', [CatalogueCategoryController::class, 'index'])->name('categories.index');
            Route::post('categories', [CatalogueCategoryController::class, 'store'])->name('categories.store');
            Route::patch('categories/{category}', [CatalogueCategoryController::class, 'update'])->name('categories.update');
            Route::delete('categories/{category}', [CatalogueCategoryController::class, 'destroy'])->name('categories.destroy');

            Route::get('suggestions', [CatalogueSuggestionController::class, 'index'])->name('suggestions.index');
            Route::post('suggestions/{type}/{id}/approve', [CatalogueSuggestionController::class, 'approve'])
                ->whereIn('type', ['part', 'vendor', 'offer'])->whereNumber('id')->name('suggestions.approve');
            Route::post('suggestions/{type}/{id}/reject', [CatalogueSuggestionController::class, 'reject'])
                ->whereIn('type', ['part', 'vendor', 'offer'])->whereNumber('id')->name('suggestions.reject');
            Route::post('suggestions/{type}/{id}/merge', [CatalogueSuggestionController::class, 'merge'])
                ->whereIn('type', ['part', 'vendor'])->whereNumber('id')->name('suggestions.merge');
        });

        Route::prefix('registrations/{registration}')->name('registrations.')->middleware('workspace:platform')->group(function () {
            Route::post('approve', [EventRegistrationController::class, 'approve'])->name('approve');
            Route::post('reject', [EventRegistrationController::class, 'reject'])->name('reject');
            Route::post('waitlist', [EventRegistrationController::class, 'waitlist'])->name('waitlist');
            Route::post('withdraw', [EventRegistrationController::class, 'withdraw'])->name('withdraw');
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
