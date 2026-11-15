<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import PageHero from '@/components/public/PageHero.vue';
import PublicHead from '@/components/public/PublicHead.vue';
import type { PublicMetaTags } from '@/composables/usePublicSite';
import { usePublicSite } from '@/composables/usePublicSite';
import { formatDate } from '@/lib/i18n';

defineProps<{
    meta: PublicMetaTags;
    post: {
        slug: string;
        title: string;
        excerpt: string | null;
        body: string | null;
        publishedAt: string | null;
    };
}>();

const { url } = usePublicSite();
</script>

<template>
    <PublicHead :meta="meta" />

    <PageHero
        :back="url('/news')"
        :back-label="$t('public_site.all_news')"
        :title="post.title"
        :lead="post.excerpt"
    >
        <template #meta>
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
        </template>
    </PageHero>

    <section class="section">
        <article class="public-shell prose">
            <div v-if="post.body" v-html="post.body" />
            <Link class="back-link" :href="url('/news')">{{
                $t('public_site.all_news')
            }}</Link>
        </article>
    </section>
</template>
