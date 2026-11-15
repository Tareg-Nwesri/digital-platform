<script setup lang="ts">
import { Link } from '@inertiajs/vue3';

defineProps<{
    paginator: {
        current_page: number;
        last_page: number;
        prev_page_url: string | null;
        next_page_url: string | null;
    };
}>();
</script>

<template>
    <nav
        v-if="paginator.last_page > 1"
        class="listing-pages"
        :aria-label="
            $t('public.page', {
                page: String(paginator.current_page),
                pages: String(paginator.last_page),
            })
        "
    >
        <Link
            v-if="paginator.prev_page_url"
            :href="paginator.prev_page_url"
            rel="prev"
            >{{ $t('public.previous') }}</Link
        >
        <span aria-current="page">{{
            $t('public.page', {
                page: String(paginator.current_page),
                pages: String(paginator.last_page),
            })
        }}</span>
        <Link
            v-if="paginator.next_page_url"
            :href="paginator.next_page_url"
            rel="next"
            >{{ $t('public.next') }}</Link
        >
    </nav>
</template>
