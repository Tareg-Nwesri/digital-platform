<script setup lang="ts">
import { computed } from 'vue';

/**
 * The outline icons of the old public site (empty states and About).
 */
const props = withDefaults(
    defineProps<{ name: string; size?: number; strokeWidth?: number }>(),
    { size: 24, strokeWidth: 1.8 },
);

const paths: Record<string, string[]> = {
    spark: [
        'M12 3v4M12 17v4M3 12h4M17 12h4',
        'm6.3 6.3 2.1 2.1M15.6 15.6l2.1 2.1M6.3 17.7l2.1-2.1M15.6 8.4l2.1-2.1',
    ],
    calendar: [
        'M4 7a2 2 0 0 1 2-2h12a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2Z',
        'M4 10h16M8 3v4M16 3v4',
    ],
    people: [
        'M9 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8Z',
        'M2 21v-1a6 6 0 0 1 12 0v1M16 3.5a4 4 0 0 1 0 7M22 21v-1a6 6 0 0 0-4-5.6',
    ],
    document: [
        'M14 3H7a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8Z',
        'M14 3v5h5M9 13h6M9 17h4',
    ],
    question: [
        'M21 12a8 8 0 0 1-11.8 7L4 20l1-5.2A8 8 0 1 1 21 12Z',
        'M9.5 9.5a2.5 2.5 0 1 1 3.5 2.3c-.6.3-1 .9-1 1.6M12 16.5h.01',
    ],
    vision: [
        'M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12Z',
        'M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z',
    ],
    mission: [
        'M20 12a8 8 0 1 1-8-8',
        'M16 12a4 4 0 1 1-4-4',
        'm12 12 9-9',
        'M16 3h5v5',
    ],
    learning: [
        'M9 18h6M9 21h6',
        'M8 14a6 6 0 1 1 8 0c-1 1-1 2-1 4H9c0-2 0-3-1-4Z',
        'm10 9 2 2 2-2M12 11v7',
    ],
    care: [
        'M15 6a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z',
        'M6 21v-3a6 6 0 0 1 12 0v3',
        'M18 3a3 3 0 0 1 0 6M21 20v-3a5 5 0 0 0-3-4M6 3a3 3 0 0 0 0 6M3 20v-3a5 5 0 0 1 3-4',
    ],
    practice: [
        'M14 3a6 6 0 0 0-7 8L3 15a3 3 0 0 0 4 4l4-4a6 6 0 0 0 8-7l-4 4-3-3 4-4Z',
    ],
    sustainability: [
        'M20 3c-7 0-14 1-14 8a7 7 0 0 0 7 7c7 0 7-8 7-15Z',
        'M4 21 15 10M10 15v-4M10 15h4',
    ],
};

const lines = computed(() => paths[props.name] ?? paths.spark);
</script>

<template>
    <svg
        viewBox="0 0 24 24"
        :width="size"
        :height="size"
        fill="none"
        stroke="currentColor"
        :stroke-width="strokeWidth"
        stroke-linecap="round"
        stroke-linejoin="round"
        aria-hidden="true"
        focusable="false"
    >
        <path v-for="(d, index) in lines" :key="index" :d="d" />
    </svg>
</template>
