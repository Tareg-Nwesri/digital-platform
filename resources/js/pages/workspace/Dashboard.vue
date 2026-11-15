<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import EventStatusBadge from '@/components/events/EventStatusBadge.vue';
import { Badge } from '@/components/ui/badge';
import { formatDate, formatNumber } from '@/lib/i18n';
import { show as showEvent } from '@/routes/workspace/events';

defineProps<{
    team: {
        slug: string;
        name: string;
        status: string;
        statusLabel: string;
        tier: string;
        logo: string | null;
    };
    role: string | null;
    stats: {
        members: { used: number; limit: number };
        projects: { published: number; drafts: number };
        robots: number;
        partsCost: { totals: string[]; unpriced: number };
    };
    upcoming: {
        id: number;
        slug: string;
        title: string;
        category: string;
        startsAt: string;
        timezone: string;
        status: string;
    }[];
}>();
</script>

<template>
    <Head :title="team.name" />

    <div class="flex flex-col gap-6 p-4 md:p-6">
        <div class="flex flex-wrap items-center gap-4">
            <img
                v-if="team.logo"
                :src="team.logo"
                alt=""
                class="size-14 rounded-lg border bg-white object-contain p-1"
            />
            <div class="grid gap-1">
                <h1 class="text-xl font-semibold">{{ team.name }}</h1>
                <div
                    class="flex flex-wrap items-center gap-2 text-sm text-muted-foreground"
                >
                    {{ $t(':tier tier', { tier: team.tier }) }}
                    <Badge v-if="role" variant="outline">{{
                        $t(`teams.roles.${role}`)
                    }}</Badge>
                    <Badge
                        v-if="team.status !== 'active'"
                        variant="destructive"
                        >{{ team.statusLabel }}</Badge
                    >
                </div>
            </div>
        </div>

        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <div class="rounded-xl border bg-card p-5" data-test="members-tile">
                <p class="text-sm text-muted-foreground">{{ $t('Members') }}</p>
                <p class="mt-1 text-3xl font-semibold tabular-nums">
                    {{ formatNumber(stats.members.used) }}
                    <span class="text-base font-normal text-muted-foreground"
                        >/ {{ formatNumber(stats.members.limit) }}</span
                    >
                </p>
            </div>
            <div
                class="rounded-xl border bg-card p-5"
                data-test="projects-tile"
            >
                <p class="text-sm text-muted-foreground">
                    {{ $t('Projects') }}
                </p>
                <p class="mt-1 text-3xl font-semibold tabular-nums">
                    {{ formatNumber(stats.projects.published) }}
                </p>
                <p class="text-xs text-muted-foreground">
                    {{
                        $t(':count drafts', {
                            count: formatNumber(stats.projects.drafts),
                        })
                    }}
                </p>
            </div>
            <div class="rounded-xl border bg-card p-5" data-test="robots-tile">
                <p class="text-sm text-muted-foreground">{{ $t('Robots') }}</p>
                <p class="mt-1 text-3xl font-semibold tabular-nums">
                    {{ formatNumber(stats.robots) }}
                </p>
            </div>
            <div class="rounded-xl border bg-card p-5" data-test="cost-tile">
                <p class="text-sm text-muted-foreground">
                    {{ $t('Parts cost') }}
                </p>
                <p
                    v-for="total in stats.partsCost.totals"
                    :key="total"
                    class="mt-1 text-2xl font-semibold tabular-nums"
                    dir="ltr"
                >
                    {{ total }}
                </p>
                <p
                    v-if="stats.partsCost.totals.length === 0"
                    class="mt-1 text-2xl font-semibold"
                >
                    —
                </p>
                <p
                    v-if="stats.partsCost.unpriced"
                    class="text-xs text-muted-foreground"
                >
                    {{
                        $t(':count without a price', {
                            count: formatNumber(stats.partsCost.unpriced),
                        })
                    }}
                </p>
            </div>
        </div>

        <section class="grid gap-3 rounded-xl border bg-card p-5">
            <h2 class="font-semibold">{{ $t('Upcoming events') }}</h2>
            <p
                v-if="upcoming.length === 0"
                class="text-sm text-muted-foreground"
            >
                {{ $t('No upcoming entries.') }}
            </p>
            <ul class="divide-y">
                <li v-for="entry in upcoming" :key="entry.id">
                    <Link
                        :href="showEvent([team.slug, entry.slug])"
                        class="flex flex-wrap items-center gap-3 py-3 hover:underline"
                    >
                        <span class="flex-1">
                            <span class="font-medium">{{ entry.title }}</span>
                            <span class="text-sm text-muted-foreground">
                                · {{ entry.category }}</span
                            >
                        </span>
                        <span class="text-sm text-muted-foreground">{{
                            formatDate(entry.startsAt, {
                                dateStyle: 'medium',
                                timeZone: entry.timezone,
                            })
                        }}</span>
                        <EventStatusBadge
                            :status="entry.status"
                            kind="registration"
                        />
                    </Link>
                </li>
            </ul>
        </section>
    </div>
</template>
