<script setup lang="ts">
import { ConfigProvider } from 'reka-ui';
import { useDirection } from '@/composables/useDirection';

/**
 * Tells every reka-ui primitive (menus, dialogs, selects, tooltips) the page
 * direction, so popovers open, align and take arrow keys the right way in
 * Arabic.
 */
const { dir } = useDirection();
</script>

<template>
    <ConfigProvider :dir="dir">
        <slot />
    </ConfigProvider>
</template>
