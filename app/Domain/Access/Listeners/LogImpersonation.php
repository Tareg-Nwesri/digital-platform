<?php

namespace App\Domain\Access\Listeners;

use Lab404\Impersonate\Events\LeaveImpersonation;
use Lab404\Impersonate\Events\TakeImpersonation;

/**
 * Audit trail for impersonation (SRS FR-ACC-15): who, whom, from where, and for how long.
 */
class LogImpersonation
{
    private const STARTED_AT = 'impersonation.started_at';

    public function handle(TakeImpersonation|LeaveImpersonation $event): void
    {
        $properties = [
            'impersonator_id' => $event->impersonator->getAuthIdentifier(),
            'impersonated_id' => $event->impersonated->getAuthIdentifier(),
            'ip' => request()->ip(),
        ];

        if ($event instanceof TakeImpersonation) {
            session()->put(self::STARTED_AT, now()->toIso8601String());
        } elseif (($startedAt = session()->pull(self::STARTED_AT)) !== null) {
            $properties['started_at'] = $startedAt;
            $properties['duration_seconds'] = (int) abs(now()->diffInSeconds($startedAt));
        }

        $started = $event instanceof TakeImpersonation;

        activity('access')
            ->causedBy($event->impersonator)
            ->performedOn($event->impersonated)
            ->event($started ? 'impersonation.started' : 'impersonation.ended')
            ->withProperties($properties)
            ->log($started ? 'Impersonation started' : 'Impersonation ended');
    }
}
