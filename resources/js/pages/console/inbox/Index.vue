<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { trans } from 'laravel-vue-i18n';
import { h } from 'vue';
import PostStatusBadge from '@/components/content/PostStatusBadge.vue';
import DataTable from '@/components/data-table/DataTable.vue';
import Heading from '@/components/Heading.vue';
import { formatDate } from '@/lib/i18n';
import { dashboard } from '@/routes/console';
import { index, show } from '@/routes/console/inbox';
import type { ListColumn, TableResult } from '@/types/table';

type MessageRow = {
    id: number;
    name: string;
    subject: string;
    status: string;
    department: string | null;
    assignee: string | null;
    createdAt: string | null;
};

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Organisation console', href: dashboard() },
            { title: 'Inbox', href: index() },
        ],
    },
});

defineProps<{
    messages: TableResult<MessageRow>;
    statuses: string[];
}>();

const selectClass =
    'h-9 rounded-md border border-input bg-transparent px-3 text-sm shadow-xs';

const columns: ListColumn<MessageRow>[] = [
    {
        id: 'subject',
        header: () => trans('Message'),
        meta: { sortable: true },
        cell: ({ row }) =>
            h('div', { class: 'grid' }, [
                h(
                    Link,
                    {
                        href: show(row.original.id).url,
                        class: [
                            'text-foreground hover:underline',
                            row.original.status === 'new'
                                ? 'font-semibold'
                                : 'font-medium',
                        ],
                    },
                    () => row.original.subject,
                ),
                h(
                    'span',
                    { class: 'text-xs text-muted-foreground', dir: 'auto' },
                    row.original.name,
                ),
            ]),
    },
    {
        id: 'status',
        header: () => trans('Status'),
        cell: ({ row }) =>
            h(PostStatusBadge, {
                status: row.original.status,
                kind: 'message',
            }),
    },
    {
        id: 'assignee',
        header: () => trans('Assigned to'),
        cell: ({ row }) =>
            row.original.assignee ??
            h('span', { class: 'text-muted-foreground' }, trans('Nobody')),
    },
    {
        id: 'created_at',
        header: () => trans('Received'),
        meta: { sortable: true },
        cell: ({ row }) =>
            h(
                'span',
                { class: 'text-muted-foreground tabular-nums' },
                formatDate(row.original.createdAt),
            ),
    },
];
</script>

<template>
    <Head :title="$t('Inbox')" />

    <div class="flex flex-col gap-6 p-4 md:p-6">
        <Heading
            :title="$t('Inbox')"
            :description="$t('Messages sent through the website contact form.')"
        />

        <DataTable
            :columns="columns"
            :rows="messages"
            :search-placeholder="$t('Search by name, email or subject')"
            :empty-text="$t('No messages.')"
            :only="['messages']"
        >
            <template #filters="{ filter }">
                <select
                    v-model="filter.status"
                    :class="selectClass"
                    :aria-label="$t('Status')"
                >
                    <option :value="undefined">{{ $t('All statuses') }}</option>
                    <option
                        v-for="status in statuses"
                        :key="status"
                        :value="status"
                    >
                        {{ $t(`content.message_status.${status}`) }}
                    </option>
                </select>
                <select
                    v-model="filter.assignee"
                    :class="selectClass"
                    :aria-label="$t('Assigned to')"
                >
                    <option :value="undefined">{{ $t('Anyone') }}</option>
                    <option value="me">{{ $t('Assigned to me') }}</option>
                    <option value="none">{{ $t('Not assigned') }}</option>
                </select>
            </template>
        </DataTable>
    </div>
</template>
