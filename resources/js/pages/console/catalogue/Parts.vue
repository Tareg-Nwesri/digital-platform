<script setup lang="ts">
import FormOption from '@/components/forms/FormOption.vue';
import FormSelect from '@/components/forms/FormSelect.vue';
import { Head, Link } from '@inertiajs/vue3';
import { Plus } from '@lucide/vue';
import { trans } from 'laravel-vue-i18n';
import { h, ref } from 'vue';
import CatalogueNav from '@/components/catalogue/CatalogueNav.vue';
import CatalogueStatusBadge from '@/components/catalogue/CatalogueStatusBadge.vue';
import PartForm from '@/components/catalogue/PartForm.vue';
import DataTable from '@/components/data-table/DataTable.vue';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { formatNumber } from '@/lib/i18n';
import { dashboard } from '@/routes/console';
import { index, show } from '@/routes/console/catalogue/parts';
import type { ListColumn, TableResult } from '@/types/table';

type PartRow = {
    id: number;
    name: string;
    partNumber: string | null;
    category: string | null;
    status: string;
    team: string | null;
    offers: number;
    bestPrice: string | null;
};

type CategoryOption = { id: number; slug: string; name: string; depth: number };

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Organisation console', href: dashboard() },
            { title: 'Catalogue', href: index() },
        ],
    },
});

defineProps<{
    parts: TableResult<PartRow>;
    filters: { statuses: string[]; categories: CategoryOption[] };
    categories: CategoryOption[];
    suggestionsCount: number;
    can: { manage: boolean; review: boolean };
}>();

const creating = ref(false);

const columns: ListColumn<PartRow>[] = [
    {
        id: 'name',
        header: () => trans('Part'),
        meta: { sortable: true },
        cell: ({ row }) =>
            h('div', { class: 'grid' }, [
                h(
                    Link,
                    {
                        href: show(row.original.id).url,
                        class: 'font-medium text-foreground hover:underline',
                    },
                    () => row.original.name,
                ),
                row.original.partNumber &&
                    h(
                        'span',
                        { class: 'text-xs text-muted-foreground', dir: 'ltr' },
                        row.original.partNumber,
                    ),
            ]),
    },
    {
        id: 'category',
        header: () => trans('Category'),
        cell: ({ row }) =>
            row.original.category ??
            h('span', { class: 'text-muted-foreground' }, '—'),
    },
    {
        id: 'status',
        header: () => trans('Status'),
        cell: ({ row }) =>
            h('div', { class: 'grid gap-0.5' }, [
                h(CatalogueStatusBadge, { status: row.original.status }),
                row.original.team &&
                    h(
                        'span',
                        { class: 'text-xs text-muted-foreground' },
                        trans('From :team', { team: row.original.team }),
                    ),
            ]),
    },
    {
        id: 'price',
        header: () => trans('Best price'),
        cell: ({ row }) =>
            h('div', { class: 'grid text-sm' }, [
                h(
                    'span',
                    { class: 'tabular-nums', dir: 'ltr' },
                    row.original.bestPrice ?? '—',
                ),
                h(
                    'span',
                    { class: 'text-xs text-muted-foreground' },
                    trans(':count offers', {
                        count: formatNumber(row.original.offers),
                    }),
                ),
            ]),
    },
];
</script>

<template>
    <Head :title="$t('Catalogue')" />

    <div class="flex flex-col gap-6 p-4 md:p-6">
        <Heading
            :title="$t('Catalogue')"
            :description="
                $t(
                    'The parts and vendors every team builds from. Teams suggest additions; you review them.',
                )
            "
        />

        <CatalogueNav current="parts" :suggestions-count="suggestionsCount" />

        <DataTable
            :columns="columns"
            :rows="parts"
            :search-placeholder="$t('Search by name or part number')"
            :empty-text="$t('No parts match.')"
            :only="['parts']"
        >
            <template #filters="{ filter }">
                <FormSelect v-model="filter.status" :aria-label="$t('Status')">
                    <FormOption :value="undefined">{{
                        $t('All statuses')
                    }}</FormOption>
                    <FormOption
                        v-for="status in filters.statuses"
                        :key="status"
                        :value="status"
                    >
                        {{ $t(`catalogue.status.${status}`) }}
                    </FormOption>
                </FormSelect>
                <FormSelect
                    v-if="filters.categories.length > 0"
                    v-model="filter.category"
                    :aria-label="$t('Category')"
                >
                    <FormOption :value="undefined">
                        {{ $t('All categories') }}
                    </FormOption>
                    <FormOption
                        v-for="category in filters.categories"
                        :key="category.slug"
                        :value="category.slug"
                    >
                        {{ category.depth > 0 ? '— ' : '' }}{{ category.name }}
                    </FormOption>
                </FormSelect>
            </template>

            <template v-if="can.manage" #actions>
                <Dialog v-model:open="creating">
                    <DialogTrigger as-child>
                        <Button data-test="new-part">
                            <Plus class="size-4" />
                            {{ $t('New part') }}
                        </Button>
                    </DialogTrigger>
                    <DialogContent
                        class="max-h-[90vh] overflow-y-auto sm:max-w-xl"
                    >
                        <DialogHeader>
                            <DialogTitle>{{ $t('New part') }}</DialogTitle>
                        </DialogHeader>
                        <PartForm
                            :categories="categories"
                            @saved="creating = false"
                        />
                    </DialogContent>
                </Dialog>
            </template>
        </DataTable>
    </div>
</template>
