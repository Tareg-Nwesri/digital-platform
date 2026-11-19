<script setup lang="ts">
import { CircleHelp } from '@lucide/vue';
import { Button } from '@/components/ui/button';
import { usePageTour } from '@/composables/usePageTour';

/**
 * The "?" in the top bar: starts the guided tour of the current page.
 */
const { start, hasTour } = usePageTour();
</script>

<template>
    <Button
        v-if="hasTour()"
        type="button"
        variant="ghost"
        size="icon"
        data-tour="tour-button"
        data-test="tour-button"
        :aria-label="$t('tour.ui.button')"
        :title="$t('tour.ui.button')"
        @click="start"
    >
        <CircleHelp class="size-5" />
    </Button>
</template>
