<script setup lang="ts" generic="T extends RowData">
import {
    ArrowDown,
    ArrowUp,
    ArrowUpDown,
    ChevronLeft,
    ChevronRight,
    Inbox,
    Search,
} from '@lucide/vue';
import { FlexRender, useTable } from '@tanstack/vue-table';
import type { RowData } from '@tanstack/vue-table';
import { computed, shallowRef, watch } from 'vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { useTableQuery } from '@/composables/useTableQuery';
import { formatNumber, isolateLtr } from '@/lib/i18n';
import { tableFeaturesForLists } from '@/types/table';
import type { ListColumn, TableResult } from '@/types/table';

/**
 * Server-driven table (LLD 10 §5): sorting, search, filters and paging are
 * done by TableQuery on the server; this component only renders and edits
 * the URL state. Columns with meta.sortable get a sort toggle, and columns
 * with meta.align "end" (amounts, counts) line up on the reading-end side.
 */
const props = withDefaults(
    defineProps<{
        columns: ListColumn<T>[];
        rows: TableResult<T>;
        searchPlaceholder?: string;
        emptyText?: string;
        only?: string[];
        searchable?: boolean;
        /** Names this table for the page guide: adds data-tour="<tour>-search|filters|actions|table". */
        tour?: string;
    }>(),
    { searchable: true },
);

const { state, visit, toggleSort } = useTableQuery(
    props.rows.query,
    props.only,
);

const rowData = shallowRef<T[]>(props.rows.data);

watch(
    () => props.rows.data,
    (rows) => (rowData.value = rows),
);

const table = useTable<typeof tableFeaturesForLists, T>({
    features: tableFeaturesForLists,
    columns: props.columns,
    data: rowData,
});

const meta = computed(() => props.rows.meta);

/** "1–10" stays in reading order inside an Arabic sentence. */
const range = computed(() =>
    isolateLtr(
        `${formatNumber(meta.value.from ?? 0)}–${formatNumber(meta.value.to ?? 0)}`,
    ),
);

function alignEnd(column: { columnDef: { meta?: unknown } }): boolean {
    return (
        (column.columnDef.meta as { align?: string } | undefined)?.align ===
        'end'
    );
}

function sortKey(column: {
    id: string;
    columnDef: { meta?: unknown };
}): string | null {
    const columnMeta = column.columnDef.meta as
        | { sortable?: boolean | string }
        | undefined;

    if (!columnMeta?.sortable) {
        return null;
    }

    return typeof columnMeta.sortable === 'string'
        ? columnMeta.sortable
        : column.id;
}

function ariaSort(column: {
    id: string;
    columnDef: { meta?: unknown };
}): 'ascending' | 'descending' | undefined {
    const key = sortKey(column);

    if (!key || state.sort !== key) {
        return undefined;
    }

    return state.direction === 'asc' ? 'ascending' : 'descending';
}
</script>

<template>
    <div class="grid gap-3" data-test="data-table">
        <div class="flex flex-wrap items-center gap-2">
            <div
                v-if="searchable"
                class="relative w-full sm:max-w-xs"
                :data-tour="tour ? `${tour}-search` : undefined"
            >
                <Search
                    class="pointer-events-none absolute start-2.5 top-1/2 size-4 -translate-y-1/2 text-muted-foreground"
                />
                <Input
                    v-model="state.q"
                    type="search"
                    class="bg-card ps-8"
                    :placeholder="searchPlaceholder ?? $t('Search')"
                    :aria-label="searchPlaceholder ?? $t('Search')"
                    data-test="data-table-search"
                />
            </div>
            <div
                v-if="$slots.filters"
                class="flex flex-wrap items-center gap-2"
                :data-tour="tour ? `${tour}-filters` : undefined"
            >
                <slot name="filters" :filter="state.filter" />
            </div>
            <div
                class="ms-auto flex items-center gap-2"
                :data-tour="
                    tour && $slots.actions ? `${tour}-actions` : undefined
                "
            >
                <slot name="actions" />
            </div>
        </div>

        <div
            class="overflow-x-auto rounded-xl border bg-card shadow-xs"
            :data-tour="tour ? `${tour}-table` : undefined"
        >
            <table class="w-full text-sm">
                <thead
                    class="border-b bg-muted/50 text-xs text-muted-foreground"
                >
                    <tr
                        v-for="headerGroup in table.getHeaderGroups()"
                        :key="headerGroup.id"
                    >
                        <th
                            v-for="header in headerGroup.headers"
                            :key="header.id"
                            scope="col"
                            class="h-10 px-4 font-semibold whitespace-nowrap"
                            :class="
                                alignEnd(header.column)
                                    ? 'text-end'
                                    : 'text-start'
                            "
                            :aria-sort="ariaSort(header.column)"
                        >
                            <button
                                v-if="sortKey(header.column)"
                                type="button"
                                class="-mx-1.5 inline-flex items-center gap-1 rounded-md px-1.5 py-1 transition-colors hover:bg-muted hover:text-foreground"
                                :class="
                                    ariaSort(header.column) && 'text-foreground'
                                "
                                @click="toggleSort(sortKey(header.column)!)"
                            >
                                <FlexRender :header="header" />
                                <ArrowUp
                                    v-if="
                                        ariaSort(header.column) === 'ascending'
                                    "
                                    class="size-3.5"
                                />
                                <ArrowDown
                                    v-else-if="ariaSort(header.column)"
                                    class="size-3.5"
                                />
                                <ArrowUpDown
                                    v-else
                                    class="size-3.5 opacity-40"
                                />
                            </button>
                            <FlexRender v-else :header="header" />
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="row in table.getRowModel().rows"
                        :key="row.id"
                        class="border-b transition-colors last:border-0 hover:bg-muted/40"
                    >
                        <td
                            v-for="cell in row.getAllCells()"
                            :key="cell.id"
                            class="px-4 py-3 align-middle"
                            :class="
                                alignEnd(cell.column) &&
                                'text-end whitespace-nowrap tabular-nums'
                            "
                        >
                            <FlexRender :cell="cell" />
                        </td>
                    </tr>
                    <tr v-if="!rows.data.length">
                        <td :colspan="columns.length" class="px-4 py-12">
                            <div
                                class="flex flex-col items-center gap-3 text-center text-muted-foreground"
                                data-test="data-table-empty"
                            >
                                <span
                                    class="flex size-10 items-center justify-center rounded-full bg-muted"
                                >
                                    <Inbox class="size-5" />
                                </span>
                                <p>
                                    {{
                                        emptyText ?? $t('Nothing to show yet.')
                                    }}
                                </p>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div
            v-if="meta.total > 0"
            class="flex flex-wrap items-center justify-between gap-2 text-sm text-muted-foreground"
        >
            <span class="tabular-nums" data-test="data-table-range">{{
                $t('Showing :range of :total', {
                    range,
                    total: formatNumber(meta.total),
                })
            }}</span>
            <div v-if="meta.last_page > 1" class="flex items-center gap-2">
                <Button
                    variant="outline"
                    size="sm"
                    :disabled="meta.current_page <= 1"
                    @click="visit(meta.current_page - 1)"
                >
                    <ChevronLeft class="size-4 rtl:rotate-180" />
                    {{ $t('Previous') }}
                </Button>
                <span class="tabular-nums">{{
                    $t('Page :current of :last', {
                        current: formatNumber(meta.current_page),
                        last: formatNumber(meta.last_page),
                    })
                }}</span>
                <Button
                    variant="outline"
                    size="sm"
                    :disabled="meta.current_page >= meta.last_page"
                    @click="visit(meta.current_page + 1)"
                >
                    {{ $t('Next') }}
                    <ChevronRight class="size-4 rtl:rotate-180" />
                </Button>
            </div>
        </div>
    </div>
</template>
