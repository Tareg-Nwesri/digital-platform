<script setup lang="ts">
import DirectionProvider from '@/components/app/DirectionProvider.vue';
import ImpersonationBanner from '@/components/app/ImpersonationBanner.vue';
import AppLayout from '@/layouts/app/AppSidebarLayout.vue';
import type { BreadcrumbItem } from '@/types';

const { breadcrumbs = [] } = defineProps<{
    breadcrumbs?: BreadcrumbItem[];
}>();
</script>

<template>
    <DirectionProvider>
        <ImpersonationBanner />
        <AppLayout :breadcrumbs="breadcrumbs">
            <slot />
        </AppLayout>
    </DirectionProvider>
</template>
