<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import { Button } from '@/components/ui/button';

defineProps<{
    status: number;
    title: string;
    message: string;
    homeUrl: string;
    homeLabel: string;
}>();

function reload() {
    window.location.reload();
}
</script>

<template>
    <Head :title="title" />

    <main
        class="flex min-h-svh items-center justify-center bg-background p-6"
    >
        <div
            class="w-full max-w-md rounded-2xl border bg-card p-8 text-center shadow-sm"
            data-test="error-page"
        >
            <AppLogoIcon class="mx-auto mb-6 size-12" />
            <p
                class="mx-auto mb-4 w-fit rounded-full bg-brand-soft px-3 py-1 text-sm font-bold text-brand-ink tabular-nums"
            >
                {{ status }}
            </p>
            <h1 class="mb-2 text-2xl font-bold tracking-tight">{{ title }}</h1>
            <p class="mb-8 text-muted-foreground">{{ message }}</p>
            <div class="flex flex-wrap justify-center gap-3">
                <Button v-if="status === 419" variant="outline" @click="reload">
                    {{ $t('Refresh') }}
                </Button>
                <Button as-child>
                    <Link :href="homeUrl">{{ homeLabel }}</Link>
                </Button>
            </div>
        </div>
    </main>
</template>
