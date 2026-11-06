<?php

namespace App\Http\Middleware;

use App\Domain\Access\Models\Workspace;
use App\Domain\Access\Services\WorkspaceContext;
use App\Domain\Organisation\Models\Department;
use App\Domain\Teams\Models\Team;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resolves the team or department from the URL, checks membership and scopes
 * permissions to its workspace (LLD 11 §2). The active workspace is never
 * stored in the session.
 *
 * Usage: ->middleware('workspace:team') or ->middleware('workspace:department').
 */
class ResolveWorkspace
{
    public function __construct(private readonly WorkspaceContext $context) {}

    public function handle(Request $request, Closure $next, string $type): Response
    {
        $user = $request->user();

        if ($type === 'team') {
            $team = $request->route('team');
            abort_unless($team instanceof Team, 404);

            if (! $user?->isSuperAdmin() && ! $team->hasMember($user)) {
                abort(404);
            }

            $this->context->set($team->workspace, $team);
        } elseif ($type === 'department') {
            $department = $request->route('department');
            abort_unless($department instanceof Department, 404);

            if (! $user?->isSuperAdmin() && ! $department->hasMember($user)) {
                abort(403);
            }

            $this->context->set($department->workspace, $department);
        } else {
            $this->context->set(Workspace::platform(), Workspace::platform());
        }

        return $next($request);
    }
}
