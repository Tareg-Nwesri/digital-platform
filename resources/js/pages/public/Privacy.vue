<script setup lang="ts">
import PublicHead from '@/components/public/PublicHead.vue';
import QuestionsPage from '@/components/public/QuestionsPage.vue';
import type { PublicMetaTags } from '@/composables/usePublicSite';

defineProps<{ meta: PublicMetaTags }>();
</script>

<template>
    <PublicHead :meta="meta" />
    <QuestionsPage kind="privacy" />
</template>
