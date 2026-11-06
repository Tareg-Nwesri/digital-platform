import { router } from '@inertiajs/vue3';
import { reactive, watch } from 'vue';
import type { TableQueryState } from '@/types/table';

/**
 * Keeps a list's search/sort/filter/page state in the URL and reloads the
 * page's props through Inertia without losing scroll or component state.
 */
export function useTableQuery(initial: TableQueryState, only?: string[]) {
    const state = reactive({
        q: initial.q ?? '',
        sort: initial.sort,
        direction: initial.direction ?? 'asc',
        per_page: initial.per_page,
        filter: { ...initial.filter } as Record<string, string>,
    });

    function visit(page = 1) {
        const query: Record<string, unknown> = {
            q: state.q || undefined,
            sort: state.sort || undefined,
            direction: state.sort ? state.direction : undefined,
            per_page: state.per_page,
            page: page > 1 ? page : undefined,
        };

        for (const [key, value] of Object.entries(state.filter)) {
            if (value !== '' && value !== undefined && value !== null) {
                query[`filter[${key}]`] = value;
            }
        }

        router.get(window.location.pathname, query as never, {
            preserveState: true,
            preserveScroll: true,
            replace: true,
            only,
        });
    }

    let timer: ReturnType<typeof setTimeout> | undefined;

    watch(
        () => state.q,
        () => {
            clearTimeout(timer);
            timer = setTimeout(() => visit(1), 300);
        },
    );

    watch(
        () => [state.per_page, JSON.stringify(state.filter)],
        () => visit(1),
    );

    function toggleSort(column: string) {
        if (state.sort === column) {
            state.direction = state.direction === 'asc' ? 'desc' : 'asc';
        } else {
            state.sort = column;
            state.direction = 'asc';
        }

        visit(1);
    }

    return { state, visit, toggleSort };
}
