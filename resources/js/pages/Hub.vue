<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import Heading from '@/components/Heading.vue';
import { Badge } from '@/components/ui/badge';
import { hub } from '@/routes';
import { dashboard as consoleDashboard } from '@/routes/console';
import { dashboard as departmentDashboard } from '@/routes/console/departments';
import { dashboard as teamDashboard } from '@/routes/workspace';

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Your workspaces', href: hub() }],
    },
});

defineProps<{
    teams: {
        slug: string;
        name: string;
        role: string | null;
        status: string;
        logoUrl: string | null;
    }[];
    departments: { slug: string; name: string; isAdmin: boolean }[];
    isSuperAdmin: boolean;
}>();
</script>

<template>
    <Head title="Your workspaces" />

    <div class="flex flex-col gap-8 p-4 md:p-6">
        <Heading
            title="Your workspaces"
            description="Choose where you want to work."
        />

        <Link
            v-if="isSuperAdmin"
            :href="consoleDashboard()"
            class="rounded-xl border bg-card p-5 transition hover:border-foreground/30"
            data-test="hub-console"
        >
            <p class="font-semibold">Organisation console</p>
            <p class="text-sm text-muted-foreground">
                Departments, teams, tiers and platform settings.
            </p>
        </Link>

        <section v-if="teams.length" class="grid gap-3">
            <h2 class="text-sm font-semibold text-muted-foreground">Teams</h2>
            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                <Link
                    v-for="team in teams"
                    :key="team.slug"
                    :href="teamDashboard(team.slug)"
                    class="flex items-center justify-between gap-3 rounded-xl border bg-card p-5 transition hover:border-foreground/30"
                    data-test="hub-team"
                >
                    <div class="min-w-0">
                        <p class="truncate font-semibold">{{ team.name }}</p>
                        <p class="text-sm text-muted-foreground capitalize">
                            {{ team.role }}
                        </p>
                    </div>
                    <Badge
                        v-if="team.status !== 'active'"
                        variant="secondary"
                        class="capitalize"
                        >{{ team.status }}</Badge
                    >
                </Link>
            </div>
        </section>

        <section v-if="departments.length" class="grid gap-3">
            <h2 class="text-sm font-semibold text-muted-foreground">
                Departments
            </h2>
            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                <Link
                    v-for="department in departments"
                    :key="department.slug"
                    :href="departmentDashboard(department.slug)"
                    class="flex items-center justify-between gap-3 rounded-xl border bg-card p-5 transition hover:border-foreground/30"
                    data-test="hub-department"
                >
                    <p class="truncate font-semibold">{{ department.name }}</p>
                    <Badge v-if="department.isAdmin" variant="outline"
                        >Admin</Badge
                    >
                </Link>
            </div>
        </section>

        <p
            v-if="!isSuperAdmin && !teams.length && !departments.length"
            class="rounded-xl border border-dashed p-6 text-sm text-muted-foreground"
        >
            You are not part of any team or department yet. Ask an organiser to
            invite you.
        </p>
    </div>
</template>
