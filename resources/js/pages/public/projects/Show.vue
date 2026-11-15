<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import PageHero from '@/components/public/PageHero.vue';
import PublicHead from '@/components/public/PublicHead.vue';
import type { PublicMetaTags } from '@/composables/usePublicSite';
import { usePublicSite } from '@/composables/usePublicSite';

defineProps<{
    meta: PublicMetaTags;
    project: {
        slug: string;
        title: string;
        excerpt: string | null;
        body: string | null;
        team: { slug: string; name: string };
        robots: { name: string; description: string | null }[];
    };
}>();

const { url } = usePublicSite();
</script>

<template>
    <PublicHead :meta="meta" />

    <PageHero
        :back="url('/projects')"
        :back-label="$t('public_site.view_projects')"
        :title="project.title"
        :lead="project.excerpt"
    >
        <template #meta>
            <Link class="chip" :href="url(`/teams/${project.team.slug}`)">{{
                project.team.name
            }}</Link>
        </template>
    </PageHero>

    <section class="section">
        <article class="public-shell prose">
            <div v-if="project.body" v-html="project.body" />
            <template v-if="project.robots.length">
                <h2>{{ $t('public.robots') }}</h2>
                <ul>
                    <li v-for="robot in project.robots" :key="robot.name">
                        <strong>{{ robot.name }}</strong>
                        <template v-if="robot.description">
                            — {{ robot.description }}</template
                        >
                    </li>
                </ul>
            </template>
            <Link class="back-link" :href="url('/projects')">{{
                $t('public_site.view_projects')
            }}</Link>
        </article>
    </section>
</template>
