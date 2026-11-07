<script setup lang="ts">
import DirectionProvider from '@/components/app/DirectionProvider.vue';
import AuthLayout from '@/layouts/auth/AuthSimpleLayout.vue';

const { title = '', description = '' } = defineProps<{
    title?: string;
    description?: string;
}>();
</script>

<template>
    <DirectionProvider>
        <AuthLayout :title="title" :description="description">
            <slot />
        </AuthLayout>
    </DirectionProvider>
</template>
