<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { index as categories } from '@/routes/console/catalogue/categories';
import { index as parts } from '@/routes/console/catalogue/parts';
import { index as suggestions } from '@/routes/console/catalogue/suggestions';
import { index as vendors } from '@/routes/console/catalogue/vendors';

defineProps<{
    current: 'parts' | 'vendors' | 'categories' | 'suggestions';
    suggestionsCount?: number;
}>();

const links = [
    ['parts', 'Parts', parts()],
    ['vendors', 'Vendors', vendors()],
    ['categories', 'Categories', categories()],
    ['suggestions', 'Suggestions', suggestions()],
] as const;
</script>

<template>
    <nav class="section-tabs" data-tour="catalogue-nav">
        <Link
            v-for="[key, label, href] in links"
            :key="key"
            :href="href"
            class="section-tab"
            :aria-current="current === key ? 'page' : undefined"
        >
            {{ $t(label) }}
            <span
                v-if="key === 'suggestions' && suggestionsCount"
                class="section-tab-count"
                >{{ suggestionsCount }}</span
            >
        </Link>
    </nav>
</template>
