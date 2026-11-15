<script setup lang="ts">
import PublicIcon from './PublicIcon.vue';

withDefaults(defineProps<{ icon?: string; title?: string | null }>(), {
    icon: 'spark',
    title: null,
});
</script>

<template>
    <div class="empty-state">
        <span class="empty-state__icon" aria-hidden="true">
            <PublicIcon :name="icon" />
        </span>
        <div class="empty-state__copy">
            <strong v-if="title">{{ title }}</strong>
            <p><slot /></p>
        </div>
        <slot name="action" />
    </div>
</template>
