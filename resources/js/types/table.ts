import { tableFeatures } from '@tanstack/vue-table';
import type { ColumnDef, RowData } from '@tanstack/vue-table';

/**
 * The shape returned by App\Support\Tables\TableQuery::paginate().
 */
export type TableQueryState = {
    q: string;
    sort: string | null;
    direction: 'asc' | 'desc';
    per_page: number;
    filter: Record<string, string>;
};

export type TableResult<T> = {
    data: T[];
    meta: {
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
        from: number | null;
        to: number | null;
    };
    query: TableQueryState;
};

/**
 * Lists render with the core features only: TableQuery sorts, filters and
 * paginates on the server, so no client row models are registered.
 */
export const tableFeaturesForLists = tableFeatures({});

/**
 * A column of a server-driven list. Set meta.sortable to true (sort by the
 * column id) or to the server column name that TableQuery allows. Set
 * meta.align to "end" for numbers and amounts, so they line up on the
 * reading-end side in both languages.
 */
export type ListColumn<T extends RowData> = ColumnDef<
    typeof tableFeaturesForLists,
    T,
    any
> & {
    meta?: { sortable?: boolean | string; align?: 'start' | 'end' };
};
