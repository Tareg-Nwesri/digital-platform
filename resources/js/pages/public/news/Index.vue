<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import EmptyState from '@/components/public/EmptyState.vue';
import PageHero from '@/components/public/PageHero.vue';
import PublicHead from '@/components/public/PublicHead.vue';
import PublicPager from '@/components/public/PublicPager.vue';
import type { PublicMetaTags } from '@/composables/usePublicSite';
import { usePublicSite } from '@/composables/usePublicSite';
import { formatDate } from '@/lib/i18n';

defineProps<{
    meta: PublicMetaTags;
    posts: {
        data: {
            slug: string;
            title: string;
            excerpt: string | null;
            publishedAt: string | null;
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
        :title="$t('public_site.news')"
        :lead="$t('public_site.news_intro')"
    />

    <section class="section">
        <div class="public-shell">
            <div class="card-grid">
                <article
                    v-for="post in posts.data"
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
                        <h2>{{ post.title }}</h2>
                        <p v-if="post.excerpt">{{ post.excerpt }}</p>
                        <Link
                            class="text-link stretched-link"
                            :href="url(`/news/${post.slug}`)"
                            >{{ $t('public_site.read') }}</Link
                        >
                    </div>
                </article>
                <EmptyState v-if="posts.data.length === 0" icon="document">{{
                    $t('public_site.news_empty')
                }}</EmptyState>
            </div>
            <PublicPager :paginator="posts" />
        </div>
    </section>
</template>
