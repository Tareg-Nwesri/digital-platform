<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import EventFacts from '@/components/public/EventFacts.vue';
import { Badge } from '@/components/ui/badge';

defineProps<{
    events: {
        slug: string;
        title: string;
        venue: string | null;
        city: string | null;
        timezone: string;
        startsAt: string;
        registrationClosesAt: string;
        isOpen: boolean;
    }[];
}>();
</script>

<template>
    <Head :title="$t('Events')" />

    <div class="grid gap-6">
        <h1 class="text-2xl font-bold">{{ $t('Events') }}</h1>

        <p v-if="events.length === 0" class="text-muted-foreground">
            {{ $t('No events are open right now.') }}
        </p>

        <ul class="grid gap-4">
            <li v-for="event in events" :key="event.slug">
                <Link
                    :href="`/${$page.props.locale}/events/${event.slug}`"
                    class="grid gap-3 rounded-xl border bg-card p-5 hover:bg-accent"
                >
                    <span class="flex flex-wrap items-center gap-2">
                        <span class="text-lg font-semibold">{{
                            event.title
                        }}</span>
                        <Badge v-if="event.isOpen">{{
                            $t('Registration open')
                        }}</Badge>
                    </span>
                    <EventFacts :event="event" />
                </Link>
            </li>
        </ul>
    </div>
</template>
