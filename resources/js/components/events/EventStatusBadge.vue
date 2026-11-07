<script setup lang="ts">
import { computed } from 'vue';
import { Badge } from '@/components/ui/badge';

const props = defineProps<{
    status: string;
    kind?: 'event' | 'registration';
}>();

const variants: Record<
    string,
    'default' | 'secondary' | 'destructive' | 'outline'
> = {
    draft: 'outline',
    open: 'default',
    closed: 'secondary',
    running: 'default',
    finished: 'secondary',
    archived: 'outline',
    invited: 'outline',
    pending: 'secondary',
    approved: 'default',
    waitlisted: 'secondary',
    rejected: 'destructive',
    withdrawn: 'outline',
};

const label = computed(() =>
    props.kind === 'registration'
        ? `events.registration_status.${props.status}`
        : `events.status.${props.status}`,
);
</script>

<template>
    <Badge :variant="variants[status] ?? 'outline'" :data-status="status">
        {{ $t(label) }}
    </Badge>
</template>
