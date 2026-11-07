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
    <nav class="flex gap-1 overflow-x-auto border-b">
        <Link
            v-for="[key, label, href] in links"
            :key="key"
            :href="href"
            class="-mb-px flex items-center gap-2 border-b-2 px-3 py-2 text-sm font-medium whitespace-nowrap transition-colors"
            :class="
                current === key
                    ? 'border-primary text-foreground'
                    : 'border-transparent text-muted-foreground hover:text-foreground'
            "
            :aria-current="current === key ? 'page' : undefined"
        >
            {{ $t(label) }}
            <span
                v-if="key === 'suggestions' && suggestionsCount"
                class="rounded-full bg-primary px-1.5 text-xs text-primary-foreground tabular-nums"
                >{{ suggestionsCount }}</span
            >
        </Link>
    </nav>
</template>
