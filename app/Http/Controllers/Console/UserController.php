<?php

namespace App\Http\Controllers\Console;

use App\Domain\Access\Models\Workspace;
use App\Domain\Access\Services\WorkspaceContext;
use App\Http\Controllers\Controller;
use App\Http\Requests\Console\ToggleUserActiveRequest;
use App\Models\User;
use App\Support\Tables\TableQuery;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * LLD 01 §6/§8: every account on the platform, for the super admin.
 */
class UserController extends Controller
{
    public function __construct(private readonly WorkspaceContext $context) {}

    public function index(Request $request): Response
    {
        $actor = $request->user();

        $superAdminIds = $this->context->within(
            Workspace::platform(),
            fn (): array => User::role(User::SUPER_ADMIN)->pluck('users.id')->all(),
        );

        $query = User::query()->withCount(['teams', 'departments']);

        $users = TableQuery::for($query, $request)
            ->searchable(['name_en', 'name_ar', 'email'])
            ->sortable(['name_en', 'email', 'last_login_at', 'created_at'], default: 'name_en')
            ->filters([
                'active' => fn (Builder $query, mixed $value) => $query->where('is_active', filter_var($value, FILTER_VALIDATE_BOOLEAN)),
            ])
            ->paginate(fn (User $user): array => $this->row(
                $user->rememberSuperAdmin(in_array($user->getKey(), $superAdminIds, true)),
                $actor,
            ));

        return Inertia::render('console/users/Index', ['users' => $users]);
    }

    /**
     * @return array{id: int, name: string, email: string|null, isActive: bool, canLogin: bool, isSuperAdmin: bool, memberships: int, lastLoginAt: string|null, canImpersonate: bool, canToggleActive: bool}
     */
    private function row(User $user, User $actor): array
    {
        return [
            'id' => $user->getKey(),
            'name' => $user->name,
            'email' => $user->email,
            'isActive' => (bool) $user->is_active,
            'canLogin' => (bool) $user->can_login,
            'isSuperAdmin' => $user->isSuperAdmin(),
            'memberships' => (int) $user->teams_count + (int) $user->departments_count,
            'lastLoginAt' => $user->last_login_at?->toIso8601String(),
            'canImpersonate' => $actor->can('impersonate', $user),
            'canToggleActive' => $actor->can('toggle-active', $user),
        ];
    }

    public function toggleActive(ToggleUserActiveRequest $request, User $user): RedirectResponse
    {
        $active = $request->boolean('active');

        $user->update(['is_active' => $active]);

        if (! $active && config('session.driver') === 'database') {
            DB::connection(config('session.connection'))
                ->table(config('session.table', 'sessions'))
                ->where('user_id', $user->getKey())
                ->delete();
        }

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __($active ? 'access.users.activated' : 'access.users.deactivated', ['name' => $user->name]),
        ]);

        return back();
    }
}
