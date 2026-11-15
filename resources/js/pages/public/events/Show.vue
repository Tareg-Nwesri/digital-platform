<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import EventDocuments from '@/components/public/EventDocuments.vue';
import EventFacts from '@/components/public/EventFacts.vue';
import { Button } from '@/components/ui/button';

export type PublicEvent = {
    slug: string;
    title: string;
    venue: string | null;
    city: string | null;
    timezone: string;
    startsAt: string;
    endsAt: string;
    registrationOpensAt: string | null;
    registrationClosesAt: string;
    isOpen: boolean;
    cover: string | null;
    documents: {
        id: number;
        name: string;
        extension: string;
        size: number;
        url: string;
    }[];
    description: string | null;
    rules: string | null;
    terms: string | null;
    categories: {
        id: number;
        name: string;
        ageMin: number | null;
        ageMax: number | null;
        membersMin: number;
        membersMax: number;
        robotsPerTeam: number;
    }[];
};

defineProps<{ event: PublicEvent }>();
</script>

<template>
    <Head :title="event.title" />

    <article class="grid gap-6">
        <header class="grid gap-4 overflow-hidden rounded-xl border bg-card">
            <img
                v-if="event.cover"
                :src="event.cover"
                alt=""
                class="aspect-[5/2] w-full object-cover"
                data-test="event-cover"
            />
            <div class="grid gap-4 p-6" :class="{ 'pt-2': event.cover }">
                <h1 class="text-2xl font-bold">{{ event.title }}</h1>
                <EventFacts :event="event" />
                <div v-if="event.isOpen">
                    <Button as-child size="lg" data-test="register-link">
                        <Link
                            :href="`/${$page.props.locale}/events/${event.slug}/register`"
                            >{{ $t('Register your team') }}</Link
                        >
                    </Button>
                </div>
            </div>
        </header>

        <EventDocuments :documents="event.documents" />

        <section
            v-if="event.description"
            class="rich-text rounded-xl border bg-card p-6"
            v-html="event.description"
        />

        <section
            v-if="event.categories.length"
            class="grid gap-3 rounded-xl border bg-card p-6"
        >
            <h2 class="text-lg font-semibold">{{ $t('Categories') }}</h2>
            <ul class="grid gap-2 sm:grid-cols-2">
                <li
                    v-for="category in event.categories"
                    :key="category.id"
                    class="grid gap-1 rounded-lg border p-3 text-sm"
                >
                    <span class="font-medium">{{ category.name }}</span>
                    <span
                        v-if="
                            category.ageMin !== null || category.ageMax !== null
                        "
                        class="text-muted-foreground"
                        >{{
                            $t('Ages :min–:max', {
                                min: String(category.ageMin ?? 0),
                                max: String(category.ageMax ?? 99),
                            })
                        }}</span
                    >
                    <span class="text-muted-foreground">{{
                        $t(':min–:max members per team', {
                            min: String(category.membersMin),
                            max: String(category.membersMax),
                        })
                    }}</span>
                </li>
            </ul>
        </section>

        <section
            v-if="event.rules"
            class="grid gap-3 rounded-xl border bg-card p-6"
        >
            <h2 class="text-lg font-semibold">{{ $t('Rules') }}</h2>
            <div class="rich-text" v-html="event.rules" />
        </section>
    </article>
</template>
