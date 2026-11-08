<?php

namespace App\Models;

use App\Domain\Access\Services\WorkspaceContext;
use Lab404\Impersonate\Services\ImpersonateManager;
use Spatie\Activitylog\Models\Activity as SpatieActivity;

/**
 * Activity entries stamped with their context (LLD 08 §3): the current
 * workspace, and the real person behind an impersonated session.
 *
 * @property int|null $workspace_id
 * @property int|null $impersonator_id
 */
class Activity extends SpatieActivity
{
    protected static function booted(): void
    {
        static::creating(function (Activity $activity): void {
            $activity->workspace_id ??= app(WorkspaceContext::class)->workspace()?->getKey();

            if ($activity->impersonator_id === null && app()->bound('session')) {
                $manager = app(ImpersonateManager::class);
                $activity->impersonator_id = $manager->isImpersonating() ? $manager->getImpersonatorId() : null;
            }
        });
    }
}
