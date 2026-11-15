<script setup lang="ts">
import { CalendarDays, Clock, MapPin } from '@lucide/vue';
import { formatDate } from '@/lib/i18n';

defineProps<{
    event: {
        venue: string | null;
        city: string | null;
        timezone: string;
        startsAt: string;
        registrationClosesAt: string;
        isOpen: boolean;
    };
}>();
</script>

<template>
    <ul class="grid gap-2 text-sm text-muted-foreground">
        <li class="flex items-center gap-2">
            <CalendarDays class="size-4 shrink-0" />
            {{
                formatDate(event.startsAt, {
                    dateStyle: 'full',
                    timeZone: event.timezone,
                })
            }}
        </li>
        <li v-if="event.venue || event.city" class="flex items-center gap-2">
            <MapPin class="size-4 shrink-0" />
            {{ [event.venue, event.city].filter(Boolean).join(' · ') }}
        </li>
        <li class="flex items-center gap-2">
            <Clock class="size-4 shrink-0" />
            <template v-if="event.isOpen">{{
                $t('Registration closes :date', {
                    date: formatDate(event.registrationClosesAt, {
                        dateStyle: 'medium',
                        timeStyle: 'short',
                        timeZone: event.timezone,
                    }),
                })
            }}</template>
            <template v-else>{{ $t('Registration is closed') }}</template>
        </li>
    </ul>
</template>
