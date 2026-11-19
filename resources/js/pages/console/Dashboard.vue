<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import {
    Activity as ActivityIcon,
    ArrowUpRight,
    CalendarClock,
    CheckCircle2,
    Globe,
    Users,
    Wallet,
} from '@lucide/vue';
import { computed, ref } from 'vue';
import Heading from '@/components/Heading.vue';
import { formatDate, formatNumber } from '@/lib/i18n';
import { dashboard } from '@/routes/console';
import { show as showEvent } from '@/routes/console/events';

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Organisation console', href: dashboard() }],
    },
});

const props = defineProps<{
    attention: {
        key: string;
        label: string;
        hint: string;
        count: number;
        href: string;
        tone: 'urgent' | 'finance' | 'normal';
    }[];
    events: {
        slug: string;
        title: string;
        status: string;
        startsAt: string;
        timezone: string;
        daysToStart: number;
        daysToClose: number | null;
        capacity: number | null;
        approved: number;
        pending: number;
        waitlisted: number;
        fromWebsite: number;
    }[];
    registrations: {
        days: { date: string; count: number }[];
        total: number;
        fromWebsite: number;
    };
    finance: {
        funds: { name: string; balance: string }[];
        monthIn: string[];
        monthOut: string[];
        teamBudgets: string[];
    };
    community: {
        activeTeams: number;
        pendingTeams: number;
        members: number;
        robots: number;
        publishedProjects: number;
        newTeamsThisMonth: number;
    };
    activity: {
        id: number;
        description: string;
        area: string | null;
        causer: string | null;
        at: string | null;
    }[];
}>();

const waiting = computed(() =>
    props.attention.reduce((sum, item) => sum + item.count, 0),
);

/* Registrations chart: one series, one hue; the tallest bar sets the scale. */
const peak = computed(() =>
    Math.max(1, ...props.registrations.days.map((day) => day.count)),
);
const hovered = ref<number | null>(null);

function fill(event: (typeof props.events)[number]): {
    approved: number;
    pending: number;
} {
    const total =
        event.capacity ??
        Math.max(event.approved + event.pending + event.waitlisted, 1);

    return {
        approved: Math.min(100, (event.approved / total) * 100),
        pending: Math.min(
            100 - Math.min(100, (event.approved / total) * 100),
            (event.pending / total) * 100,
        ),
    };
}

const toneClass: Record<string, string> = {
    urgent: 'before:bg-primary',
    finance: 'before:bg-amber-500',
    normal: 'before:bg-sky-500',
};

const shortDate = (date: string) =>
    formatDate(`${date}T00:00`, { day: 'numeric', month: 'short' });
</script>

<template>
    <Head :title="$t('Organisation console')" />

    <div class="flex flex-col gap-6 p-4 md:p-6">
        <div class="flex flex-wrap items-end justify-between gap-3">
            <Heading
                :title="$t('Organisation console')"
                :description="
                    waiting
                        ? $t(':count items are waiting for someone.', {
                              count: formatNumber(waiting),
                          })
                        : $t('Nothing is waiting. Everything is up to date.')
                "
            />
        </div>

        <!-- What waits for someone -->
        <section
            data-tour="dashboard-attention"
            :aria-label="$t('Needs your attention')"
            class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4"
        >
            <Link
                v-for="item in attention"
                :key="item.key"
                :href="item.href"
                class="group relative flex flex-col gap-1 overflow-hidden rounded-xl border bg-card p-4 ps-5 transition-colors before:absolute before:inset-y-0 before:start-0 before:w-1 hover:bg-accent"
                :class="
                    item.count ? toneClass[item.tone] : 'before:bg-transparent'
                "
                :data-test="`attention-${item.key}`"
            >
                <span
                    class="flex items-center justify-between gap-2 text-sm text-muted-foreground"
                >
                    {{ item.label }}
                    <ArrowUpRight
                        class="size-4 opacity-0 transition-opacity group-hover:opacity-100 rtl:-scale-x-100"
                    />
                </span>
                <span class="flex items-baseline gap-2">
                    <span
                        class="text-3xl font-semibold tabular-nums"
                        :class="{ 'text-muted-foreground': !item.count }"
                        >{{ formatNumber(item.count) }}</span
                    >
                    <CheckCircle2
                        v-if="!item.count"
                        class="size-4 text-emerald-600"
                        :aria-label="$t('Done')"
                    />
                </span>
                <span class="text-xs text-muted-foreground">{{
                    item.hint
                }}</span>
            </Link>
        </section>

        <div class="grid gap-6 xl:grid-cols-[minmax(0,3fr)_minmax(0,2fr)]">
            <!-- Upcoming events -->
            <section
                data-tour="dashboard-events"
                class="grid content-start gap-4 rounded-xl border bg-card p-5"
            >
                <h2 class="flex items-center gap-2 font-semibold">
                    <CalendarClock class="size-4 text-muted-foreground" />
                    {{ $t('Upcoming events') }}
                </h2>
                <p
                    v-if="events.length === 0"
                    class="text-sm text-muted-foreground"
                >
                    {{ $t('No events are open or coming up.') }}
                </p>
                <article
                    v-for="event in events"
                    :key="event.slug"
                    class="grid gap-2"
                    :data-test="`event-${event.slug}`"
                >
                    <div
                        class="flex flex-wrap items-baseline justify-between gap-2"
                    >
                        <Link
                            :href="showEvent(event.slug)"
                            class="font-medium hover:underline"
                            >{{ event.title }}</Link
                        >
                        <span class="text-xs text-muted-foreground">
                            {{
                                formatDate(event.startsAt, {
                                    day: 'numeric',
                                    month: 'long',
                                    timeZone: event.timezone,
                                })
                            }}
                            ·
                            {{
                                event.daysToStart > 0
                                    ? $t('in :count days', {
                                          count: formatNumber(
                                              event.daysToStart,
                                          ),
                                      })
                                    : $t('running now')
                            }}
                        </span>
                    </div>
                    <div
                        class="flex h-2.5 w-full gap-0.5 overflow-hidden rounded-full bg-muted"
                        role="img"
                        :aria-label="
                            $t(
                                ':approved approved and :pending pending of :capacity places',
                                {
                                    approved: formatNumber(event.approved),
                                    pending: formatNumber(event.pending),
                                    capacity: event.capacity
                                        ? formatNumber(event.capacity)
                                        : '∞',
                                },
                            )
                        "
                    >
                        <span
                            class="h-full rounded-full bg-primary"
                            :style="{ width: `${fill(event).approved}%` }"
                        />
                        <span
                            class="h-full rounded-full bg-primary/35"
                            :style="{ width: `${fill(event).pending}%` }"
                        />
                    </div>
                    <div
                        class="flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-muted-foreground"
                    >
                        <span class="flex items-center gap-1.5"
                            ><span class="size-2 rounded-full bg-primary" />{{
                                $t(':count approved', {
                                    count: formatNumber(event.approved),
                                })
                            }}<template v-if="event.capacity">
                                / {{ formatNumber(event.capacity) }}</template
                            ></span
                        >
                        <span class="flex items-center gap-1.5"
                            ><span
                                class="size-2 rounded-full bg-primary/35"
                            />{{
                                $t(':count pending', {
                                    count: formatNumber(event.pending),
                                })
                            }}</span
                        >
                        <span v-if="event.waitlisted">{{
                            $t(':count waitlisted', {
                                count: formatNumber(event.waitlisted),
                            })
                        }}</span>
                        <span
                            v-if="event.fromWebsite"
                            class="flex items-center gap-1"
                            ><Globe class="size-3" />{{
                                $t(':count from the website', {
                                    count: formatNumber(event.fromWebsite),
                                })
                            }}</span
                        >
                        <span
                            v-if="event.daysToClose !== null"
                            class="ms-auto font-medium text-foreground"
                            >{{
                                event.daysToClose > 0
                                    ? $t('Registration closes in :count days', {
                                          count: formatNumber(
                                              event.daysToClose,
                                          ),
                                      })
                                    : $t('Registration closes today')
                            }}</span
                        >
                    </div>
                </article>
            </section>

            <!-- Registrations over the last 14 days -->
            <section
                class="grid content-start gap-3 rounded-xl border bg-card p-5"
                data-test="registrations-chart"
                data-tour="dashboard-registrations"
            >
                <div class="flex items-baseline justify-between gap-2">
                    <h2 class="font-semibold">
                        {{ $t('Registrations, last 14 days') }}
                    </h2>
                    <span class="text-2xl font-semibold tabular-nums">{{
                        formatNumber(registrations.total)
                    }}</span>
                </div>
                <p class="text-xs text-muted-foreground">
                    {{
                        $t(':count from the website', {
                            count: formatNumber(registrations.fromWebsite),
                        })
                    }}
                </p>
                <div class="relative mt-2">
                    <div
                        class="flex h-32 items-end gap-0.5"
                        @mouseleave="hovered = null"
                    >
                        <button
                            v-for="(day, index) in registrations.days"
                            :key="day.date"
                            type="button"
                            class="group relative flex h-full flex-1 items-end focus:outline-none"
                            :aria-label="`${shortDate(day.date)}: ${formatNumber(day.count)}`"
                            @mouseenter="hovered = index"
                            @focus="hovered = index"
                        >
                            <span
                                class="w-full rounded-t-[4px] transition-colors"
                                :class="[
                                    day.count ? 'bg-primary' : 'bg-muted',
                                    hovered === index
                                        ? 'opacity-100'
                                        : hovered === null
                                          ? 'opacity-90'
                                          : 'opacity-50',
                                ]"
                                :style="{
                                    height: day.count
                                        ? `${Math.max(6, (day.count / peak) * 100)}%`
                                        : '2px',
                                }"
                            />
                        </button>
                    </div>
                    <div
                        v-if="hovered !== null"
                        class="pointer-events-none absolute -top-2 rounded-md border bg-popover px-2 py-1 text-xs shadow-sm"
                        :style="{
                            insetInlineStart: `calc(${(hovered / registrations.days.length) * 100}% - 1rem)`,
                        }"
                        role="status"
                    >
                        <span class="text-muted-foreground">{{
                            shortDate(registrations.days[hovered].date)
                        }}</span>
                        ·
                        <span class="font-semibold tabular-nums">{{
                            formatNumber(registrations.days[hovered].count)
                        }}</span>
                    </div>
                    <div
                        class="mt-1 flex justify-between text-[11px] text-muted-foreground tabular-nums"
                    >
                        <span>{{ shortDate(registrations.days[0].date) }}</span>
                        <span>{{ $t('Today') }}</span>
                    </div>
                </div>
                <table class="sr-only">
                    <caption>
                        {{
                            $t('Registrations, last 14 days')
                        }}
                    </caption>
                    <tbody>
                        <tr v-for="day in registrations.days" :key="day.date">
                            <th scope="row">{{ shortDate(day.date) }}</th>
                            <td>{{ day.count }}</td>
                        </tr>
                    </tbody>
                </table>
            </section>
        </div>

        <div class="grid gap-6 xl:grid-cols-2">
            <!-- Money -->
            <section
                data-tour="dashboard-money"
                class="grid content-start gap-4 rounded-xl border bg-card p-5"
            >
                <h2 class="flex items-center gap-2 font-semibold">
                    <Wallet class="size-4 text-muted-foreground" />
                    {{ $t('Money') }}
                </h2>
                <div class="grid gap-3 sm:grid-cols-3">
                    <div>
                        <p class="text-xs text-muted-foreground">
                            {{ $t('In this month') }}
                        </p>
                        <p
                            v-for="amount in finance.monthIn"
                            :key="amount"
                            class="text-lg font-semibold tabular-nums"
                            dir="ltr"
                        >
                            {{ amount }}
                        </p>
                        <p
                            v-if="!finance.monthIn.length"
                            class="text-lg font-semibold"
                        >
                            —
                        </p>
                    </div>
                    <div>
                        <p class="text-xs text-muted-foreground">
                            {{ $t('Spent this month') }}
                        </p>
                        <p
                            v-for="amount in finance.monthOut"
                            :key="amount"
                            class="text-lg font-semibold tabular-nums"
                            dir="ltr"
                        >
                            {{ amount }}
                        </p>
                        <p
                            v-if="!finance.monthOut.length"
                            class="text-lg font-semibold"
                        >
                            —
                        </p>
                    </div>
                    <div>
                        <p class="text-xs text-muted-foreground">
                            {{ $t('Held in team budgets') }}
                        </p>
                        <p
                            v-for="amount in finance.teamBudgets"
                            :key="amount"
                            class="text-lg font-semibold tabular-nums"
                            dir="ltr"
                        >
                            {{ amount }}
                        </p>
                        <p
                            v-if="!finance.teamBudgets.length"
                            class="text-lg font-semibold"
                        >
                            —
                        </p>
                    </div>
                </div>
                <ul
                    v-if="finance.funds.length"
                    class="divide-y rounded-lg border text-sm"
                >
                    <li
                        v-for="fund in finance.funds"
                        :key="fund.name"
                        class="flex items-center justify-between gap-2 px-3 py-2"
                    >
                        <span>{{ fund.name }}</span>
                        <span class="font-medium tabular-nums" dir="ltr">{{
                            fund.balance
                        }}</span>
                    </li>
                </ul>
            </section>

            <!-- Community -->
            <section
                data-tour="dashboard-community"
                class="grid content-start gap-4 rounded-xl border bg-card p-5"
            >
                <h2 class="flex items-center gap-2 font-semibold">
                    <Users class="size-4 text-muted-foreground" />
                    {{ $t('Community') }}
                </h2>
                <dl class="grid grid-cols-2 gap-4 sm:grid-cols-3">
                    <div>
                        <dt class="text-xs text-muted-foreground">
                            {{ $t('Active teams') }}
                        </dt>
                        <dd class="text-2xl font-semibold tabular-nums">
                            {{ formatNumber(community.activeTeams) }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs text-muted-foreground">
                            {{ $t('Team members') }}
                        </dt>
                        <dd class="text-2xl font-semibold tabular-nums">
                            {{ formatNumber(community.members) }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs text-muted-foreground">
                            {{ $t('New teams this month') }}
                        </dt>
                        <dd class="text-2xl font-semibold tabular-nums">
                            {{ formatNumber(community.newTeamsThisMonth) }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs text-muted-foreground">
                            {{ $t('Robots') }}
                        </dt>
                        <dd class="text-2xl font-semibold tabular-nums">
                            {{ formatNumber(community.robots) }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs text-muted-foreground">
                            {{ $t('Published projects') }}
                        </dt>
                        <dd class="text-2xl font-semibold tabular-nums">
                            {{ formatNumber(community.publishedProjects) }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs text-muted-foreground">
                            {{ $t('Pending teams') }}
                        </dt>
                        <dd class="text-2xl font-semibold tabular-nums">
                            {{ formatNumber(community.pendingTeams) }}
                        </dd>
                    </div>
                </dl>
            </section>
        </div>

        <!-- Recent activity -->
        <section
            class="grid gap-3 rounded-xl border bg-card p-5"
            data-tour="dashboard-activity"
        >
            <h2 class="flex items-center gap-2 font-semibold">
                <ActivityIcon class="size-4 text-muted-foreground" />
                {{ $t('Recent activity') }}
            </h2>
            <p
                v-if="activity.length === 0"
                class="text-sm text-muted-foreground"
            >
                {{ $t('Nothing yet.') }}
            </p>
            <ul class="divide-y text-sm">
                <li
                    v-for="entry in activity"
                    :key="entry.id"
                    class="flex flex-wrap items-center gap-x-3 gap-y-1 py-2"
                >
                    <span class="flex-1">{{ entry.description }}</span>
                    <span v-if="entry.causer" class="text-muted-foreground">{{
                        entry.causer
                    }}</span>
                    <span class="text-xs text-muted-foreground tabular-nums">{{
                        formatDate(entry.at, {
                            day: 'numeric',
                            month: 'short',
                            hour: '2-digit',
                            minute: '2-digit',
                        })
                    }}</span>
                </li>
            </ul>
        </section>
    </div>
</template>
