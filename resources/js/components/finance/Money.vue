<script setup lang="ts">
/**
 * An amount as formatted by the server (MoneyFormatter): Western digits,
 * grouped, with the ISO code. Numbers read left to right even in Arabic.
 */
defineProps<{
    value: { amountMinor: number; currency: string; formatted: string } | null;
    sign?: 'in' | 'out';
}>();
</script>

<template>
    <span
        v-if="value"
        dir="ltr"
        class="whitespace-nowrap tabular-nums"
        :class="[
            value.amountMinor < 0 && 'text-destructive',
            sign === 'in' && 'text-emerald-700 dark:text-emerald-400',
        ]"
        >{{ sign === 'out' ? '−' : sign === 'in' ? '+' : ''
        }}{{ value.formatted }}</span
    >
    <span v-else class="text-muted-foreground">—</span>
</template>
