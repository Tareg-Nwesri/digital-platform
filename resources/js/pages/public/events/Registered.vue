<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { CircleCheck } from '@lucide/vue';
import { Button } from '@/components/ui/button';

defineProps<{
    event: { slug: string; title: string };
    teamName: string;
    categoryName: string;
    email: string | null;
}>();
</script>

<template>
    <Head :title="$t('Registration received')" />

    <div
        class="mx-auto grid max-w-lg justify-items-center gap-4 rounded-xl border bg-card p-8 text-center"
        data-test="registration-received"
    >
        <CircleCheck class="size-12 text-green-600" />
        <h1 class="text-2xl font-bold">{{ $t('Registration received') }}</h1>
        <p>
            {{
                $t(':team is registered for :event (:category).', {
                    team: teamName,
                    event: event.title,
                    category: categoryName,
                })
            }}
        </p>
        <p class="text-sm text-muted-foreground">
            {{
                $t(
                    'The organisers will review the registration and contact you to confirm it.',
                )
            }}
        </p>
        <Button as-child variant="outline">
            <Link :href="`/${$page.props.locale}/events/${event.slug}`">{{
                $t('Back to the event')
            }}</Link>
        </Button>
    </div>
</template>
