<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import EventDocuments from '@/components/public/EventDocuments.vue';
import MediaFigure from '@/components/public/MediaFigure.vue';
import PageHero from '@/components/public/PageHero.vue';
import PublicHead from '@/components/public/PublicHead.vue';
import type { PublicMetaTags } from '@/composables/usePublicSite';
import { usePublicSite } from '@/composables/usePublicSite';
import { formatDate, formatNumber } from '@/lib/i18n';

export type PublicEvent = {
    slug: string;
    title: string;
    venue: string | null;
    city: string | null;
    timezone: string;
    startsAt: string;
    endsAt: string;
    registrationOpensAt: string | null;
    registrationClosesAt: string;
    isOpen: boolean;
    cover: string | null;
    documents: {
        id: number;
        name: string;
        extension: string;
        size: number;
        url: string;
    }[];
    description: string | null;
    rules: string | null;
    terms: string | null;
    categories: {
        id: number;
        name: string;
        ageMin: number | null;
        ageMax: number | null;
        membersMin: number;
        membersMax: number;
        robotsPerTeam: number;
    }[];
};

const props = defineProps<{
    meta: PublicMetaTags;
    event: PublicEvent & {
        when: 'upcoming' | 'current' | 'past';
        teams: { name: string; slug: string | null; category: string }[];
        results: {
            team: string;
            category: string;
            placement: number | null;
            score: string | null;
            award: string | null;
        }[];
    };
}>();

const { url } = usePublicSite();

const chip: Record<string, string> = {
    past: 'chip chip--muted',
    upcoming: 'chip chip--brand',
    current: 'chip chip--live',
};

const dates = computed(() => {
    const start = formatDate(props.event.startsAt, {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
        timeZone: props.event.timezone,
    });
    const end = formatDate(props.event.endsAt, {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
        timeZone: props.event.timezone,
    });

    return start === end ? start : `${start} – ${end}`;
});

const registerUrl = computed(() => url(`/events/${props.event.slug}/register`));
</script>

<template>
    <PublicHead :meta="meta" />

    <PageHero
        :back="url('/events')"
        :back-label="$t('public_site.all_events')"
        :title="event.title"
    >
        <template #meta>
            <span :class="chip[event.when]">{{
                $t(`public_site.${event.when}_event`)
            }}</span>
            <time class="chip" :datetime="event.startsAt">{{ dates }}</time>
        </template>
        <Link
            v-if="event.isOpen"
            class="button button--arrow"
            :href="registerUrl"
            data-test="register-link"
            >{{ $t('public.register_team') }}</Link
        >
    </PageHero>

    <section class="section">
        <div class="public-shell">
            <div v-if="event.cover" class="detail-banner">
                <MediaFigure
                    ratio="wide"
                    :src="event.cover"
                    :alt="event.title"
                    eager
                />
            </div>
            <div class="detail-layout">
                <article class="prose">
                    <template v-if="event.description">
                        <h2>{{ $t('public_site.event_details') }}</h2>
                        <div v-html="event.description" />
                    </template>

                    <template v-if="event.categories.length">
                        <h2>{{ $t('public.categories') }}</h2>
                        <ul>
                            <li
                                v-for="category in event.categories"
                                :key="category.id"
                            >
                                <strong>{{ category.name }}</strong>
                                <template
                                    v-if="
                                        category.ageMin !== null ||
                                        category.ageMax !== null
                                    "
                                >
                                    ·
                                    {{
                                        $t('Ages :min–:max', {
                                            min: String(category.ageMin ?? 0),
                                            max: String(category.ageMax ?? 99),
                                        })
                                    }}
                                </template>
                                ·
                                {{
                                    $t(':min–:max members per team', {
                                        min: String(category.membersMin),
                                        max: String(category.membersMax),
                                    })
                                }}
                            </li>
                        </ul>
                    </template>

                    <template v-if="event.rules">
                        <h2>{{ $t('Rules') }}</h2>
                        <div v-html="event.rules" />
                    </template>

                    <template v-if="event.results.length">
                        <h2>{{ $t('public.results') }}</h2>
                        <table class="results-table" data-test="results">
                            <thead>
                                <tr>
                                    <th scope="col">
                                        {{ $t('public.placement') }}
                                    </th>
                                    <th scope="col">{{ $t('Team') }}</th>
                                    <th scope="col">{{ $t('Category') }}</th>
                                    <th scope="col">
                                        {{ $t('public.award') }}
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr
                                    v-for="(result, index) in event.results"
                                    :key="index"
                                >
                                    <td>
                                        {{
                                            result.placement
                                                ? formatNumber(result.placement)
                                                : '—'
                                        }}
                                    </td>
                                    <td>{{ result.team }}</td>
                                    <td>{{ result.category }}</td>
                                    <td>
                                        {{ result.award ?? result.score ?? '' }}
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </template>

                    <template v-if="event.teams.length">
                        <h2>{{ $t('public.teams_entered') }}</h2>
                        <ul data-test="event-teams">
                            <li
                                v-for="team in event.teams"
                                :key="`${team.name}-${team.category}`"
                            >
                                <Link
                                    v-if="team.slug"
                                    :href="url(`/teams/${team.slug}`)"
                                    >{{ team.name }}</Link
                                >
                                <template v-else>{{ team.name }}</template>
                                · {{ team.category }}
                            </li>
                        </ul>
                    </template>

                    <template v-if="event.terms">
                        <h2>{{ $t('public_site.event_terms') }}</h2>
                        <div v-html="event.terms" />
                    </template>
                </article>

                <aside
                    class="detail-aside"
                    aria-labelledby="registration-heading"
                >
                    <div class="card">
                        <h2 id="registration-heading">
                            {{ $t('public_site.team_registration') }}
                        </h2>
                        <p>
                            {{
                                event.isOpen
                                    ? $t('public_site.registration_copy')
                                    : $t('public.registration_closed')
                            }}
                        </p>
                        <dl class="fact-list">
                            <div>
                                <dt>{{ $t('public_site.event_dates') }}</dt>
                                <dd>{{ dates }}</dd>
                            </div>
                            <div v-if="event.venue || event.city">
                                <dt>{{ $t('City') }}</dt>
                                <dd>
                                    {{
                                        [event.venue, event.city]
                                            .filter(Boolean)
                                            .join(' · ')
                                    }}
                                </dd>
                            </div>
                            <div v-if="event.isOpen">
                                <dt>
                                    {{ $t('public.registration_deadline') }}
                                </dt>
                                <dd>
                                    {{
                                        formatDate(event.registrationClosesAt, {
                                            dateStyle: 'medium',
                                            timeStyle: 'short',
                                            timeZone: event.timezone,
                                        })
                                    }}
                                </dd>
                            </div>
                        </dl>
                        <Link
                            v-if="event.isOpen"
                            class="button button--arrow button--block"
                            :href="registerUrl"
                            >{{ $t('public.register_team') }}</Link
                        >
                    </div>
                    <EventDocuments :documents="event.documents" />
                </aside>
            </div>
        </div>
    </section>
</template>
