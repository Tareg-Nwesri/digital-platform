<?php

use App\Http\Controllers\Auth\InvitationController;
use App\Http\Controllers\Console\AuditController;
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
use App\Http\Controllers\Console\EventMediaController;
use App\Http\Controllers\Console\EventRegistrationController;
use App\Http\Controllers\Console\EventSetupController;
use App\Http\Controllers\Console\FinanceAccountController;
use App\Http\Controllers\Console\FinanceController;
use App\Http\Controllers\Console\ImpersonationController;
use App\Http\Controllers\Console\InboxController;
use App\Http\Controllers\Console\PayoutController;
use App\Http\Controllers\Console\PostController;
use App\Http\Controllers\Console\ReceiptController;
use App\Http\Controllers\Console\ReportController;
use App\Http\Controllers\Console\SettingsController;
use App\Http\Controllers\Console\SponsorController;
use App\Http\Controllers\Console\SponsorshipController;
use App\Http\Controllers\Console\TeamBudgetController;
use App\Http\Controllers\Console\TeamController;
use App\Http\Controllers\Console\TeamFeatureController;
use App\Http\Controllers\Console\TeamStatusController;
use App\Http\Controllers\Console\TeamTierController;
use App\Http\Controllers\Console\TierController;
use App\Http\Controllers\Console\TransactionController;
use App\Http\Controllers\Console\UserController;
use App\Http\Controllers\HubController;
use App\Http\Controllers\LocaleController;
use App\Http\Controllers\Public\EventController as PublicEventController;
use App\Http\Controllers\Public\SiteController;
use App\Http\Controllers\Workspace\BudgetController as WorkspaceBudgetController;
use App\Http\Controllers\Workspace\CatalogueController as WorkspaceCatalogueController;
use App\Http\Controllers\Workspace\DashboardController as WorkspaceDashboardController;
use App\Http\Controllers\Workspace\EventController as WorkspaceEventController;
use App\Http\Controllers\Workspace\MemberController as WorkspaceMemberController;
use App\Http\Controllers\Workspace\ProjectController as WorkspaceProjectController;
use App\Http\Controllers\Workspace\RobotController as WorkspaceRobotController;
use App\Http\Controllers\Workspace\SettingsController as WorkspaceSettingsController;
use Illuminate\Auth\Middleware\RequirePassword;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => redirect('/'.app()->getLocale()))->name('home');
Route::get('sitemap.xml', [SiteController::class, 'sitemap'])->name('public.sitemap');

Route::prefix('{locale}')->whereIn('locale', ['en', 'ar'])->name('public.')->group(function () {
    Route::get('/', [SiteController::class, 'home'])->name('home');
    Route::get('about', [SiteController::class, 'about'])->name('about');
    Route::get('get-involved', [SiteController::class, 'getInvolved'])->name('get-involved');
    Route::get('faq', [SiteController::class, 'faq'])->name('faq');
    Route::get('privacy', [SiteController::class, 'privacy'])->name('privacy');
    Route::get('contact', [SiteController::class, 'contact'])->name('contact');
    Route::post('contact', [SiteController::class, 'storeContact'])->middleware('throttle:5,1')->name('contact.store');
    Route::get('teams', [SiteController::class, 'teams'])->name('teams.index');
    Route::get('teams/{slug}', [SiteController::class, 'team'])->name('teams.show');
    Route::get('projects', [SiteController::class, 'projects'])->name('projects.index');
    Route::get('projects/{slug}', [SiteController::class, 'project'])->name('projects.show');
    Route::get('news', [SiteController::class, 'news'])->name('news.index');
    Route::get('news/{slug}', [SiteController::class, 'article'])->name('news.show');
});

Route::post('locale/{locale}', LocaleController::class)
    ->whereIn('locale', ['en', 'ar'])
    ->name('locale.update');

Route::prefix('{locale}/events')->whereIn('locale', ['en', 'ar'])->name('public.events.')->group(function () {
    Route::get('/', [PublicEventController::class, 'index'])->name('index');
    Route::get('{event:slug}', [PublicEventController::class, 'show'])->name('show');
    Route::get('{event:slug}/register', [PublicEventController::class, 'create'])->name('register');
    Route::post('{event:slug}/register', [PublicEventController::class, 'store'])
        ->middleware('throttle:10,1')
        ->name('register.store');
    Route::get('{event:slug}/registered', [PublicEventController::class, 'registered'])->name('registered');
    Route::get('{event:slug}/documents/{media}', [PublicEventController::class, 'document'])->whereNumber('media')->name('documents');
});

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
            Route::get('audit', [AuditController::class, 'index'])->name('audit.index');
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
            Route::post('{event:slug}/cover', [EventMediaController::class, 'cover'])->name('cover');
            Route::delete('{event:slug}/cover', [EventMediaController::class, 'destroyCover'])->name('cover.destroy');
            Route::post('{event:slug}/documents', [EventMediaController::class, 'storeDocument'])->name('documents.store');
            Route::delete('{event:slug}/documents/{media}', [EventMediaController::class, 'destroyDocument'])->whereNumber('media')->name('documents.destroy');
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

            Route::get('sponsors', [SponsorController::class, 'index'])->name('sponsors.index');
            Route::post('sponsors', [SponsorController::class, 'store'])->name('sponsors.store');
            Route::get('sponsors/{sponsor}', [SponsorController::class, 'show'])->name('sponsors.show');
            Route::patch('sponsors/{sponsor}', [SponsorController::class, 'update'])->name('sponsors.update');
            Route::post('sponsors/{sponsor}/contacts', [SponsorController::class, 'storeContact'])->name('sponsors.contacts.store');
            Route::delete('sponsors/{sponsor}/contacts/{contact}', [SponsorController::class, 'destroyContact'])->name('sponsors.contacts.destroy');
            Route::post('sponsors/{sponsor}/sponsorships', [SponsorshipController::class, 'store'])->name('sponsorships.store');
            Route::get('sponsorships/{sponsorship}', [SponsorshipController::class, 'show'])->name('sponsorships.show');
            Route::patch('sponsorships/{sponsorship}', [SponsorshipController::class, 'update'])->name('sponsorships.update');

            Route::get('payouts', [PayoutController::class, 'index'])->name('payouts.index');
            Route::post('payouts', [PayoutController::class, 'store'])->name('payouts.store');
            Route::post('payouts/{payout}/submit', [PayoutController::class, 'submit'])->name('payouts.submit');
            Route::post('payouts/{payout}/cancel', [PayoutController::class, 'cancel'])->name('payouts.cancel');

            Route::get('budgets', [TeamBudgetController::class, 'index'])->name('budgets.index');
            Route::post('requests/{funding}/reject', [TeamBudgetController::class, 'rejectRequest'])->name('requests.reject');
            Route::post('requests/{funding}/changes', [TeamBudgetController::class, 'changesRequest'])->name('requests.changes');
            Route::post('expenses/{expense}/reject', [TeamBudgetController::class, 'rejectExpense'])->name('expenses.reject');
            Route::get('expenses/{expense}/receipt', [TeamBudgetController::class, 'receipt'])->name('expenses.receipt');

            Route::get('receipts', [ReceiptController::class, 'index'])->name('receipts.index');
            Route::post('receipts', [ReceiptController::class, 'store'])->name('receipts.store');
            Route::get('receipts/{receipt}', [ReceiptController::class, 'show'])->name('receipts.show');
            Route::post('receipts/{receipt}/submit', [ReceiptController::class, 'submit'])->name('receipts.submit');

            Route::middleware(['not-impersonating', RequirePassword::class])->group(function () {
                Route::get('approvals', [FinanceController::class, 'approvals'])->name('approvals');
                Route::post('transactions/{transaction}/approve', [TransactionController::class, 'approve'])->name('transactions.approve');
                Route::post('transactions/{transaction}/reject', [TransactionController::class, 'reject'])->name('transactions.reject');
                Route::post('transactions/{transaction}/reverse', [TransactionController::class, 'reverse'])->name('transactions.reverse');
                Route::post('periods/{period}/close', [FinanceController::class, 'closePeriod'])->name('periods.close');
                Route::post('receipts/{receipt}/post', [ReceiptController::class, 'post'])->name('receipts.post');
                Route::post('receipts/{receipt}/reject', [ReceiptController::class, 'reject'])->name('receipts.reject');
                Route::post('payouts/{payout}/approve', [PayoutController::class, 'approve'])->name('payouts.approve');
                Route::post('payouts/{payout}/reject', [PayoutController::class, 'reject'])->name('payouts.reject');
                Route::post('payouts/{payout}/paid', [PayoutController::class, 'paid'])->name('payouts.paid');
                Route::get('budgets/review', [TeamBudgetController::class, 'review'])->name('budgets.review');
                Route::post('requests/{funding}/approve', [TeamBudgetController::class, 'approveRequest'])->name('requests.approve');
                Route::post('expenses/{expense}/approve', [TeamBudgetController::class, 'approveExpense'])->name('expenses.approve');
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

        Route::get('departments/{department:slug}/report', [ReportController::class, 'department'])
            ->middleware('workspace:department')
            ->name('reports.department');

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
        Route::get('members', [WorkspaceMemberController::class, 'index'])->name('members.index');
        Route::post('members/offline', [WorkspaceMemberController::class, 'offline'])->name('members.offline');
        Route::patch('members/{user}/role', [WorkspaceMemberController::class, 'role'])->name('members.role');
        Route::post('members/{user}/captain', [WorkspaceMemberController::class, 'captain'])->name('members.captain');
        Route::delete('members/{user}', [WorkspaceMemberController::class, 'destroy'])->name('members.destroy');
        Route::post('invitations', [WorkspaceMemberController::class, 'invite'])->name('invitations.store');
        Route::delete('invitations/{invitation}', [WorkspaceMemberController::class, 'revokeInvitation'])->name('invitations.destroy');
        Route::post('leave', [WorkspaceMemberController::class, 'leave'])->name('leave');

        Route::get('projects', [WorkspaceProjectController::class, 'index'])->name('projects.index');
        Route::post('projects', [WorkspaceProjectController::class, 'store'])->name('projects.store');
        Route::get('projects/{project:slug}/edit', [WorkspaceProjectController::class, 'edit'])->name('projects.edit');
        Route::patch('projects/{project:slug}', [WorkspaceProjectController::class, 'update'])->name('projects.update');
        Route::delete('projects/{project:slug}', [WorkspaceProjectController::class, 'destroy'])->name('projects.destroy');
        Route::post('projects/{project:slug}/publish', [WorkspaceProjectController::class, 'publish'])->name('projects.publish');
        Route::delete('projects/{project:slug}/publish', [WorkspaceProjectController::class, 'unpublish'])->name('projects.unpublish');

        Route::get('robots', [WorkspaceRobotController::class, 'index'])->name('robots.index');
        Route::post('robots', [WorkspaceRobotController::class, 'store'])->name('robots.store');
        Route::get('robots/{robot:slug}', [WorkspaceRobotController::class, 'show'])->name('robots.show');
        Route::patch('robots/{robot:slug}', [WorkspaceRobotController::class, 'update'])->name('robots.update');
        Route::delete('robots/{robot:slug}', [WorkspaceRobotController::class, 'destroy'])->name('robots.destroy');
        Route::put('robots/{robot:slug}/bom', [WorkspaceRobotController::class, 'bom'])->name('robots.bom');
        Route::get('catalogue/parts/search', [WorkspaceRobotController::class, 'search'])->name('catalogue.search');
        Route::get('catalogue', [WorkspaceCatalogueController::class, 'index'])->name('catalogue.index');
        Route::post('catalogue/parts', [WorkspaceCatalogueController::class, 'storePart'])->name('catalogue.parts.store');
        Route::post('catalogue/vendors', [WorkspaceCatalogueController::class, 'storeVendor'])->name('catalogue.vendors.store');
        Route::post('catalogue/offers', [WorkspaceCatalogueController::class, 'storeOffer'])->name('catalogue.offers.store');

        Route::get('budget', [WorkspaceBudgetController::class, 'index'])->name('budget.index');
        Route::post('budget/requests', [WorkspaceBudgetController::class, 'storeRequest'])->name('budget.requests.store');
        Route::put('budget/requests/{funding}', [WorkspaceBudgetController::class, 'updateRequest'])->name('budget.requests.update');
        Route::post('budget/requests/{funding}/cancel', [WorkspaceBudgetController::class, 'cancelRequest'])->name('budget.requests.cancel');
        Route::post('budget/expenses', [WorkspaceBudgetController::class, 'storeExpense'])->name('budget.expenses.store');
        Route::get('budget/expenses/{expense}/receipt', [WorkspaceBudgetController::class, 'receipt'])->name('budget.expenses.receipt');

        Route::get('settings', [WorkspaceSettingsController::class, 'edit'])->name('settings.edit');
        Route::patch('settings', [WorkspaceSettingsController::class, 'update'])->name('settings.update');
        Route::post('settings/logo', [WorkspaceSettingsController::class, 'logo'])->name('settings.logo');
        Route::delete('settings/logo', [WorkspaceSettingsController::class, 'destroyLogo'])->name('settings.logo.destroy');

        Route::get('events', [WorkspaceEventController::class, 'index'])->name('events.index');
        Route::get('events/{event:slug}', [WorkspaceEventController::class, 'show'])->withoutScopedBindings()->name('events.show');
        Route::post('events/{event:slug}/entries', [WorkspaceEventController::class, 'apply'])->withoutScopedBindings()->name('events.apply');
        Route::post('entries/{registration}/accept', [WorkspaceEventController::class, 'accept'])->name('entries.accept');
        Route::post('entries/{registration}/withdraw', [WorkspaceEventController::class, 'withdraw'])->name('entries.withdraw');
    });
});

require __DIR__.'/settings.php';

// Unknown URLs still run the web middleware, so the 404 page knows the
// viewer's language and whether to link them to the hub.
Route::any('{fallbackPlaceholder}', fn () => abort(404))
    ->where('fallbackPlaceholder', '.*')
    ->fallback();
