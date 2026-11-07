import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import type { ComputedRef } from 'vue';

export type Direction = 'rtl' | 'ltr';

/**
 * The text direction of the current page, from the shared `dir` prop
 * (Arabic is right-to-left, and the platform default).
 */
export function useDirection(): {
    dir: ComputedRef<Direction>;
    isRtl: ComputedRef<boolean>;
    start: ComputedRef<'left' | 'right'>;
} {
    const page = usePage();
    const dir = computed<Direction>(() =>
        page.props.dir === 'ltr' ? 'ltr' : 'rtl',
    );

    return {
        dir,
        isRtl: computed(() => dir.value === 'rtl'),
        start: computed(() => (dir.value === 'rtl' ? 'right' : 'left')),
    };
}
