<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { trans } from 'laravel-vue-i18n';
import { h } from 'vue';
import DataTable from '@/components/data-table/DataTable.vue';
import Heading from '@/components/Heading.vue';
import { Badge } from '@/components/ui/badge';
import { Input } from '@/components/ui/input';
import { formatDate } from '@/lib/i18n';
import { dashboard } from '@/routes/console';
import { index } from '@/routes/console/audit';
import type { ListColumn, TableResult } from '@/types/table';

type Entry = {
    id: number;
    description: string;
    event: string | null;
    log: string | null;
    causer: string | null;
    impersonated: boolean;
    subject: string | null;
    at: string | null;
    properties: Record<string, unknown>;
};

defineProps<{
    entries: TableResult<Entry>;
    logs: string[];
    subjects: { value: string; label: string }[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Organisation console', href: dashboard() },
            { title: 'Audit log', href: index() },
        ],
    },
});

const filterClass =
    'h-9 rounded-md border border-input bg-transparent px-3 text-sm shadow-xs';

const columns: ListColumn<Entry>[] = [
    {
        id: 'created_at',
        header: () => trans('When'),
        meta: { sortable: true },
        cell: ({ row }) =>
            h(
                'span',
                {
                    class: 'whitespace-nowrap text-muted-foreground tabular-nums',
                },
                formatDate(row.original.at, {
                    day: 'numeric',
                    month: 'short',
                    year: 'numeric',
                    hour: '2-digit',
                    minute: '2-digit',
                }),
            ),
    },
    {
        id: 'description',
        header: () => trans('What'),
        cell: ({ row }) =>
            h('div', { class: 'grid gap-0.5' }, [
                h('span', row.original.description),
                h(
                    'span',
                    { class: 'text-xs text-muted-foreground', dir: 'ltr' },
                    [row.original.event, row.original.subject]
                        .filter(Boolean)
                        .join(' · '),
                ),
                Object.keys(row.original.properties).length > 0 &&
                    h(
                        'code',
                        {
                            class: 'text-xs break-all text-muted-foreground',
                            dir: 'ltr',
                        },
                        JSON.stringify(row.original.properties),
                    ),
            ]),
    },
    {
        id: 'causer',
        header: () => trans('Who'),
        cell: ({ row }) =>
            h('div', { class: 'flex flex-wrap items-center gap-2' }, [
                h('span', row.original.causer ?? trans('System')),
                row.original.impersonated &&
                    h(Badge, { variant: 'destructive' }, () =>
                        trans('Impersonated'),
                    ),
            ]),
    },
    {
        id: 'log',
        header: () => trans('Area'),
        cell: ({ row }) =>
            h(Badge, { variant: 'outline' }, () => row.original.log ?? '—'),
    },
];
</script>

<template>
    <Head :title="$t('Audit log')" />

    <div class="flex flex-col gap-6 p-4 md:p-6">
        <Heading
            :title="$t('Audit log')"
            :description="
                $t(
                    'Everything recorded across the platform, including actions taken while acting as another user.',
                )
            "
        />

        <DataTable
            :columns="columns"
            :rows="entries"
            :search-placeholder="$t('Search descriptions and events')"
            :empty-text="$t('Nothing recorded.')"
            :only="['entries']"
        >
            <template #filters="{ filter }">
                <select
                    v-model="filter.log"
                    :class="filterClass"
                    :aria-label="$t('Area')"
                >
                    <option :value="undefined">{{ $t('All areas') }}</option>
                    <option v-for="log in logs" :key="log" :value="log">
                        {{ log }}
                    </option>
                </select>
                <select
                    v-model="filter.subject"
                    :class="filterClass"
                    :aria-label="$t('Record type')"
                >
                    <option :value="undefined">{{ $t('All records') }}</option>
                    <option
                        v-for="subject in subjects"
                        :key="subject.value"
                        :value="subject.value"
                    >
                        {{ subject.label }}
                    </option>
                </select>
                <select
                    v-model="filter.impersonated"
                    :class="filterClass"
                    :aria-label="$t('Impersonated')"
                >
                    <option :value="undefined">{{ $t('Everyone') }}</option>
                    <option value="1">
                        {{ $t('Only while impersonating') }}
                    </option>
                </select>
                <Input
                    v-model="filter.from"
                    type="date"
                    dir="ltr"
                    class="w-40"
                    :aria-label="$t('From')"
                />
                <Input
                    v-model="filter.to"
                    type="date"
                    dir="ltr"
                    class="w-40"
                    :aria-label="$t('To')"
                />
            </template>
        </DataTable>
    </div>
</template>
