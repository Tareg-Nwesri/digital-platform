<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import Heading from '@/components/Heading.vue';
import { Badge } from '@/components/ui/badge';

defineProps<{
    department: { slug: string; name: string; isAdmin: boolean };
    membersCount: number;
    myPermissions: string[];
}>();
</script>

<template>
    <Head :title="department.name" />

    <div class="flex flex-col gap-6 p-4 md:p-6">
        <div class="flex flex-wrap items-center gap-3">
            <Heading
                :title="department.name"
                description="Department workspace"
            />
            <Badge v-if="department.isAdmin" variant="outline">Admin</Badge>
        </div>

        <div class="grid gap-4 sm:grid-cols-2">
            <div class="rounded-xl border bg-card p-5">
                <p class="text-sm text-muted-foreground">Members</p>
                <p class="mt-1 text-3xl font-semibold tabular-nums">
                    {{ membersCount }}
                </p>
            </div>
            <div class="rounded-xl border bg-card p-5">
                <p class="text-sm text-muted-foreground">Your access</p>
                <div class="mt-2 flex flex-wrap gap-2">
                    <Badge
                        v-for="permission in myPermissions"
                        :key="permission"
                        variant="secondary"
                        >{{ permission }}</Badge
                    >
                    <span
                        v-if="!myPermissions.length"
                        class="text-sm text-muted-foreground"
                        >No areas granted yet.</span
                    >
                </div>
            </div>
        </div>
    </div>
</template>
