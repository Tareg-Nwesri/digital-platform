<script setup lang="ts">
import FormOption from '@/components/forms/FormOption.vue';
import FormSelect from '@/components/forms/FormSelect.vue';
import { Head, Link } from '@inertiajs/vue3';
import { Plus } from '@lucide/vue';
import { trans } from 'laravel-vue-i18n';
import { h, ref } from 'vue';
import DataTable from '@/components/data-table/DataTable.vue';
import EventDetailsForm from '@/components/events/EventDetailsForm.vue';
import EventStatusBadge from '@/components/events/EventStatusBadge.vue';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { formatNumber, isolateLtr } from '@/lib/i18n';
import { formatZoned } from '@/lib/zonedDate';
import { dashboard } from '@/routes/console';
import { index, show } from '@/routes/console/events';
import type { ListColumn, TableResult } from '@/types/table';

type EventRow = {
    id: number;
    slug: string;
    title: string;
    department: string;
    status: string;
    visibility: string;
    startsAt: string;
    city: string | null;
    capacity: number | null;
    approved: number;
    pending: number;
    waitlisted: number;
};

type Option = { id: number; slug: string; name: string };

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Organisation console', href: dashboard() },
            { title: 'Events', href: index() },
        ],
    },
});

defineProps<{
    events: TableResult<EventRow>;
    filters: { statuses: string[]; departments: Option[] };
    editableDepartments: Option[];
    visibilities: string[];
    can: { create: boolean };
}>();

const creating = ref(false);

const columns: ListColumn<EventRow>[] = [
    {
        id: 'title_en',
        header: () => trans('Event'),
        meta: { sortable: true },
        cell: ({ row }) =>
            h('div', { class: 'grid' }, [
                h(
                    Link,
                    {
                        href: show(row.original.slug).url,
                        class: 'font-medium text-foreground hover:underline',
                    },
                    () => row.original.title,
                ),
                h(
                    'span',
                    { class: 'text-xs text-muted-foreground' },
                    [
                        row.original.department,
                        row.original.visibility === 'invite_only'
                            ? trans('events.visibility.invite_only')
                            : null,
                    ]
                        .filter(Boolean)
                        .join(' · '),
                ),
            ]),
    },
    {
        id: 'status',
        header: () => trans('Status'),
        cell: ({ row }) => h(EventStatusBadge, { status: row.original.status }),
    },
    {
        id: 'starts_at',
        header: () => trans('Starts'),
        meta: { sortable: true },
        cell: ({ row }) =>
            h('div', { class: 'grid text-sm tabular-nums' }, [
                formatZoned(row.original.startsAt, 'Africa/Tripoli', false),
                row.original.city &&
                    h(
                        'span',
                        { class: 'text-xs text-muted-foreground' },
                        row.original.city,
                    ),
            ]),
    },
    {
        id: 'entries',
        header: () => trans('Entries'),
        cell: ({ row }) =>
            h('div', { class: 'flex flex-wrap gap-x-3 text-sm tabular-nums' }, [
                h(
                    'span',
                    {
                        class:
                            row.original.capacity !== null &&
                            row.original.approved >= row.original.capacity
                                ? 'font-semibold text-destructive'
                                : '',
                    },
                    row.original.capacity === null
                        ? trans(':count approved', {
                              count: formatNumber(row.original.approved),
                          })
                        : isolateLtr(
                              `${formatNumber(row.original.approved)} / ${formatNumber(row.original.capacity)}`,
                          ),
                ),
                row.original.pending > 0 &&
                    h(
                        'span',
                        { class: 'text-muted-foreground' },
                        trans(':count pending', {
                            count: formatNumber(row.original.pending),
                        }),
                    ),
                row.original.waitlisted > 0 &&
                    h(
                        'span',
                        { class: 'text-muted-foreground' },
                        trans(':count waitlisted', {
                            count: formatNumber(row.original.waitlisted),
                        }),
                    ),
            ]),
    },
];
</script>

<template>
    <Head :title="$t('Events')" />

    <div class="flex flex-col gap-6 p-4 md:p-6">
        <Heading
            :title="$t('Events')"
            :description="
                $t(
                    'Events your departments organise, their entries and results.',
                )
            "
        />

        <DataTable
            tour="events"
            :columns="columns"
            :rows="events"
            :search-placeholder="$t('Search events')"
            :empty-text="$t('No events match.')"
            :only="['events']"
        >
            <template #filters="{ filter }">
                <FormSelect
                    v-model="filter.status"
                    :aria-label="$t('Status')"
                    data-test="filter-status"
                >
                    <FormOption :value="undefined">{{
                        $t('All statuses')
                    }}</FormOption>
                    <FormOption
                        v-for="status in filters.statuses"
                        :key="status"
                        :value="status"
                    >
                        {{ $t(`events.status.${status}`) }}
                    </FormOption>
                </FormSelect>
                <FormSelect
                    v-if="filters.departments.length > 1"
                    v-model="filter.department"
                    :aria-label="$t('Organised by')"
                >
                    <FormOption :value="undefined">
                        {{ $t('All departments') }}
                    </FormOption>
                    <FormOption
                        v-for="department in filters.departments"
                        :key="department.slug"
                        :value="department.slug"
                    >
                        {{ department.name }}
                    </FormOption>
                </FormSelect>
            </template>

            <template v-if="can.create" #actions>
                <Dialog v-model:open="creating">
                    <DialogTrigger as-child>
                        <Button data-test="new-event">
                            <Plus class="size-4" />
                            {{ $t('New event') }}
                        </Button>
                    </DialogTrigger>
                    <DialogContent
                        class="max-h-[90vh] overflow-y-auto sm:max-w-2xl"
                    >
                        <DialogHeader>
                            <DialogTitle>{{ $t('New event') }}</DialogTitle>
                            <DialogDescription>
                                {{
                                    $t(
                                        'The event starts as a draft. Add categories, then open registration.',
                                    )
                                }}
                            </DialogDescription>
                        </DialogHeader>
                        <EventDetailsForm
                            :departments="editableDepartments"
                            :visibilities="visibilities"
                            @saved="creating = false"
                        />
                    </DialogContent>
                </Dialog>
            </template>
        </DataTable>
    </div>
</template>
