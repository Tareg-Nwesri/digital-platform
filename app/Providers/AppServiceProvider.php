<?php

namespace App\Providers;

use App\Domain\Access\Listeners\LogImpersonation;
use App\Domain\Access\Services\WorkspaceContext;
use App\Domain\Catalogue\Policies\CataloguePolicy;
use App\Domain\Content\Models\ContactMessage;
use App\Domain\Content\Models\Post;
use App\Domain\Content\Policies\ContactMessagePolicy;
use App\Domain\Content\Policies\PostPolicy;
use App\Domain\Events\Models\Event as EventModel;
use App\Domain\Events\Policies\EventPolicy;
use App\Domain\Finance\Policies\FinancePolicy;
use App\Domain\Organisation\Models\Department;
use App\Domain\Organisation\Policies\DepartmentPolicy;
use App\Domain\Teams\Models\Team;
use App\Domain\Teams\Policies\TeamPolicy;
use App\Domain\Teams\Services\TeamFeatures;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Lab404\Impersonate\Events\LeaveImpersonation;
use Lab404\Impersonate\Events\TakeImpersonation;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->scoped(WorkspaceContext::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->configureAuthorization();
        TeamFeatures::define();
    }

    /**
     * The super admin passes every gate check (HLD 6.4). Routes that must stay
     * blocked while impersonating are guarded separately by middleware.
     */
    protected function configureAuthorization(): void
    {
        Gate::policy(Department::class, DepartmentPolicy::class);
        Gate::policy(Team::class, TeamPolicy::class);
        Gate::policy(EventModel::class, EventPolicy::class);
        CataloguePolicy::register();
        FinancePolicy::register();
        Gate::policy(Post::class, PostPolicy::class);
        Gate::policy(ContactMessage::class, ContactMessagePolicy::class);

        Gate::before(fn (User $user, string $ability): ?bool => ! in_array($ability, ['impersonate', 'toggle-active'], true) && $user->isSuperAdmin() ? true : null);

        Gate::define('impersonate', fn (User $actor, User $target): bool => $actor->canImpersonate()
            && $actor->isNot($target)
            && $target->canBeImpersonated());

        Gate::define('toggle-active', fn (User $actor, User $target): bool => $actor->isSuperAdmin()
            && $actor->isNot($target)
            && ! $target->isSuperAdmin());

        Event::listen([TakeImpersonation::class, LeaveImpersonation::class], LogImpersonation::class);
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}
