<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import EventStatusBadge from '@/components/events/EventStatusBadge.vue';
import Heading from '@/components/Heading.vue';
import EventFacts from '@/components/public/EventFacts.vue';
import { show } from '@/routes/workspace/events';

defineProps<{
    team: { slug: string; name: string };
    events: {
        slug: string;
        title: string;
        venue: string | null;
        city: string | null;
        timezone: string;
        status: string;
        startsAt: string;
        registrationClosesAt: string;
        isOpen: boolean;
        cover: string | null;
        entries: { id: number; status: string; category: string }[];
    }[];
}>();
</script>

<template>
    <Head :title="$t('Events')" />

    <div class="flex flex-col gap-6 p-4 md:p-6">
        <Heading
            :title="$t('Events')"
            :description="
                $t(
                    'Apply with your members and robots, and follow your entries.',
                )
            "
        />

        <p v-if="events.length === 0" class="text-sm text-muted-foreground">
            {{ $t('No events are open right now.') }}
        </p>

        <ul class="grid gap-4 md:grid-cols-2" data-tour="wevents-list">
            <li v-for="event in events" :key="event.slug">
                <Link
                    :href="show([team.slug, event.slug])"
                    class="grid h-full overflow-hidden rounded-xl border bg-card hover:bg-accent"
                    :data-test="`event-${event.slug}`"
                >
                    <img
                        v-if="event.cover"
                        :src="event.cover"
                        alt=""
                        class="aspect-[40/21] w-full object-cover"
                        loading="lazy"
                    />
                    <span class="grid gap-3 p-4">
                        <span class="flex flex-wrap items-center gap-2">
                            <span class="font-semibold">{{ event.title }}</span>
                            <EventStatusBadge :status="event.status" />
                        </span>
                        <EventFacts :event="event" />
                        <span
                            v-if="event.entries.length"
                            class="flex flex-wrap items-center gap-2 text-sm"
                        >
                            <span
                                v-for="entry in event.entries"
                                :key="entry.id"
                                class="flex items-center gap-1"
                            >
                                {{ entry.category }}
                                <EventStatusBadge
                                    :status="entry.status"
                                    kind="registration"
                                />
                            </span>
                        </span>
                    </span>
                </Link>
            </li>
        </ul>
    </div>
</template>
