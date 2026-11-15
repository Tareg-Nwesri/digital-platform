<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import EmptyState from '@/components/public/EmptyState.vue';
import MediaFigure from '@/components/public/MediaFigure.vue';
import PageHero from '@/components/public/PageHero.vue';
import PublicHead from '@/components/public/PublicHead.vue';
import PublicPager from '@/components/public/PublicPager.vue';
import type { PublicMetaTags } from '@/composables/usePublicSite';
import { usePublicSite } from '@/composables/usePublicSite';

defineProps<{
    meta: PublicMetaTags;
    projects: {
        data: {
            slug: string;
            title: string;
            excerpt: string | null;
            team: { slug: string; name: string };
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
        :title="$t('public_site.projects')"
        :lead="$t('public_site.projects_copy')"
    />

    <section class="section">
        <div class="public-shell">
            <div class="card-grid">
                <article
                    v-for="project in projects.data"
                    :key="project.slug"
                    class="entry-card"
                >
                    <MediaFigure />
                    <div class="entry-card__body">
                        <span class="chip chip--brand">{{
                            project.team.name
                        }}</span>
                        <h2>{{ project.title }}</h2>
                        <p v-if="project.excerpt">{{ project.excerpt }}</p>
                        <Link
                            class="text-link stretched-link"
                            :href="url(`/projects/${project.slug}`)"
                            >{{ $t('public_site.view_project') }}</Link
                        >
                    </div>
                </article>
                <EmptyState v-if="projects.data.length === 0" icon="spark">{{
                    $t('public_site.empty')
                }}</EmptyState>
            </div>
            <PublicPager :paginator="projects" />
        </div>
    </section>
</template>
