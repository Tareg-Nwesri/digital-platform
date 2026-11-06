<?php

namespace App\Domain\Access\Services;

use App\Domain\Access\Models\Workspace;
use App\Exceptions\WorkspaceMismatch;
use Illuminate\Database\Eloquent\Model;
use Spatie\Permission\PermissionRegistrar;

/**
 * Holds the workspace (team or department) resolved from the current URL.
 * Bound as a scoped singleton: one per request or queued job.
 */
final class WorkspaceContext
{
    private ?Workspace $workspace = null;

    private ?Model $owner = null;

    public function __construct(private readonly PermissionRegistrar $permissions) {}

    public function set(Workspace $workspace, Model $owner): void
    {
        $this->workspace = $workspace;
        $this->owner = $owner;
        $this->permissions->setPermissionsTeamId($workspace->getKey());
    }

    public function clear(): void
    {
        $this->workspace = null;
        $this->owner = null;
        $this->permissions->setPermissionsTeamId(null);
    }

    public function workspace(): ?Workspace
    {
        return $this->workspace;
    }

    public function owner(): ?Model
    {
        return $this->owner;
    }

    public function require(): Workspace
    {
        return $this->workspace ?? throw WorkspaceMismatch::make();
    }

    /**
     * Run a callback inside another workspace scope (or the global scope with null), then restore.
     *
     * @template TReturn
     *
     * @param  callable(): TReturn  $callback
     * @return TReturn
     */
    public function within(?Workspace $workspace, callable $callback): mixed
    {
        $previous = $this->permissions->getPermissionsTeamId();
        $this->permissions->setPermissionsTeamId($workspace?->getKey());

        try {
            return $callback();
        } finally {
            $this->permissions->setPermissionsTeamId($previous);
        }
    }
}
