import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

export type PublicMetaTags = {
    title: string;
    description: string;
    image: string;
};

/**
 * Locale-prefixed links for the public site: url('/teams') → '/ar/teams'.
 */
export function usePublicSite() {
    const page = usePage();
    const locale = computed(() =>
        (page.props.locale as string) === 'en' ? 'en' : 'ar',
    );

    const url = (path = ''): string =>
        `/${locale.value}${path === '' || path === '/' ? '' : path}`;

    const otherLocaleUrl = computed(() => {
        const other = locale.value === 'ar' ? 'en' : 'ar';

        return page.url.replace(/^\/(en|ar)(?=\/|$|\?)/, `/${other}`);
    });

    return { locale, url, otherLocaleUrl };
}
