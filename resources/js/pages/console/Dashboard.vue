<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import Heading from '@/components/Heading.vue';
import { dashboard } from '@/routes/console';

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Organisation console', href: dashboard() }],
    },
});

defineProps<{
    stats: {
        teams: { active: number; pending: number; suspended: number };
        departments: number;
        users: number;
    };
}>();
</script>

<template>
    <Head title="Organisation console" />

    <div class="flex flex-col gap-6 p-4 md:p-6">
        <Heading
            title="Organisation console"
            description="An overview of the whole platform."
        />

        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <div class="rounded-xl border bg-card p-5">
                <p class="text-sm text-muted-foreground">Active teams</p>
                <p class="mt-1 text-3xl font-semibold tabular-nums">
                    {{ stats.teams.active }}
                </p>
            </div>
            <div class="rounded-xl border bg-card p-5">
                <p class="text-sm text-muted-foreground">Pending teams</p>
                <p class="mt-1 text-3xl font-semibold tabular-nums">
                    {{ stats.teams.pending }}
                </p>
            </div>
            <div class="rounded-xl border bg-card p-5">
                <p class="text-sm text-muted-foreground">Departments</p>
                <p class="mt-1 text-3xl font-semibold tabular-nums">
                    {{ stats.departments }}
                </p>
            </div>
            <div class="rounded-xl border bg-card p-5">
                <p class="text-sm text-muted-foreground">People with a login</p>
                <p class="mt-1 text-3xl font-semibold tabular-nums">
                    {{ stats.users }}
                </p>
            </div>
        </div>
    </div>
</template>
