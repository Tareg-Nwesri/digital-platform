<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import EmptyState from '@/components/public/EmptyState.vue';
import PageHero from '@/components/public/PageHero.vue';
import PublicHead from '@/components/public/PublicHead.vue';
import PublicPager from '@/components/public/PublicPager.vue';
import TeamLogo from '@/components/public/TeamLogo.vue';
import type { PublicMetaTags } from '@/composables/usePublicSite';
import { usePublicSite } from '@/composables/usePublicSite';

defineProps<{
    meta: PublicMetaTags;
    teams: {
        data: {
            slug: string;
            name: string;
            location: string | null;
            description: string | null;
            logo: string | null;
        }[];
        current_page: number;
        last_page: number;
        prev_page_url: string | null;
        next_page_url: string | null;
    };
}>();

const { url } = usePublicSite();
</script>

<template>
    <PublicHead :meta="meta" />

    <PageHero
        :eyebrow="$t('public_site.brand')"
        :title="$t('public_site.teams')"
        :lead="$t('public_site.join_team_copy')"
    />

    <section class="section">
        <div class="public-shell">
            <div class="card-grid">
                <article
                    v-for="team in teams.data"
                    :key="team.slug"
                    class="team-card"
                >
                    <div class="team-card__top">
                        <TeamLogo :team="team" />
                        <span v-if="team.location" class="chip">{{
                            team.location
                        }}</span>
                    </div>
                    <h2>{{ team.name }}</h2>
                    <p v-if="team.description">{{ team.description }}</p>
                    <Link
                        class="text-link stretched-link"
                        :href="url(`/teams/${team.slug}`)"
                        >{{ $t('public_site.view_team') }}</Link
                    >
                </article>
                <EmptyState v-if="teams.data.length === 0" icon="people">
                    {{ $t('public_site.teams_empty') }}
                    <template #action>
                        <Link
                            class="button button--ghost"
                            :href="url('/contact')"
                            >{{ $t('public_site.contact') }}</Link
                        >
                    </template>
                </EmptyState>
            </div>
            <PublicPager :paginator="teams" />
        </div>
    </section>
</template>
