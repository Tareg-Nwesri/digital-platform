<script setup lang="ts">
import { Badge } from '@/components/ui/badge';

defineProps<{ status: string; kind?: 'post' | 'message' }>();

const variants: Record<
    string,
    'default' | 'secondary' | 'destructive' | 'outline'
> = {
    draft: 'outline',
    in_review: 'secondary',
    scheduled: 'secondary',
    published: 'default',
    archived: 'outline',
    new: 'default',
    in_progress: 'secondary',
    closed: 'outline',
};
</script>

<template>
    <Badge :variant="variants[status] ?? 'outline'" :data-status="status">
        {{
            $t(
                kind === 'message'
                    ? `content.message_status.${status}`
                    : `content.post_status.${status}`,
            )
        }}
    </Badge>
</template>
