<script setup lang="ts">
import HeaderQuickActions from '@/components/app/HeaderQuickActions.vue';
import TourButton from '@/components/app/TourButton.vue';
import Breadcrumbs from '@/components/Breadcrumbs.vue';
import { Separator } from '@/components/ui/separator';
import { SidebarTrigger } from '@/components/ui/sidebar';
import type { BreadcrumbItem } from '@/types';

withDefaults(
    defineProps<{
        breadcrumbs?: BreadcrumbItem[];
    }>(),
    {
        breadcrumbs: () => [],
    },
);
</script>

<template>
    <header
        class="sticky top-0 z-20 flex h-14 shrink-0 items-center gap-2 border-b border-sidebar-border/70 bg-background/85 px-4 backdrop-blur supports-[backdrop-filter]:bg-background/70 md:rounded-t-xl"
    >
        <div class="flex min-w-0 items-center gap-2">
            <SidebarTrigger class="-ms-1 shrink-0" />
            <template v-if="breadcrumbs && breadcrumbs.length > 0">
                <Separator
                    orientation="vertical"
                    class="me-1 data-[orientation=vertical]:h-4"
                />
                <Breadcrumbs :breadcrumbs="breadcrumbs" class="min-w-0" />
            </template>
        </div>
        <div class="ms-auto flex shrink-0 items-center gap-1">
            <HeaderQuickActions />
            <TourButton />
        </div>
    </header>
</template>
