<script setup lang="ts">
import { Badge } from '@/components/ui/badge';

defineProps<{ status: string }>();

const variants: Record<
    string,
    'default' | 'secondary' | 'destructive' | 'outline'
> = {
    approved: 'default',
    suggested: 'secondary',
    rejected: 'destructive',
    archived: 'outline',
};
</script>

<template>
    <Badge :variant="variants[status] ?? 'outline'" :data-status="status">
        {{ $t(`catalogue.status.${status}`) }}
    </Badge>
</template>
