<script setup lang="ts">
import DatePicker from '@/components/forms/DatePicker.vue';
import { Head, router } from '@inertiajs/vue3';
import { Download } from '@lucide/vue';
import { computed, ref } from 'vue';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { formatDate, formatNumber } from '@/lib/i18n';
import { dashboard } from '@/routes/console';
import { department as departmentReport } from '@/routes/console/reports';

type Entry = {
    id: number;
    description: string;
    event: string | null;
    log: string | null;
    causer: string | null;
    impersonated: boolean;
    subject: string | null;
    at: string | null;
};

const props = defineProps<{
    department: { slug: string; name: string };
    range: { from: string; to: string };
    report: {
        areas: string[];
        events?: {
            byStatus: Record<string, number>;
            registrations: Record<string, number>;
        };
        catalogue?: { suggested: number; approved: number; rejected: number };
        content?: { published: number; drafts: number };
        inbox?: { new: number; inProgress: number; closed: number };
        finance?: { income: string; spending: string };
        activity: {
            data: Entry[];
            meta: { current_page: number; last_page: number; total: number };
        };
    };
    can: { export: boolean };
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Organisation console', href: dashboard() }],
    },
});

const from = ref(props.range.from);
const to = ref(props.range.to);

function apply(page = 1): void {
    router.get(
        departmentReport(props.department.slug).url,
        { from: from.value, to: to.value, page },
        { preserveScroll: true, replace: true },
    );
}

const csvUrl = computed(
    () =>
        departmentReport(props.department.slug, {
            query: {
                from: props.range.from,
                to: props.range.to,
                format: 'csv',
            },
        }).url,
);

const tiles = computed(() => {
    const items: { label: string; value: string }[] = [];
    const r = props.report;

    if (r.events) {
        const active =
            (r.events.byStatus.open ?? 0) +
            (r.events.byStatus.closed ?? 0) +
            (r.events.byStatus.running ?? 0);
        items.push({ label: 'Active events', value: formatNumber(active) });
        items.push({
            label: 'Approved entries',
            value: formatNumber(r.events.registrations.approved ?? 0),
        });
        items.push({
            label: 'Entries waiting',
            value: formatNumber(
                (r.events.registrations.pending ?? 0) +
                    (r.events.registrations.waitlisted ?? 0),
            ),
        });
    }

    if (r.content) {
        items.push({
            label: 'News published',
            value: formatNumber(r.content.published),
        });
    }

    if (r.inbox) {
        items.push({ label: 'New messages', value: formatNumber(r.inbox.new) });
        items.push({
            label: 'Messages closed',
            value: formatNumber(r.inbox.closed),
        });
    }

    if (r.catalogue) {
        items.push({
            label: 'Suggestions received',
            value: formatNumber(r.catalogue.suggested),
        });
        items.push({
            label: 'Suggestions approved',
            value: formatNumber(r.catalogue.approved),
        });
    }

    if (r.finance) {
        items.push({ label: 'Income (LYD)', value: r.finance.income });
        items.push({ label: 'Spending (LYD)', value: r.finance.spending });
    }

    return items;
});
</script>

<template>
    <Head :title="$t('Report')" />

    <div class="flex flex-col gap-6 p-4 md:p-6">
        <div class="flex flex-wrap items-end justify-between gap-3">
            <Heading
                :title="$t('Report: :name', { name: department.name })"
                :description="
                    $t(
                        'What happened in this department’s areas over the period.',
                    )
                "
            />
            <Button
                v-if="can.export"
                as="a"
                :href="csvUrl"
                variant="outline"
                data-test="export-csv"
                data-tour="report-export"
            >
                <Download class="size-4" />
                {{ $t('Download CSV') }}
            </Button>
        </div>

        <form
            class="flex flex-wrap items-end gap-3"
            data-tour="report-range"
            @submit.prevent="apply()"
        >
            <div class="grid gap-1.5">
                <Label for="report-from">{{ $t('From') }}</Label>
                <DatePicker id="report-from" v-model="from" class="w-44" />
            </div>
            <div class="grid gap-1.5">
                <Label for="report-to">{{ $t('To') }}</Label>
                <DatePicker id="report-to" v-model="to" class="w-44" />
            </div>
            <Button type="submit" variant="outline">{{ $t('Show') }}</Button>
        </form>

        <p v-if="tiles.length === 0" class="text-sm text-muted-foreground">
            {{ $t('This department has no areas to report on yet.') }}
        </p>
        <div
            class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4"
            data-tour="report-tiles"
        >
            <div
                v-for="tile in tiles"
                :key="tile.label"
                class="rounded-xl border bg-card p-4"
            >
                <p class="text-sm text-muted-foreground">
                    {{ $t(tile.label) }}
                </p>
                <p class="mt-1 text-2xl font-semibold tabular-nums" dir="ltr">
                    {{ tile.value }}
                </p>
            </div>
        </div>

        <section class="grid gap-3" data-tour="report-members">
            <h3 class="font-semibold">{{ $t('Activity by members') }}</h3>
            <div class="overflow-x-auto rounded-xl border bg-card">
                <table class="w-full text-sm">
                    <tbody>
                        <tr v-if="report.activity.data.length === 0">
                            <td
                                class="px-4 py-6 text-center text-muted-foreground"
                            >
                                {{ $t('No activity in this period.') }}
                            </td>
                        </tr>
                        <tr
                            v-for="entry in report.activity.data"
                            :key="entry.id"
                            class="border-b last:border-0"
                        >
                            <td
                                class="px-4 py-3 whitespace-nowrap text-muted-foreground tabular-nums"
                            >
                                {{ formatDate(entry.at) }}
                            </td>
                            <td class="px-4 py-3">
                                <div>{{ entry.description }}</div>
                                <div class="text-xs text-muted-foreground">
                                    {{
                                        [entry.causer, entry.subject]
                                            .filter(Boolean)
                                            .join(' · ')
                                    }}
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <div
                v-if="report.activity.meta.last_page > 1"
                class="flex justify-end gap-2"
            >
                <Button
                    variant="outline"
                    size="sm"
                    :disabled="report.activity.meta.current_page <= 1"
                    @click="apply(report.activity.meta.current_page - 1)"
                    >{{ $t('Previous') }}</Button
                >
                <Button
                    variant="outline"
                    size="sm"
                    :disabled="
                        report.activity.meta.current_page >=
                        report.activity.meta.last_page
                    "
                    @click="apply(report.activity.meta.current_page + 1)"
                    >{{ $t('Next') }}</Button
                >
            </div>
        </section>
    </div>
</template>
