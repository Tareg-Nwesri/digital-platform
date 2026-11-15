<script setup lang="ts">
import { Head, usePage } from '@inertiajs/vue3';
import { computed, onMounted } from 'vue';
import type { PublicMetaTags } from '@/composables/usePublicSite';

/**
 * Meta tags for public pages (LLD 09 §4). The server prints them in the
 * first HTML response for crawlers and link previews (marked
 * data-server-meta); once the app runs, <Head> owns them and keeps them
 * right after client-side visits.
 */
defineProps<{ meta: PublicMetaTags }>();

const page = usePage();
const path = computed(() => page.url.split('?')[0]);
const canonical = computed(() => window.location.origin + path.value);
const alternate = (locale: 'en' | 'ar') =>
    window.location.origin +
    path.value.replace(/^\/(en|ar)(?=\/|$)/, `/${locale}`);

onMounted(() => {
    document.head
        .querySelectorAll('[data-server-meta]')
        .forEach((element) => element.remove());
});
</script>

<template>
    <Head :title="meta.title">
        <meta
            head-key="description"
            name="description"
            :content="meta.description"
        />
        <link head-key="canonical" rel="canonical" :href="canonical" />
        <link
            head-key="alternate-en"
            rel="alternate"
            hreflang="en"
            :href="alternate('en')"
        />
        <link
            head-key="alternate-ar"
            rel="alternate"
            hreflang="ar"
            :href="alternate('ar')"
        />
        <link
            head-key="alternate-default"
            rel="alternate"
            hreflang="x-default"
            :href="alternate('ar')"
        />
        <meta head-key="og:title" property="og:title" :content="meta.title" />
        <meta
            head-key="og:description"
            property="og:description"
            :content="meta.description"
        />
        <meta head-key="og:image" property="og:image" :content="meta.image" />
        <meta head-key="og:url" property="og:url" :content="canonical" />
    </Head>
</template>
