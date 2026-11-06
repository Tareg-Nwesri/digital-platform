<?php

namespace App\Http\Middleware;

use App\Domain\Access\Services\WorkspaceContext;
use App\Domain\Teams\Models\Team;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Suspended, pending or archived teams are read-only in their workspace (SRS FR-TEAM-02).
 */
class EnsureTeamActive
{
    public function __construct(private readonly WorkspaceContext $context) {}

    public function handle(Request $request, Closure $next): Response
    {
        $team = $this->context->owner();

        if ($team instanceof Team && ! $team->isActive() && ! $request->isMethodSafe()) {
            abort(403, __('workspace.team_read_only'));
        }

        return $next($request);
    }
}
