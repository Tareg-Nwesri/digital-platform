<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import Heading from '@/components/Heading.vue';
import { Badge } from '@/components/ui/badge';

defineProps<{
    team: {
        slug: string;
        name: string;
        status: string;
        statusLabel: string;
        tier: string;
    };
    role: string | null;
    stats: { members: { used: number; limit: number } };
}>();
</script>

<template>
    <Head :title="team.name" />

    <div class="flex flex-col gap-6 p-4 md:p-6">
        <div class="flex flex-wrap items-center gap-3">
            <Heading :title="team.name" :description="`${team.tier} tier`" />
            <Badge v-if="role" variant="outline" class="capitalize">{{
                role
            }}</Badge>
            <Badge v-if="team.status !== 'active'" variant="destructive">{{
                team.statusLabel
            }}</Badge>
        </div>

        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <div class="rounded-xl border bg-card p-5" data-test="members-tile">
                <p class="text-sm text-muted-foreground">Members</p>
                <p class="mt-1 text-3xl font-semibold tabular-nums">
                    {{ stats.members.used }}
                    <span class="text-base font-normal text-muted-foreground"
                        >/ {{ stats.members.limit }}</span
                    >
                </p>
            </div>
        </div>
    </div>
</template>
