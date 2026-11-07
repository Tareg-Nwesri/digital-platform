<script setup lang="ts">
import { computed } from 'vue';
import { Badge } from '@/components/ui/badge';

const props = defineProps<{ status: string }>();

const variant = computed(
    () =>
        (
            {
                active: 'default',
                pending: 'secondary',
                suspended: 'destructive',
                archived: 'outline',
            } as const
        )[props.status as 'active' | 'pending' | 'suspended' | 'archived'] ??
        'outline',
);
</script>

<template>
    <Badge :variant="variant" :data-status="status">
        {{ $t(`team.status.${status}`) }}
    </Badge>
</template>
