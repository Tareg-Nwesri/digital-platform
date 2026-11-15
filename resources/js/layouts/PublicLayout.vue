<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import AppLogo from '@/components/AppLogo.vue';
import DirectionProvider from '@/components/app/DirectionProvider.vue';

const page = usePage();
const locale = computed(() => (page.props.locale as string) ?? 'ar');

/**
 * The same page in the other language: public URLs start with the locale.
 */
const otherLocaleUrl = computed(() => {
    const other = locale.value === 'ar' ? 'en' : 'ar';

    return page.url.replace(/^\/(en|ar)(?=\/|$|\?)/, `/${other}`);
});
</script>

<template>
    <DirectionProvider>
        <div class="flex min-h-svh flex-col bg-muted/40">
            <header class="border-b bg-background">
                <div
                    class="mx-auto flex h-14 w-full max-w-4xl items-center justify-between gap-4 px-4"
                >
                    <Link
                        :href="`/${locale}/events`"
                        class="flex items-center gap-2"
                    >
                        <AppLogo />
                    </Link>
                    <a
                        :href="otherLocaleUrl"
                        class="text-sm font-medium text-muted-foreground hover:text-foreground"
                        data-test="switch-locale"
                        :lang="locale === 'ar' ? 'en' : 'ar'"
                        >{{ locale === 'ar' ? 'English' : 'العربية' }}</a
                    >
                </div>
            </header>
            <main class="mx-auto w-full max-w-4xl flex-1 px-4 py-6 md:py-10">
                <slot />
            </main>
        </div>
    </DirectionProvider>
</template>
