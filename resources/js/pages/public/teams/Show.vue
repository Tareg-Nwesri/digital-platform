<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import EmptyState from '@/components/public/EmptyState.vue';
import PageHero from '@/components/public/PageHero.vue';
import PublicHead from '@/components/public/PublicHead.vue';
import TeamLogo from '@/components/public/TeamLogo.vue';
import type { PublicMetaTags } from '@/composables/usePublicSite';
import { usePublicSite } from '@/composables/usePublicSite';
import { formatDate, formatNumber } from '@/lib/i18n';

defineProps<{
    meta: PublicMetaTags;
    team: {
        slug: string;
        name: string;
        location: string | null;
        description: string | null;
        logo: string | null;
        focus: string | null;
        officialUrl: string | null;
    };
    projects: { slug: string; title: string; excerpt: string | null }[];
    robots: { name: string; status: string; description: string | null }[];
    results: {
        event: string;
        eventSlug: string;
        category: string;
        placement: number | null;
        award: string | null;
        date: string;
    }[];
}>();

const { url } = usePublicSite();
</script>

<template>
    <PublicHead :meta="meta" />

    <PageHero
        :back="url('/teams')"
        :back-label="$t('public_site.view_teams')"
        :title="team.name"
        :lead="team.description"
    >
        <template #meta>
            <TeamLogo :team="team" size="lg" />
            <span v-if="team.location" class="chip chip--brand">{{
                team.location
            }}</span>
        </template>
        <a
            v-if="team.officialUrl"
            class="button button--dark button--arrow"
            :href="team.officialUrl"
            rel="noopener"
            >{{ $t('public_site.official_website') }}</a
        >
    </PageHero>

    <section class="section">
        <div class="public-shell detail-layout">
            <div class="prose">
                <template v-if="team.focus">
                    <h2>{{ $t('public_site.focus') }}</h2>
                    <p>{{ team.focus }}</p>
                </template>

                <template v-if="projects.length">
                    <h2>{{ $t('public_site.projects') }}</h2>
                    <div class="related-list">
                        <article
                            v-for="project in projects"
                            :key="project.slug"
                            class="related-item"
                        >
                            <h3>{{ project.title }}</h3>
                            <p v-if="project.excerpt">{{ project.excerpt }}</p>
                            <Link
                                class="text-link stretched-link"
                                :href="url(`/projects/${project.slug}`)"
                                >{{ $t('public_site.view_project') }}</Link
                            >
                        </article>
                    </div>
                </template>

                <template v-if="robots.length">
                    <h2>{{ $t('public.robots') }}</h2>
                    <div class="related-list">
                        <article
                            v-for="robot in robots"
                            :key="robot.name"
                            class="related-item"
                        >
                            <h3>{{ robot.name }}</h3>
                            <p v-if="robot.description">
                                {{ robot.description }}
                            </p>
                        </article>
                    </div>
                </template>

                <template v-if="results.length">
                    <h2>{{ $t('public.results') }}</h2>
                    <div class="related-list">
                        <article
                            v-for="result in results"
                            :key="`${result.eventSlug}-${result.category}`"
                            class="related-item"
                        >
                            <h3>
                                <template v-if="result.placement"
                                    >#{{
                                        formatNumber(result.placement)
                                    }}
                                    · </template
                                >{{ result.event }}
                            </h3>
                            <p>
                                {{ result.category }} ·
                                {{ formatDate(result.date) }}
                                <template v-if="result.award">
                                    · {{ result.award }}</template
                                >
                            </p>
                            <Link
                                class="text-link stretched-link"
                                :href="url(`/events/${result.eventSlug}`)"
                                >{{ $t('public_site.view_event') }}</Link
                            >
                        </article>
                    </div>
                </template>

                <EmptyState
                    v-if="
                        !team.focus &&
                        !projects.length &&
                        !robots.length &&
                        !results.length
                    "
                    icon="spark"
                    >{{ $t('public_site.empty') }}</EmptyState
                >
            </div>
            <aside class="detail-aside">
                <div class="card">
                    <TeamLogo :team="team" />
                    <h2>{{ team.name }}</h2>
                    <p v-if="team.location">{{ team.location }}</p>
                    <a
                        v-if="team.officialUrl"
                        class="text-link"
                        :href="team.officialUrl"
                        rel="noopener"
                        >{{ $t('public_site.official_website') }}</a
                    >
                    <Link
                        class="button button--ghost button--block"
                        :href="url('/contact')"
                        >{{ $t('public_site.contact') }}</Link
                    >
                </div>
            </aside>
        </div>
    </section>
</template>
