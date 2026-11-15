<script setup lang="ts">
import { router, usePage } from '@inertiajs/vue3';
import { ArrowUp, Plus } from '@lucide/vue';
import { trans } from 'laravel-vue-i18n';
import { computed, ref } from 'vue';
import AddTeamDialog from '@/components/events/AddTeamDialog.vue';
import EventStatusBadge from '@/components/events/EventStatusBadge.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { formatNumber } from '@/lib/i18n';
import { promote } from '@/routes/console/events/waitlist';
import {
    approve,
    reject,
    waitlist,
    withdraw,
} from '@/routes/console/registrations';

export type Registration = {
    id: number;
    status: string;
    source: string;
    team: { id: number; name: string; slug: string };
    city: string | null;
    contact: {
        name: string;
        email: string | null;
        phone: string | null;
    } | null;
    robots: string[];
    category: { id: number; name: string };
    members: {
        user_id: number;
        name: string;
        age: number | null;
        role: string | null;
    }[];
    waitlistPosition: number | null;
    decisionNote: string | null;
    overrideReason: string | null;
    overrideViolations: string[];
    reviewedBy: string | null;
};

const props = defineProps<{
    eventSlug: string;
    registrations: Registration[];
    categories: { id: number; name: string }[];
    teams: { id: number; name: string }[];
    teamMembers?: {
        id: number;
        name: string;
        role: string | null;
        birthdate: string | null;
    }[];
    can: { edit: boolean; review: boolean };
    acceptsEntries: boolean;
}>();

const page = usePage();
const registrationError = computed(
    () =>
        (page.props.errors as Record<string, string | undefined>).registration,
);

const statusFilter = ref<string>('');
const categoryFilter = ref<number | ''>('');
const adding = ref(false);

const counts = computed(() =>
    props.registrations.reduce<Record<string, number>>((all, registration) => {
        all[registration.status] = (all[registration.status] ?? 0) + 1;

        return all;
    }, {}),
);

const visible = computed(() =>
    props.registrations.filter(
        (registration) =>
            (statusFilter.value === '' ||
                registration.status === statusFilter.value) &&
            (categoryFilter.value === '' ||
                registration.category.id === categoryFilter.value),
    ),
);

const hasWaitlist = computed(() => (counts.value.waitlisted ?? 0) > 0);

/* Decisions */
const deciding = ref<{
    registration: Registration;
    action: 'approve' | 'reject';
} | null>(null);
const decisionText = ref('');
const processing = ref(false);

function openDecision(
    registration: Registration,
    action: 'approve' | 'reject',
): void {
    deciding.value = { registration, action };
    decisionText.value = '';
}

const options = {
    preserveScroll: true,
    onStart: () => (processing.value = true),
    onFinish: () => (processing.value = false),
};

function confirmDecision(): void {
    if (!deciding.value) {
        return;
    }

    const { registration, action } = deciding.value;

    if (action === 'approve') {
        router.post(
            approve(registration.id).url,
            { override_reason: decisionText.value || null },
            { ...options, onSuccess: () => (deciding.value = null) },
        );
    } else {
        router.post(
            reject(registration.id).url,
            { reason: decisionText.value },
            { ...options, onSuccess: () => (deciding.value = null) },
        );
    }
}

function quickApprove(registration: Registration): void {
    router.post(approve(registration.id).url, {}, options);
}

function moveToWaitlist(registration: Registration): void {
    router.post(waitlist(registration.id).url, {}, options);
}

function withdrawEntry(registration: Registration): void {
    if (window.confirm(trans('Withdraw this entry?'))) {
        router.post(withdraw(registration.id).url, {}, options);
    }
}

function promoteNext(): void {
    router.post(promote(props.eventSlug).url, {}, options);
}

const canDecide = (registration: Registration) =>
    props.can.review &&
    ['pending', 'invited', 'waitlisted'].includes(registration.status);

const canWithdraw = (registration: Registration) =>
    props.can.edit &&
    ['pending', 'invited', 'approved', 'waitlisted'].includes(
        registration.status,
    );

const selectClass =
    'h-9 rounded-md border border-input bg-transparent px-3 text-sm shadow-xs';

const statuses = [
    'pending',
    'invited',
    'approved',
    'waitlisted',
    'rejected',
    'withdrawn',
];
</script>

<template>
    <div class="grid gap-4">
        <div class="flex flex-wrap items-center gap-2">
            <select
                v-model="statusFilter"
                :class="selectClass"
                :aria-label="$t('Status')"
            >
                <option value="">
                    {{ $t('All entries') }} ({{
                        formatNumber(registrations.length)
                    }})
                </option>
                <option
                    v-for="status in statuses"
                    :key="status"
                    :value="status"
                >
                    {{ $t(`events.registration_status.${status}`) }}
                    ({{ formatNumber(counts[status] ?? 0) }})
                </option>
            </select>
            <select
                v-if="categories.length > 1"
                v-model="categoryFilter"
                :class="selectClass"
                :aria-label="$t('Category')"
            >
                <option value="">{{ $t('All categories') }}</option>
                <option
                    v-for="category in categories"
                    :key="category.id"
                    :value="category.id"
                >
                    {{ category.name }}
                </option>
            </select>

            <div class="ms-auto flex flex-wrap gap-2">
                <Button
                    v-if="can.review && hasWaitlist"
                    variant="outline"
                    :disabled="processing"
                    data-test="promote-waitlist"
                    @click="promoteNext"
                >
                    <ArrowUp class="size-4" />
                    {{ $t('Promote from waitlist') }}
                </Button>
                <Button
                    v-if="can.edit && acceptsEntries && categories.length > 0"
                    data-test="add-team"
                    @click="adding = true"
                >
                    <Plus class="size-4" />
                    {{ $t('Add a team') }}
                </Button>
            </div>
        </div>

        <InputError :message="registrationError" />

        <div class="overflow-x-auto rounded-xl border bg-card">
            <table class="w-full text-sm">
                <thead
                    class="border-b text-start text-xs text-muted-foreground"
                >
                    <tr>
                        <th class="px-4 py-2 text-start font-medium">
                            {{ $t('Team') }}
                        </th>
                        <th class="px-4 py-2 text-start font-medium">
                            {{ $t('Category') }}
                        </th>
                        <th class="px-4 py-2 text-start font-medium">
                            {{ $t('Status') }}
                        </th>
                        <th class="px-4 py-2 text-start font-medium">
                            {{ $t('Members') }}
                        </th>
                        <th class="px-4 py-2">
                            <span class="sr-only">{{ $t('Actions') }}</span>
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-if="visible.length === 0">
                        <td
                            colspan="5"
                            class="px-4 py-8 text-center text-muted-foreground"
                        >
                            {{ $t('No entries yet.') }}
                        </td>
                    </tr>
                    <tr
                        v-for="registration in visible"
                        :key="registration.id"
                        class="border-b align-top last:border-0"
                        :data-test="`registration-${registration.id}`"
                    >
                        <td class="px-4 py-3">
                            <div class="font-medium">
                                {{ registration.team.name }}
                            </div>
                            <div class="text-xs text-muted-foreground">
                                {{
                                    registration.source === 'organiser'
                                        ? $t('Added by organisers')
                                        : registration.source === 'public'
                                          ? $t('Registered on the website')
                                          : $t('Applied')
                                }}
                                <template v-if="registration.city">
                                    · {{ registration.city }}</template
                                >
                            </div>
                            <div
                                v-if="registration.contact"
                                class="mt-1 grid text-xs"
                                data-test="registration-contact"
                            >
                                <span>{{ registration.contact.name }}</span>
                                <a
                                    v-if="registration.contact.phone"
                                    :href="`tel:${registration.contact.phone}`"
                                    dir="ltr"
                                    class="text-start hover:underline"
                                    >{{ registration.contact.phone }}</a
                                >
                                <a
                                    v-if="registration.contact.email"
                                    :href="`mailto:${registration.contact.email}`"
                                    dir="ltr"
                                    class="text-start hover:underline"
                                    >{{ registration.contact.email }}</a
                                >
                            </div>
                            <div
                                v-if="registration.robots.length"
                                class="mt-1 text-xs text-muted-foreground"
                            >
                                {{ $t('Robot') }}:
                                {{ registration.robots.join(', ') }}
                            </div>
                        </td>
                        <td class="px-4 py-3">
                            {{ registration.category.name }}
                        </td>
                        <td class="px-4 py-3">
                            <div class="flex flex-wrap items-center gap-2">
                                <EventStatusBadge
                                    :status="registration.status"
                                    kind="registration"
                                />
                                <span
                                    v-if="registration.waitlistPosition"
                                    class="text-xs text-muted-foreground tabular-nums"
                                    >#{{
                                        formatNumber(
                                            registration.waitlistPosition,
                                        )
                                    }}</span
                                >
                            </div>
                            <p
                                v-if="registration.decisionNote"
                                class="mt-1 text-xs text-muted-foreground"
                            >
                                {{ registration.decisionNote }}
                            </p>
                            <p
                                v-if="registration.overrideReason"
                                class="mt-1 text-xs text-amber-700 dark:text-amber-400"
                            >
                                {{
                                    $t('Rules overridden: :reason', {
                                        reason: registration.overrideReason,
                                    })
                                }}
                            </p>
                        </td>
                        <td class="px-4 py-3">
                            <ul class="grid gap-0.5">
                                <li
                                    v-for="member in registration.members"
                                    :key="member.user_id"
                                >
                                    {{ member.name }}
                                    <span
                                        v-if="member.age !== null"
                                        class="text-xs text-muted-foreground tabular-nums"
                                        >({{ formatNumber(member.age) }})</span
                                    >
                                </li>
                            </ul>
                        </td>
                        <td class="px-4 py-3">
                            <div class="flex flex-wrap justify-end gap-1">
                                <template v-if="canDecide(registration)">
                                    <Button
                                        size="sm"
                                        :disabled="processing"
                                        :data-test="`approve-${registration.id}`"
                                        @click="quickApprove(registration)"
                                    >
                                        {{ $t('Approve') }}
                                    </Button>
                                    <Button
                                        size="sm"
                                        variant="outline"
                                        :disabled="processing"
                                        @click="
                                            openDecision(
                                                registration,
                                                'approve',
                                            )
                                        "
                                    >
                                        {{ $t('Override…') }}
                                    </Button>
                                    <Button
                                        v-if="registration.status === 'pending'"
                                        size="sm"
                                        variant="ghost"
                                        :disabled="processing"
                                        @click="moveToWaitlist(registration)"
                                    >
                                        {{ $t('Waitlist') }}
                                    </Button>
                                    <Button
                                        v-if="registration.status === 'pending'"
                                        size="sm"
                                        variant="ghost"
                                        class="text-destructive"
                                        :disabled="processing"
                                        @click="
                                            openDecision(registration, 'reject')
                                        "
                                    >
                                        {{ $t('Reject') }}
                                    </Button>
                                </template>
                                <Button
                                    v-if="canWithdraw(registration)"
                                    size="sm"
                                    variant="ghost"
                                    :disabled="processing"
                                    @click="withdrawEntry(registration)"
                                >
                                    {{ $t('Withdraw') }}
                                </Button>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <AddTeamDialog
            v-if="can.edit"
            v-model:open="adding"
            :event-slug="eventSlug"
            :categories="categories"
            :teams="teams"
            :team-members="teamMembers"
            :can-override="can.review"
        />

        <Dialog
            :open="deciding !== null"
            @update:open="(open) => !open && (deciding = null)"
        >
            <DialogContent class="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>
                        {{
                            deciding?.action === 'reject'
                                ? $t('Reject :team?', {
                                      team:
                                          deciding?.registration.team.name ??
                                          '',
                                  })
                                : $t('Approve :team despite broken rules?', {
                                      team:
                                          deciding?.registration.team.name ??
                                          '',
                                  })
                        }}
                    </DialogTitle>
                    <DialogDescription>
                        {{
                            deciding?.action === 'reject'
                                ? $t('The team sees this reason.')
                                : $t(
                                      'The reason and the rules it overrides are recorded.',
                                  )
                        }}
                    </DialogDescription>
                </DialogHeader>
                <div class="grid gap-2">
                    <Label for="decision-text">{{ $t('Reason') }}</Label>
                    <Input
                        id="decision-text"
                        v-model="decisionText"
                        maxlength="1000"
                        required
                    />
                    <InputError
                        :message="
                            (
                                page.props.errors as Record<
                                    string,
                                    string | undefined
                                >
                            ).reason ?? registrationError
                        "
                    />
                </div>
                <DialogFooter>
                    <Button variant="outline" @click="deciding = null">
                        {{ $t('Cancel') }}
                    </Button>
                    <Button
                        :variant="
                            deciding?.action === 'reject'
                                ? 'destructive'
                                : 'default'
                        "
                        :disabled="processing || decisionText.trim() === ''"
                        data-test="confirm-decision"
                        @click="confirmDecision"
                    >
                        {{
                            deciding?.action === 'reject'
                                ? $t('Reject')
                                : $t('Approve')
                        }}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </div>
</template>
