<script setup lang="ts">
import { Link } from '@inertiajs/vue3';

defineProps<{
    title: string;
    lead?: string | null;
    eyebrow?: string | null;
    back?: string | null;
    backLabel?: string | null;
}>();
</script>

<template>
    <section class="page-hero">
        <div class="public-shell">
            <Link v-if="back" class="back-link" :href="back">{{
                backLabel
            }}</Link>
            <div v-if="$slots.meta" class="meta-row page-hero__meta">
                <slot name="meta" />
            </div>
            <p v-if="eyebrow" class="eyebrow">{{ eyebrow }}</p>
            <h1>{{ title }}</h1>
            <p v-if="lead" class="page-hero__lead">{{ lead }}</p>
            <div v-if="$slots.default" class="actions page-hero__actions">
                <slot />
            </div>
        </div>
    </section>
</template>
