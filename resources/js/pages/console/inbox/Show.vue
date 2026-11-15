<script setup lang="ts">
import FormOption from '@/components/forms/FormOption.vue';
import FormSelect from '@/components/forms/FormSelect.vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import { Mail } from '@lucide/vue';
import { computed } from 'vue';
import PostStatusBadge from '@/components/content/PostStatusBadge.vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { formatDate } from '@/lib/i18n';
import { dashboard } from '@/routes/console';
import { index, notes as notesRoute, update } from '@/routes/console/inbox';

const props = defineProps<{
    message: {
        id: number;
        name: string;
        email: string;
        subject: string;
        body: string;
        locale: string;
        status: string;
        department: string | null;
        assigneeId: number | null;
        assignee: string | null;
        createdAt: string | null;
    };
    notes: {
        id: number;
        body: string;
        author: string | null;
        createdAt: string | null;
    }[];
    statuses: string[];
    assignees: { id: number; name: string }[];
    can: { edit: boolean };
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Organisation console', href: dashboard() },
            { title: 'Inbox', href: index() },
        ],
    },
});

function patch(data: Record<string, string | null>): void {
    router.patch(update(props.message.id).url, data, { preserveScroll: true });
}

const noteForm = useForm({ body: '' });

function addNote(): void {
    noteForm.post(notesRoute(props.message.id).url, {
        preserveScroll: true,
        onSuccess: () => noteForm.reset(),
    });
}

const replyLink = computed(
    () =>
        `mailto:${props.message.email}?subject=${encodeURIComponent(`Re: ${props.message.subject}`)}`,
);
</script>

<template>
    <Head :title="message.subject" />

    <div class="grid gap-6 p-4 md:p-6 lg:grid-cols-[minmax(0,1fr)_18rem]">
        <div class="grid content-start gap-6">
            <div class="flex flex-wrap items-center gap-3">
                <Heading
                    :title="message.subject"
                    :description="`${message.name} · ${formatDate(message.createdAt)}`"
                />
                <PostStatusBadge :status="message.status" kind="message" />
            </div>

            <article class="rounded-xl border bg-card p-5">
                <p
                    class="text-sm whitespace-pre-line"
                    dir="auto"
                    :lang="message.locale"
                >
                    {{ message.body }}
                </p>
            </article>

            <section class="grid gap-3">
                <h3 class="font-semibold">{{ $t('Internal notes') }}</h3>
                <p
                    v-if="notes.length === 0"
                    class="text-sm text-muted-foreground"
                >
                    {{ $t('No notes yet.') }}
                </p>
                <ul class="grid gap-2">
                    <li
                        v-for="note in notes"
                        :key="note.id"
                        class="rounded-lg border bg-card p-3 text-sm"
                    >
                        <p class="whitespace-pre-line" dir="auto">
                            {{ note.body }}
                        </p>
                        <p class="mt-1 text-xs text-muted-foreground">
                            {{ note.author ?? '—' }} ·
                            {{ formatDate(note.createdAt) }}
                        </p>
                    </li>
                </ul>
                <form
                    v-if="can.edit"
                    class="grid gap-2"
                    @submit.prevent="addNote"
                >
                    <Label for="note-body" class="sr-only">{{
                        $t('Add a note')
                    }}</Label>
                    <textarea
                        id="note-body"
                        v-model="noteForm.body"
                        rows="3"
                        maxlength="2000"
                        dir="auto"
                        :placeholder="$t('Add a note for your colleagues')"
                        class="rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs"
                    />
                    <InputError :message="noteForm.errors.body" />
                    <Button
                        type="submit"
                        class="justify-self-end"
                        :disabled="
                            noteForm.processing || noteForm.body.trim() === ''
                        "
                    >
                        {{ $t('Add note') }}
                    </Button>
                </form>
            </section>
        </div>

        <aside class="grid content-start gap-4 rounded-xl border bg-card p-5">
            <dl class="grid gap-1 text-sm">
                <dt class="text-muted-foreground">{{ $t('From') }}</dt>
                <dd dir="auto">{{ message.name }}</dd>
                <dd dir="ltr" class="text-start break-all">
                    {{ message.email }}
                </dd>
            </dl>
            <Button as="a" :href="replyLink" variant="outline">
                <Mail class="size-4" />
                {{ $t('Reply by email') }}
            </Button>

            <template v-if="can.edit">
                <div class="grid gap-2">
                    <Label for="message-status">{{ $t('Status') }}</Label>
                    <FormSelect
                        id="message-status"
                        :model-value="message.status"
                        @change="(value) => patch({ status: String(value) })"
                    >
                        <FormOption
                            v-for="status in statuses"
                            :key="status"
                            :value="status"
                        >
                            {{ $t(`content.message_status.${status}`) }}
                        </FormOption>
                    </FormSelect>
                </div>
                <div class="grid gap-2">
                    <Label for="message-assignee">{{
                        $t('Assigned to')
                    }}</Label>
                    <FormSelect
                        id="message-assignee"
                        :model-value="message.assigneeId ?? ''"
                        @change="
                            (value) =>
                                patch({
                                    assigned_to:
                                        value === '' || value === null
                                            ? null
                                            : String(value),
                                })
                        "
                    >
                        <FormOption value="">{{ $t('Nobody') }}</FormOption>
                        <FormOption
                            v-for="person in assignees"
                            :key="person.id"
                            :value="person.id"
                        >
                            {{ person.name }}
                        </FormOption>
                    </FormSelect>
                </div>
            </template>
            <p v-else class="text-sm text-muted-foreground">
                {{ $t('Assigned to') }}: {{ message.assignee ?? $t('Nobody') }}
            </p>
        </aside>
    </div>
</template>
