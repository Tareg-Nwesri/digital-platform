<script setup lang="ts">
import { Badge } from '@/components/ui/badge';

defineProps<{ status: string }>();

const variants: Record<
    string,
    'default' | 'secondary' | 'destructive' | 'outline'
> = {
    draft: 'outline',
    submitted: 'secondary',
    posted: 'default',
    rejected: 'destructive',
    reversed: 'outline',
};
</script>

<template>
    <Badge :variant="variants[status] ?? 'outline'" :data-status="status">
        {{ $t(`finance.transaction_status.${status}`) }}
    </Badge>
</template>
