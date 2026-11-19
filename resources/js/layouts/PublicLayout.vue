<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import DirectionProvider from '@/components/app/DirectionProvider.vue';
import MadeBy from '@/components/MadeBy.vue';
import { usePublicSite } from '@/composables/usePublicSite';
import '../../css/public.css';

/**
 * The public website shell, ported from the old site's layout: header with
 * a mobile menu, language switch and footer. Public pages are always light.
 */
const page = usePage();
const { locale, url, otherLocaleUrl } = usePublicSite();

const navigation = [
    ['about', '/about'],
    ['teams', '/teams'],
    ['projects', '/projects'],
    ['events', '/events'],
    ['news', '/news'],
    ['contact', '/contact'],
] as const;

const footerLinks = [
    ...navigation,
    ['get_involved', '/get-involved'],
    ['faq', '/faq'],
    ['privacy', '/privacy'],
] as const;

const currentPath = computed(() => page.url.split('?')[0]);

function ariaCurrent(path: string): 'page' | 'true' | undefined {
    const target = url(path);

    if (currentPath.value === target) {
        return 'page';
    }

    return currentPath.value.startsWith(`${target}/`) ? 'true' : undefined;
}

const menuOpen = ref(false);
const scrolled = ref(false);
const year = new Date().getFullYear();

const onScroll = () => (scrolled.value = window.scrollY > 8);
const onKey = (event: KeyboardEvent) => {
    if (event.key === 'Escape') {
        menuOpen.value = false;
    }
};

let wasDark = false;

onMounted(() => {
    wasDark = document.documentElement.classList.contains('dark');
    document.documentElement.classList.remove('dark');
    document.documentElement.classList.add('js');
    document.body.classList.add('public-site');
    window.addEventListener('scroll', onScroll, { passive: true });
    window.addEventListener('keydown', onKey);
    onScroll();
});

onBeforeUnmount(() => {
    document.body.classList.remove('public-site');

    if (wasDark) {
        document.documentElement.classList.add('dark');
    }

    window.removeEventListener('scroll', onScroll);
    window.removeEventListener('keydown', onKey);
});

watch(currentPath, () => (menuOpen.value = false));
</script>

<template>
    <DirectionProvider>
        <a class="skip-link" href="#content">{{ $t('public_site.skip') }}</a>
        <header class="public-header" :class="{ 'is-scrolled': scrolled }">
            <div class="public-shell public-header__inner">
                <Link class="public-brand" :href="url()">
                    <img
                        src="/assets/img/brand/logo.png"
                        width="42"
                        height="42"
                        alt=""
                    />
                    <span
                        ><b>Digital</b
                        ><small>{{ $t('public_site.foundation') }}</small></span
                    >
                </Link>
                <button
                    class="public-nav-toggle"
                    type="button"
                    aria-controls="public-navigation"
                    :aria-expanded="menuOpen ? 'true' : 'false'"
                    :aria-label="
                        menuOpen
                            ? $t('public_site.close_menu')
                            : $t('public_site.menu')
                    "
                    @click="menuOpen = !menuOpen"
                >
                    <i aria-hidden="true"></i><i aria-hidden="true"></i>
                </button>
                <nav
                    id="public-navigation"
                    class="public-nav"
                    :class="{ 'is-open': menuOpen }"
                    :aria-label="$t('public_site.navigation')"
                >
                    <Link
                        v-for="[label, path] in navigation"
                        :key="label"
                        :href="url(path)"
                        :aria-current="ariaCurrent(path)"
                        >{{ $t(`public_site.${label}`) }}</Link
                    >
                    <a
                        class="locale"
                        :href="otherLocaleUrl"
                        :lang="locale === 'ar' ? 'en' : 'ar'"
                        :dir="locale === 'ar' ? 'ltr' : 'rtl'"
                        data-test="switch-locale"
                        >{{ locale === 'ar' ? 'English' : 'العربية' }}</a
                    >
                    <Link
                        class="nav-cta"
                        :href="url('/get-involved')"
                        :aria-current="ariaCurrent('/get-involved')"
                        >{{ $t('public_site.get_involved') }}</Link
                    >
                </nav>
            </div>
        </header>

        <main id="content" tabindex="-1">
            <slot />
        </main>

        <footer class="public-footer">
            <div class="public-shell footer-grid">
                <div>
                    <Link class="public-brand" :href="url()">
                        <img
                            src="/assets/img/brand/logo.png"
                            width="42"
                            height="42"
                            alt=""
                        />
                        <span
                            ><b>Digital</b
                            ><small>{{
                                $t('public_site.foundation')
                            }}</small></span
                        >
                    </Link>
                    <p>{{ $t('public_site.home_description') }}</p>
                </div>
                <nav :aria-label="$t('public_site.navigation')">
                    <Link
                        v-for="[label, path] in footerLinks"
                        :key="label"
                        :href="url(path)"
                        >{{ $t(`public_site.${label}`) }}</Link
                    >
                </nav>
            </div>
            <div class="footer-bottom public-shell">
                <span>© {{ year }} {{ $t('public_site.brand') }}</span>
                <MadeBy />
                <a
                    :href="otherLocaleUrl"
                    :lang="locale === 'ar' ? 'en' : 'ar'"
                    >{{ locale === 'ar' ? 'English' : 'العربية' }}</a
                >
            </div>
        </footer>
    </DirectionProvider>
</template>
