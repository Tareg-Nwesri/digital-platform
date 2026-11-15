<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { trans } from 'laravel-vue-i18n';
import { computed, ref } from 'vue';
import EntryPicker from '@/components/events/EntryPicker.vue';
import type {
    PickerCategory,
    PickerMember,
    PickerRobot,
} from '@/components/events/EntryPicker.vue';
import EventStatusBadge from '@/components/events/EventStatusBadge.vue';
import InputError from '@/components/InputError.vue';
import EventDocuments from '@/components/public/EventDocuments.vue';
import EventFacts from '@/components/public/EventFacts.vue';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { accept, withdraw } from '@/routes/workspace/entries';
import { apply } from '@/routes/workspace/events';

type Entry = {
    id: number;
    status: string;
    categoryId: number;
    category: string;
    waitlistPosition: number | null;
    decisionNote: string | null;
    canWithdraw: boolean;
    members: string[];
    robots: string[];
};

const props = defineProps<{
    team: { slug: string; name: string };
    event: {
        slug: string;
        title: string;
        venue: string | null;
        city: string | null;
        timezone: string;
        status: string;
        startsAt: string;
        registrationClosesAt: string;
        isOpen: boolean;
        cover: string | null;
        description: string | null;
        rules: string | null;
        documents: {
            id: number;
            name: string;
            extension: string;
            size: number;
            url: string;
        }[];
        categories: PickerCategory[];
    };
    entries: Entry[];
    members: PickerMember[];
    robots: PickerRobot[];
    can: { apply: boolean };
}>();

const form = useForm({
    category_id:
        props.event.categories.length === 1
            ? props.event.categories[0].id
            : (null as number | null),
    member_ids: [] as number[],
    robot_ids: [] as number[],
});

const category = computed(
    () => props.event.categories.find((c) => c.id === form.category_id) ?? null,
);

function submit(): void {
    form.post(apply([props.team.slug, props.event.slug]).url, {
        preserveScroll: true,
        onSuccess: () => form.reset(),
    });
}

const accepting = ref<Entry | null>(null);
const acceptForm = useForm({
    member_ids: [] as number[],
    robot_ids: [] as number[],
});
const acceptCategory = computed(
    () =>
        props.event.categories.find(
            (c) => c.id === accepting.value?.categoryId,
        ) ?? null,
);

function submitAccept(): void {
    if (!accepting.value) {
        return;
    }

    acceptForm.post(accept([props.team.slug, accepting.value.id]).url, {
        preserveScroll: true,
        onSuccess: () => {
            accepting.value = null;
            acceptForm.reset();
        },
    });
}

function withdrawEntry(entry: Entry): void {
    if (window.confirm(trans('Withdraw this entry?'))) {
        router.post(
            withdraw([props.team.slug, entry.id]).url,
            {},
            { preserveScroll: true },
        );
    }
}

const selectClass =
    'h-9 w-full rounded-md border border-input bg-transparent px-3 text-sm shadow-xs';
</script>

<template>
    <Head :title="event.title" />

    <div class="grid gap-6 p-4 md:p-6 xl:grid-cols-[minmax(0,1fr)_22rem]">
        <div class="grid content-start gap-6">
            <header
                class="grid gap-3 overflow-hidden rounded-xl border bg-card"
            >
                <img
                    v-if="event.cover"
                    :src="event.cover"
                    alt=""
                    class="aspect-[40/21] w-full object-cover sm:aspect-[5/2]"
                />
                <div class="grid gap-3 p-5">
                    <div class="flex flex-wrap items-center gap-2">
                        <h1 class="text-xl font-semibold">{{ event.title }}</h1>
                        <EventStatusBadge :status="event.status" />
                    </div>
                    <EventFacts :event="event" />
                </div>
            </header>

            <section
                v-if="event.description"
                class="rich-text rounded-xl border bg-card p-5"
                v-html="event.description"
            />

            <section
                v-if="event.categories.length"
                class="grid gap-3 rounded-xl border bg-card p-5"
            >
                <h2 class="font-semibold">{{ $t('Categories') }}</h2>
                <ul class="grid gap-2 sm:grid-cols-2">
                    <li
                        v-for="option in event.categories"
                        :key="option.id"
                        class="grid gap-1 rounded-lg border p-3 text-sm"
                    >
                        <span class="font-medium">{{ option.name }}</span>
                        <span
                            v-if="
                                option.ageMin !== null || option.ageMax !== null
                            "
                            class="text-muted-foreground"
                            >{{
                                $t('Ages :min–:max', {
                                    min: String(option.ageMin ?? 0),
                                    max: String(option.ageMax ?? 99),
                                })
                            }}</span
                        >
                        <span class="text-muted-foreground">{{
                            $t(':min–:max members per team', {
                                min: String(option.membersMin),
                                max: String(option.membersMax),
                            })
                        }}</span>
                    </li>
                </ul>
            </section>

            <section
                v-if="event.rules"
                class="grid gap-3 rounded-xl border bg-card p-5"
            >
                <h2 class="font-semibold">{{ $t('Rules') }}</h2>
                <div class="rich-text" v-html="event.rules" />
            </section>

            <EventDocuments :documents="event.documents" />
        </div>

        <aside class="grid content-start gap-4">
            <section class="grid gap-3 rounded-xl border bg-card p-5">
                <h2 class="font-semibold">{{ $t('Your entries') }}</h2>
                <p
                    v-if="entries.length === 0"
                    class="text-sm text-muted-foreground"
                >
                    {{ $t('Your team has not entered this event yet.') }}
                </p>
                <article
                    v-for="entry in entries"
                    :key="entry.id"
                    class="grid gap-2 rounded-lg border p-3 text-sm"
                    :data-test="`entry-${entry.id}`"
                >
                    <div
                        class="flex flex-wrap items-center justify-between gap-2"
                    >
                        <span class="font-medium">{{ entry.category }}</span>
                        <EventStatusBadge
                            :status="entry.status"
                            kind="registration"
                        />
                    </div>
                    <p
                        v-if="entry.members.length"
                        class="text-muted-foreground"
                    >
                        {{ entry.members.join(', ') }}
                    </p>
                    <p v-if="entry.robots.length" class="text-muted-foreground">
                        {{ $t('Robot') }}: {{ entry.robots.join(', ') }}
                    </p>
                    <p v-if="entry.decisionNote" class="text-destructive">
                        {{ entry.decisionNote }}
                    </p>
                    <div v-if="can.apply" class="flex flex-wrap gap-2">
                        <Button
                            v-if="entry.status === 'invited'"
                            size="sm"
                            @click="accepting = entry"
                            >{{ $t('Accept invitation') }}</Button
                        >
                        <Button
                            v-if="entry.canWithdraw"
                            size="sm"
                            variant="ghost"
                            class="text-destructive"
                            @click="withdrawEntry(entry)"
                            >{{ $t('Withdraw') }}</Button
                        >
                    </div>
                </article>
            </section>

            <section
                v-if="accepting"
                class="grid gap-4 rounded-xl border bg-card p-5"
                data-test="accept-form"
            >
                <h2 class="font-semibold">
                    {{ $t('Accept invitation') }} · {{ accepting.category }}
                </h2>
                <EntryPicker
                    v-model:member-ids="acceptForm.member_ids"
                    v-model:robot-ids="acceptForm.robot_ids"
                    :category="acceptCategory"
                    :members="members"
                    :robots="robots"
                    :errors="acceptForm.errors"
                />
                <InputError :message="(acceptForm.errors as Record<string, string | undefined>).registration" />
                <div class="flex justify-end gap-2">
                    <Button variant="outline" @click="accepting = null">{{
                        $t('Cancel')
                    }}</Button>
                    <Button
                        :disabled="acceptForm.processing"
                        @click="submitAccept"
                        >{{ $t('Accept invitation') }}</Button
                    >
                </div>
            </section>

            <form
                v-if="can.apply && event.isOpen && event.categories.length"
                class="grid gap-4 rounded-xl border bg-card p-5"
                data-test="apply-form"
                @submit.prevent="submit"
            >
                <h2 class="font-semibold">{{ $t('Apply to this event') }}</h2>
                <div class="grid gap-2">
                    <Label for="entry-category">{{ $t('Category') }}</Label>
                    <select
                        id="entry-category"
                        v-model="form.category_id"
                        required
                        :class="selectClass"
                    >
                        <option :value="null" disabled>
                            {{ $t('Choose a category') }}
                        </option>
                        <option
                            v-for="option in event.categories"
                            :key="option.id"
                            :value="option.id"
                        >
                            {{ option.name }}
                        </option>
                    </select>
                    <InputError :message="form.errors.category_id" />
                </div>
                <EntryPicker
                    v-model:member-ids="form.member_ids"
                    v-model:robot-ids="form.robot_ids"
                    :category="category"
                    :members="members"
                    :robots="robots"
                    :errors="form.errors"
                />
                <InputError :message="(form.errors as Record<string, string | undefined>).registration" />
                <Button
                    type="submit"
                    :disabled="form.processing"
                    data-test="apply"
                    >{{ $t('Send application') }}</Button
                >
            </form>
            <p
                v-else-if="can.apply && !event.isOpen"
                class="rounded-xl border bg-card p-5 text-sm text-muted-foreground"
            >
                {{ $t('Registration for this event is not open right now.') }}
            </p>
        </aside>
    </div>
</template>
