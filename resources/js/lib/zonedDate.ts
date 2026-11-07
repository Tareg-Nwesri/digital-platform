import { currentLocale } from '@/lib/i18n';

/**
 * Event dates are stored in UTC but entered and shown as wall-clock times in
 * the event's own timezone (Africa/Tripoli by default).
 */
export function toZonedInput(
    iso: string | null | undefined,
    timeZone: string,
): string {
    if (!iso) {
        return '';
    }

    const parts = Object.fromEntries(
        new Intl.DateTimeFormat('en-CA', {
            timeZone,
            year: 'numeric',
            month: '2-digit',
            day: '2-digit',
            hour: '2-digit',
            minute: '2-digit',
            hourCycle: 'h23',
        })
            .formatToParts(new Date(iso))
            .map((part) => [part.type, part.value]),
    );

    return `${parts.year}-${parts.month}-${parts.day}T${parts.hour}:${parts.minute}`;
}

export function formatZoned(
    iso: string | null | undefined,
    timeZone: string,
    withTime = true,
): string {
    if (!iso) {
        return '—';
    }

    return new Intl.DateTimeFormat(currentLocale(), {
        timeZone,
        day: 'numeric',
        month: 'short',
        year: 'numeric',
        ...(withTime ? { hour: '2-digit', minute: '2-digit' } : {}),
        numberingSystem: 'latn',
    }).format(new Date(iso));
}
