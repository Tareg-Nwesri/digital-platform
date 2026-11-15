<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import EmptyState from '@/components/public/EmptyState.vue';
import PageHero from '@/components/public/PageHero.vue';
import PublicHead from '@/components/public/PublicHead.vue';
import type { PublicMetaTags } from '@/composables/usePublicSite';
import { usePublicSite } from '@/composables/usePublicSite';
import { formatDate } from '@/lib/i18n';

defineProps<{
    meta: PublicMetaTags;
    events: {
        slug: string;
        title: string;
        excerpt: string;
        timezone: string;
        startsAt: string;
        endsAt: string;
        isOpen: boolean;
        when: 'upcoming' | 'current' | 'past';
    }[];
}>();

const { url } = usePublicSite();

const chip: Record<string, string> = {
    past: 'chip chip--muted',
    upcoming: 'chip chip--brand',
    current: 'chip chip--live',
};
</script>

<template>
    <PublicHead :meta="meta" />

    <PageHero
        :eyebrow="$t('public_site.brand')"
        :title="$t('public_site.events')"
        :lead="$t('public_site.events_intro')"
    />

    <section class="section">
        <div class="public-shell">
            <div class="event-list">
                <article
                    v-for="event in events"
                    :key="event.slug"
                    class="event-row"
                    :data-test="`event-${event.slug}`"
                >
                    <div class="date-block" aria-hidden="true">
                        <b>{{
                            formatDate(event.startsAt, {
                                day: '2-digit',
                                timeZone: event.timezone,
                            })
                        }}</b>
                        <span>{{
                            formatDate(event.startsAt, {
                                month: 'short',
                                year: 'numeric',
                                timeZone: event.timezone,
                            })
                        }}</span>
                    </div>
                    <div>
                        <div class="meta-row">
                            <span :class="chip[event.when]">{{
                                $t(`public_site.${event.when}_event`)
                            }}</span>
                            <span
                                v-if="event.isOpen"
                                class="chip chip--brand"
                                >{{ $t('Registration open') }}</span
                            >
                            <time
                                class="event-row__range"
                                :datetime="event.startsAt"
                                >{{
                                    formatDate(event.startsAt, {
                                        day: '2-digit',
                                        month: 'short',
                                        year: 'numeric',
                                        timeZone: event.timezone,
                                    })
                                }}</time
                            >
                        </div>
                        <h2>{{ event.title }}</h2>
                        <p v-if="event.excerpt">{{ event.excerpt }}</p>
                    </div>
                    <Link
                        class="text-link stretched-link"
                        :href="url(`/events/${event.slug}`)"
                        >{{ $t('public_site.view_event') }}</Link
                    >
                </article>
                <EmptyState v-if="events.length === 0" icon="calendar">{{
                    $t('public_site.events_empty')
                }}</EmptyState>
            </div>
        </div>
    </section>
</template>
