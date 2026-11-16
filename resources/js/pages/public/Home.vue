<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { onMounted, ref } from 'vue';
import EmptyState from '@/components/public/EmptyState.vue';
import MediaFigure from '@/components/public/MediaFigure.vue';
import PublicHead from '@/components/public/PublicHead.vue';
import PublicIcon from '@/components/public/PublicIcon.vue';
import TeamLogo from '@/components/public/TeamLogo.vue';
import type { PublicMetaTags } from '@/composables/usePublicSite';
import { usePublicSite } from '@/composables/usePublicSite';
import { formatDate } from '@/lib/i18n';
import { initPublicTimeline } from '@/lib/publicTimeline';

type TeamCard = {
    slug: string;
    name: string;
    location: string | null;
    description: string | null;
    logo: string | null;
};

defineProps<{
    meta: PublicMetaTags;
    teams: TeamCard[];
    projects: {
        slug: string;
        title: string;
        excerpt: string | null;
        team: { slug: string; name: string };
    }[];
    events: {
        slug: string;
        title: string;
        excerpt: string;
        startsAt: string;
        timezone: string;
        cover: string | null;
    }[];
    sponsors: { name: string; logo: string; website: string | null }[];
    news: {
        slug: string;
        title: string;
        excerpt: string | null;
        publishedAt: string | null;
    }[];
}>();

const { url } = usePublicSite();
const timeline = ref<HTMLElement | null>(null);

const steps = [
    [
        '01',
        'learning',
        'learning_copy',
        'general/six-wheel-rover-exploded.webp',
        'home_learning_visual',
    ],
    [
        '02',
        'teams',
        'teams_copy',
        'fight/team-workshop.webp',
        'home_teams_visual',
    ],
    [
        '03',
        'projects',
        'projects_copy',
        'general/robotics-systems-showcase.webp',
        'home_projects_visual',
    ],
    [
        '04',
        'events',
        'events_copy',
        'fight/arena-systems-showcase.webp',
        'home_events_visual',
    ],
] as const;

const image = (path: string) => `/assets/img/robots/${path}`;

onMounted(() => initPublicTimeline(timeline.value));
</script>

<template>
    <PublicHead :meta="meta" />

    <section class="hero">
        <div class="public-shell hero-layout">
            <div class="hero-intro">
                <p class="eyebrow">{{ $t('public_site.brand') }}</p>
                <h1>
                    <span>{{ $t('public_site.hero_lead') }}</span>
                    {{ ' ' }}
                    <span>{{ $t('public_site.hero_end') }}</span>
                </h1>
                <p class="hero-copy">
                    {{ $t('public_site.home_description') }}
                </p>
                <div class="actions">
                    <Link class="button button--arrow" :href="url('/teams')">{{
                        $t('public_site.view_teams')
                    }}</Link>
                    <Link class="button button--ghost" :href="url('/about')">{{
                        $t('public_site.learn_more')
                    }}</Link>
                </div>
            </div>
            <div class="hero-media">
                <MediaFigure
                    :src="image('fight/arena-battle.webp')"
                    :alt="$t('public_site.home_hero_visual')"
                    eager
                />
                <div class="hero-badge">
                    <img
                        src="/assets/img/brand/logo.png"
                        width="44"
                        height="44"
                        alt=""
                    />
                    <div>
                        <strong>{{ $t('public_site.country') }}</strong>
                        <span>{{ $t('public_site.media_note') }}</span>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="section section--tight">
        <div class="public-shell intro">
            <h2>{{ $t('public_site.intro') }}</h2>
            <div class="intro-copy">
                <p>{{ $t('public_site.intro_description') }}</p>
                <Link class="text-link" :href="url('/about')">{{
                    $t('public_site.about')
                }}</Link>
            </div>
        </div>
    </section>

    <section
        id="timeline"
        ref="timeline"
        class="section section--surface"
        aria-labelledby="timeline-title"
    >
        <div class="public-shell">
            <div class="section-head">
                <div>
                    <header class="section-heading">
                        <h2 id="timeline-title">
                            {{ $t('public_site.activities_heading') }}
                        </h2>
                    </header>
                </div>
            </div>
            <ol class="timeline-steps">
                <li
                    v-for="[number, title, copy, img, alt] in steps"
                    :key="number"
                    class="timeline-step"
                >
                    <div class="timeline-step__body">
                        <span
                            class="timeline-step__number"
                            aria-hidden="true"
                            >{{ number }}</span
                        >
                        <h3>{{ $t(`public_site.${title}`) }}</h3>
                        <p>{{ $t(`public_site.${copy}`) }}</p>
                    </div>
                    <MediaFigure
                        class="timeline-step__visual"
                        :src="image(img)"
                        :alt="$t(`public_site.${alt}`)"
                    />
                </li>
            </ol>
        </div>
    </section>

    <section class="section" aria-labelledby="teams-title">
        <div class="public-shell">
            <div class="section-head">
                <header class="section-heading">
                    <p class="eyebrow">{{ $t('public_site.teams') }}</p>
                    <h2 id="teams-title">
                        {{ $t('public_site.teams_heading') }}
                    </h2>
                </header>
                <Link class="text-link" :href="url('/teams')">{{
                    $t('public_site.view_teams')
                }}</Link>
            </div>
            <div class="team-feature">
                <MediaFigure
                    :src="image('general/student-robotics-team.webp')"
                    :alt="$t('public_site.home_team_visual')"
                />
                <div class="team-feature__card">
                    <template v-if="teams[0]">
                        <div class="meta-row">
                            <TeamLogo :team="teams[0]" />
                            <span
                                v-if="teams[0].location"
                                class="chip chip--brand"
                                >{{ teams[0].location }}</span
                            >
                        </div>
                        <h3>{{ teams[0].name }}</h3>
                        <p v-if="teams[0].description">
                            {{ teams[0].description }}
                        </p>
                        <Link
                            class="button button--dark button--arrow"
                            :href="url(`/teams/${teams[0].slug}`)"
                            >{{ $t('public_site.view_team') }}</Link
                        >
                    </template>
                    <template v-else>
                        <span class="icon-tile" aria-hidden="true"
                            ><PublicIcon name="people" :size="26"
                        /></span>
                        <h3>{{ $t('public_site.teams') }}</h3>
                        <p>{{ $t('public_site.teams_empty') }}</p>
                        <Link
                            class="button button--dark button--arrow"
                            :href="url('/contact')"
                            >{{ $t('public_site.contact') }}</Link
                        >
                    </template>
                </div>
            </div>
            <div v-if="teams.length > 1" class="team-mini-grid">
                <article
                    v-for="team in teams.slice(1)"
                    :key="team.slug"
                    class="team-mini"
                >
                    <TeamLogo :team="team" />
                    <div>
                        <h3>{{ team.name }}</h3>
                        <p v-if="team.location">{{ team.location }}</p>
                    </div>
                    <Link
                        class="text-link stretched-link"
                        :href="url(`/teams/${team.slug}`)"
                        >{{ $t('public_site.view_team') }}</Link
                    >
                </article>
            </div>
        </div>
    </section>

    <section class="section section--flush-top" aria-labelledby="project-title">
        <div class="public-shell story-band">
            <MediaFigure
                :src="image('general/rover-obstacle-course.webp')"
                :alt="$t('public_site.home_project_visual')"
            />
            <div class="story-card">
                <p class="eyebrow">
                    {{ projects[0]?.team.name ?? $t('public_site.projects') }}
                </p>
                <h2 id="project-title">
                    {{
                        projects[0]?.title ?? $t('public_site.projects_heading')
                    }}
                </h2>
                <p>
                    {{
                        projects[0]?.excerpt ?? $t('public_site.projects_copy')
                    }}
                </p>
                <Link
                    class="text-link"
                    :href="
                        projects[0]
                            ? url(`/projects/${projects[0].slug}`)
                            : url('/projects')
                    "
                    >{{
                        projects[0]
                            ? $t('public_site.view_project')
                            : $t('public_site.view_projects')
                    }}</Link
                >
            </div>
        </div>
    </section>

    <section class="section section--surface">
        <div class="public-shell split">
            <MediaFigure
                ratio="portrait"
                :src="image('general/robot-workshop-collaboration.webp')"
                :alt="$t('public_site.home_story_visual')"
            />
            <div>
                <h2>{{ $t('public_site.story_heading') }}</h2>
                <p>{{ $t('public_site.story_copy') }}</p>
                <Link
                    class="button button--ghost button--arrow"
                    :href="url('/about')"
                    >{{ $t('public_site.learn_more') }}</Link
                >
            </div>
        </div>
    </section>

    <section class="section section--surface" aria-labelledby="events-title">
        <div class="public-shell">
            <div class="section-head">
                <header class="section-heading">
                    <h2 id="events-title">{{ $t('public_site.events') }}</h2>
                </header>
                <Link class="text-link" :href="url('/events')">{{
                    $t('public_site.all_events')
                }}</Link>
            </div>
            <div class="card-grid">
                <article
                    v-for="event in events"
                    :key="event.slug"
                    class="entry-card"
                >
                    <MediaFigure :src="event.cover" :alt="event.title" />
                    <time class="date-block" :datetime="event.startsAt"
                        ><b>{{
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
                        }}</span></time
                    >
                    <div class="entry-card__body">
                        <h3>{{ event.title }}</h3>
                        <p v-if="event.excerpt">{{ event.excerpt }}</p>
                        <Link
                            class="text-link stretched-link"
                            :href="url(`/events/${event.slug}`)"
                            >{{ $t('public_site.view_event') }}</Link
                        >
                    </div>
                </article>
                <EmptyState v-if="events.length === 0" icon="calendar">{{
                    $t('public_site.events_empty')
                }}</EmptyState>
            </div>
        </div>
    </section>

    <section
        v-if="sponsors.length"
        class="section section--surface"
        aria-labelledby="sponsors-title"
    >
        <div class="public-shell network">
            <div>
                <h2 id="sponsors-title">
                    {{ $t('public_site.network_heading') }}
                </h2>
                <p>{{ $t('public.sponsors_copy') }}</p>
                <Link class="text-link" :href="url('/contact')">{{
                    $t('public_site.contact')
                }}</Link>
            </div>
            <ul class="logo-band" :aria-label="$t('public.sponsors')">
                <li
                    v-for="sponsor in sponsors"
                    :key="sponsor.name"
                    class="sponsor-logo"
                >
                    <a
                        v-if="sponsor.website"
                        :href="sponsor.website"
                        target="_blank"
                        rel="noopener noreferrer"
                        :title="sponsor.name"
                    >
                        <img
                            :src="sponsor.logo"
                            :alt="sponsor.name"
                            loading="lazy"
                            decoding="async"
                        />
                    </a>
                    <img
                        v-else
                        :src="sponsor.logo"
                        :alt="sponsor.name"
                        loading="lazy"
                        decoding="async"
                    />
                </li>
            </ul>
        </div>
    </section>

    <section class="section" aria-labelledby="news-title">
        <div class="public-shell">
            <div class="section-head">
                <header class="section-heading">
                    <h2 id="news-title">{{ $t('public_site.news') }}</h2>
                </header>
                <Link class="text-link" :href="url('/news')">{{
                    $t('public_site.all_news')
                }}</Link>
            </div>
            <div class="card-grid">
                <article
                    v-for="post in news"
                    :key="post.slug"
                    class="entry-card"
                >
                    <div class="entry-card__body">
                        <time
                            class="chip chip--brand"
                            :datetime="post.publishedAt ?? undefined"
                            >{{
                                formatDate(post.publishedAt, {
                                    day: '2-digit',
                                    month: 'short',
                                    year: 'numeric',
                                })
                            }}</time
                        >
                        <h3>{{ post.title }}</h3>
                        <p v-if="post.excerpt">{{ post.excerpt }}</p>
                        <Link
                            class="text-link stretched-link"
                            :href="url(`/news/${post.slug}`)"
                            >{{ $t('public_site.read') }}</Link
                        >
                    </div>
                </article>
                <EmptyState v-if="news.length === 0" icon="document">{{
                    $t('public_site.news_empty')
                }}</EmptyState>
            </div>
        </div>
    </section>

    <section class="section section--flush-top">
        <div class="public-shell">
            <div class="cta-panel">
                <h2>{{ $t('public_site.closing_heading') }}</h2>
                <div>
                    <p>{{ $t('public_site.closing_copy') }}</p>
                    <div class="actions">
                        <Link
                            class="button button--arrow"
                            :href="url('/get-involved')"
                            >{{ $t('public_site.get_involved') }}</Link
                        >
                        <Link
                            class="button button--on-dark"
                            :href="url('/contact')"
                            >{{ $t('public_site.contact') }}</Link
                        >
                    </div>
                </div>
            </div>
        </div>
    </section>
</template>
