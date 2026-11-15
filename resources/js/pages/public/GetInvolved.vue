<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import PageHero from '@/components/public/PageHero.vue';
import PublicHead from '@/components/public/PublicHead.vue';
import type { PublicMetaTags } from '@/composables/usePublicSite';
import { usePublicSite } from '@/composables/usePublicSite';

defineProps<{ meta: PublicMetaTags }>();

const { url } = usePublicSite();

const paths = [
    ['join_team', 'join_team_copy', 'teams', '/teams'],
    ['start_project', 'start_project_copy', 'projects', '/projects'],
    ['contact', 'contact_copy', 'contact', '/contact'],
] as const;
</script>

<template>
    <PublicHead :meta="meta" />

    <PageHero
        :eyebrow="$t('public_site.brand')"
        :title="$t('public_site.get_involved')"
        :lead="$t('public_site.get_involved_copy')"
    />

    <section class="section" aria-labelledby="paths-heading">
        <div class="public-shell">
            <h2 id="paths-heading" class="sr-only">
                {{ $t('public_site.paths_heading') }}
            </h2>
            <div class="path-grid">
                <article
                    v-for="([title, copy, link, path], index) in paths"
                    :key="title"
                    class="path-card"
                >
                    <span class="path-card__number" aria-hidden="true">{{
                        String(index + 1).padStart(2, '0')
                    }}</span>
                    <h3>{{ $t(`public_site.${title}`) }}</h3>
                    <p>{{ $t(`public_site.${copy}`) }}</p>
                    <Link class="text-link stretched-link" :href="url(path)">{{
                        $t(`public_site.${link}`)
                    }}</Link>
                </article>
            </div>
        </div>
    </section>
</template>
