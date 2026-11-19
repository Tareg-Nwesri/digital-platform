<?php

namespace App\Support\Activity;

use Spatie\Activitylog\Models\Activity;

/**
 * The reader-facing label of an activity log entry. Entries are written in
 * English (the stored description stays the audit record); screens show the
 * translation for the entry's event from lang/{locale}/audit.php, and fall
 * back to the stored description for events without one.
 */
final class ActivityLabel
{
    public static function for(Activity $activity): string
    {
        $event = (string) $activity->event;

        if ($event !== '') {
            $key = 'audit.events.'.str_replace('.', '-', $event);
            $label = __($key);

            if ($label !== $key) {
                return (string) $label;
            }

            /* Model events from LogsActivity: "created", "updated", "deleted", "restored". */
            $key = "audit.model.{$event}";

            if ($activity->subject_type !== null && __($key) !== $key) {
                return (string) __($key, ['subject' => self::subject($activity->subject_type)]);
            }
        }

        return (string) $activity->description;
    }

    /**
     * The translated name of a log (area), such as "finance".
     */
    public static function area(?string $log): ?string
    {
        return $log === null ? null : self::translated("audit.logs.{$log}", $log);
    }

    /**
     * The translated record type of a subject class, such as "Payout".
     */
    public static function subject(string $type): string
    {
        $name = class_basename($type);

        return self::translated("audit.subjects.{$name}", $name);
    }

    private static function translated(string $key, string $fallback): string
    {
        $label = __($key);

        return $label === $key ? $fallback : (string) $label;
    }
}
