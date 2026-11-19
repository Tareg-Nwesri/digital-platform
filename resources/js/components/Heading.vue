<script setup lang="ts">
/**
 * A page or section heading. The default variant is the page title (h1)
 * with an optional eyebrow above it and actions on the reading-end side;
 * "small" is a section heading inside a page.
 */
type Props = {
    title: string;
    description?: string;
    eyebrow?: string;
    variant?: 'default' | 'small';
};

withDefaults(defineProps<Props>(), {
    variant: 'default',
});
</script>

<template>
    <header v-if="variant === 'small'" class="space-y-0.5" data-slot="heading">
        <h2 class="text-base font-semibold">
            {{ $t(title ?? '') }}
        </h2>
        <p v-if="description" class="text-sm text-muted-foreground">
            {{ $t(description ?? '') }}
        </p>
    </header>
    <header
        v-else
        class="flex min-w-0 flex-wrap items-start justify-between gap-x-4 gap-y-3"
        data-slot="heading"
    >
        <div class="min-w-0 space-y-1">
            <p
                v-if="eyebrow"
                class="text-xs font-semibold tracking-wide text-brand-ink"
            >
                {{ $t(eyebrow) }}
            </p>
            <h1
                class="text-2xl leading-tight font-bold tracking-tight text-balance"
            >
                {{ $t(title ?? '') }}
            </h1>
            <p
                v-if="description"
                class="max-w-2xl text-sm leading-relaxed text-muted-foreground"
            >
                {{ $t(description ?? '') }}
            </p>
        </div>
        <div
            v-if="$slots.actions"
            class="flex shrink-0 flex-wrap items-center gap-2"
        >
            <slot name="actions" />
        </div>
    </header>
</template>
