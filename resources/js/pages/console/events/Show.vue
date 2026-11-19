<script setup lang="ts">
import FormOption from '@/components/forms/FormOption.vue';
import FormSelect from '@/components/forms/FormSelect.vue';
import { Head, router, usePage, setLayoutProps } from '@inertiajs/vue3';
import { X } from '@lucide/vue';
import { trans } from 'laravel-vue-i18n';
import { computed, ref } from 'vue';
import CategoriesEditor from '@/components/events/CategoriesEditor.vue';
import type { Category } from '@/components/events/CategoriesEditor.vue';
import EventDetailsForm from '@/components/events/EventDetailsForm.vue';
import EventMediaPanel from '@/components/events/EventMediaPanel.vue';
import type { EventDocument } from '@/components/events/EventMediaPanel.vue';
import EventStatusBadge from '@/components/events/EventStatusBadge.vue';
import RegistrationsPanel from '@/components/events/RegistrationsPanel.vue';
import type { Registration } from '@/components/events/RegistrationsPanel.vue';
import ResultsEditor from '@/components/events/ResultsEditor.vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { formatDate, formatNumber } from '@/lib/i18n';
import { formatZoned } from '@/lib/zonedDate';
import { dashboard } from '@/routes/console';
import { destroy, index, status as statusRoute } from '@/routes/console/events';
import {
    destroy as uninvite,
    store as invite,
} from '@/routes/console/events/invitations';

type Option = { id: number; slug: string; name: string };

type EventProps = {
    id: number;
    slug: string;
    title: string;
    titleEn: string;
    titleAr: string | null;
    descriptionEn: string | null;
    descriptionAr: string | null;
    venue: string | null;
    city: string | null;
    timezone: string;
    startsAt: string;
    endsAt: string;
    registrationOpensAt: string | null;
    registrationClosesAt: string | null;
    status: string;
    visibility: string;
    capacity: number | null;
    approved: number;
    resultsPublishedAt: string | null;
    cover: string | null;
    documents: EventDocument[];
    department: Option;
};

type ResultRow = {
    placement: number | null;
    score: string | null;
    awardEn: string | null;
    awardAr: string | null;
    notes: string | null;
} | null;

const props = defineProps<{
    event: EventProps;
    transitions: string[];
    categories: Category[];
    registrations: (Registration & { result: ResultRow })[];
    invitations: { id: number; name: string; slug: string }[];
    can: { edit: boolean; review: boolean; delete: boolean };
    editableDepartments: Option[];
    visibilities: string[];
    teamOptions: { id: number; name: string }[];
    teamMembers?: {
        id: number;
        name: string;
        role: string | null;
        birthdate: string | null;
    }[];
    activity: {
        id: number;
        description: string;
        event: string | null;
        causer: string | null;
        at: string | null;
    }[];
}>();

setLayoutProps({
    breadcrumbs: [
        { title: 'Organisation console', href: dashboard() },
        { title: 'Events', href: index() },
        { title: props.event.title },
    ],
});

const page = usePage();
const pageErrors = computed(
    () => page.props.errors as Record<string, string | undefined>,
);

type Tab =
    | 'overview'
    | 'categories'
    | 'entries'
    | 'invitations'
    | 'results'
    | 'settings'
    | 'media'
    | 'activity';

const tabs = computed(() =>
    (
        [
            ['overview', 'Overview', true],
            ['categories', 'Categories', true],
            ['entries', 'Entries', true],
            [
                'invitations',
                'Invitations',
                props.event.visibility === 'invite_only',
            ],
            [
                'results',
                'Results',
                ['finished', 'archived'].includes(props.event.status),
            ],
            ['media', 'Cover & files', true],
            ['settings', 'Settings', props.can.edit],
            ['activity', 'Activity', true],
        ] as [Tab, string, boolean][]
    ).filter(([, , visible]) => visible),
);
const tab = ref<Tab>('overview');

const acceptsEntries = computed(() =>
    ['open', 'closed', 'running'].includes(props.event.status),
);

/* Status */
const changingStatus = ref(false);

function changeStatus(status: string): void {
    router.patch(
        statusRoute(props.event.slug).url,
        { status },
        {
            preserveScroll: true,
            onStart: () => (changingStatus.value = true),
            onFinish: () => (changingStatus.value = false),
        },
    );
}

const transitionLabel: Record<string, string> = {
    open: 'Open registration',
    closed: 'Close registration',
    running: 'Start the event',
    finished: 'Finish the event',
    archived: 'Archive',
};

function deleteEvent(): void {
    if (
        window.confirm(trans('Delete this draft event? This cannot be undone.'))
    ) {
        router.delete(destroy(props.event.slug).url);
    }
}

/* Invitations */
const inviteTeamId = ref<number | ''>('');
const invitedIds = computed(
    () => new Set(props.invitations.map((team) => team.id)),
);
const invitableTeams = computed(() =>
    props.teamOptions.filter((team) => !invitedIds.value.has(team.id)),
);

function inviteTeam(): void {
    if (inviteTeamId.value === '') {
        return;
    }

    router.post(
        invite(props.event.slug).url,
        { team_ids: [inviteTeamId.value] },
        { preserveScroll: true, onSuccess: () => (inviteTeamId.value = '') },
    );
}

function removeInvitation(teamId: number): void {
    router.delete(uninvite([props.event.slug, teamId]).url, {
        preserveScroll: true,
    });
}
</script>

<template>
    <Head :title="event.title" />

    <div class="flex flex-col gap-6 p-4 md:p-6">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div class="flex flex-wrap items-center gap-3">
                <Heading
                    :title="event.title"
                    :description="
                        $t('Organised by :department', {
                            department: event.department.name,
                        })
                    "
                />
                <EventStatusBadge :status="event.status" />
                <Badge
                    v-if="event.visibility === 'invite_only'"
                    variant="outline"
                >
                    {{ $t('events.visibility.invite_only') }}
                </Badge>
            </div>
            <div
                v-if="can.edit"
                class="flex flex-wrap gap-2"
                data-tour="event-lifecycle"
            >
                <Button
                    v-for="next in transitions"
                    :key="next"
                    :variant="next === 'archived' ? 'outline' : 'default'"
                    :disabled="changingStatus"
                    :data-test="`transition-${next}`"
                    @click="changeStatus(next)"
                >
                    {{ $t(transitionLabel[next] ?? next) }}
                </Button>
                <Button
                    v-if="can.delete"
                    variant="ghost"
                    class="text-destructive"
                    @click="deleteEvent"
                >
                    {{ $t('Delete') }}
                </Button>
            </div>
        </div>

        <InputError :message="pageErrors.status ?? pageErrors.event" />

        <nav class="section-tabs" role="tablist" data-tour="event-tabs">
            <button
                v-for="[key, label] in tabs"
                :key="key"
                type="button"
                role="tab"
                :aria-selected="tab === key"
                class="section-tab"
                :data-test="`tab-${key}`"
                @click="tab = key"
            >
                {{ $t(label) }}
            </button>
        </nav>

        <section
            v-if="tab === 'overview'"
            class="grid gap-6 lg:grid-cols-2"
            data-tour="event-overview"
        >
            <div class="rounded-xl border bg-card p-5">
                <h3 class="font-semibold">{{ $t('Details') }}</h3>
                <dl
                    class="mt-3 grid grid-cols-[auto_1fr] gap-x-6 gap-y-2 text-sm"
                >
                    <dt class="text-muted-foreground">{{ $t('Starts') }}</dt>
                    <dd class="tabular-nums">
                        {{ formatZoned(event.startsAt, event.timezone) }}
                    </dd>
                    <dt class="text-muted-foreground">{{ $t('Ends') }}</dt>
                    <dd class="tabular-nums">
                        {{ formatZoned(event.endsAt, event.timezone) }}
                    </dd>
                    <dt class="text-muted-foreground">
                        {{ $t('Registration') }}
                    </dt>
                    <dd class="tabular-nums">
                        {{
                            formatZoned(
                                event.registrationOpensAt,
                                event.timezone,
                            )
                        }}
                        →
                        {{
                            formatZoned(
                                event.registrationClosesAt ?? event.startsAt,
                                event.timezone,
                            )
                        }}
                    </dd>
                    <dt class="text-muted-foreground">{{ $t('Venue') }}</dt>
                    <dd>
                        {{
                            [event.venue, event.city]
                                .filter(Boolean)
                                .join(', ') || '—'
                        }}
                    </dd>
                    <dt class="text-muted-foreground">{{ $t('Timezone') }}</dt>
                    <dd dir="ltr" class="text-start">{{ event.timezone }}</dd>
                </dl>
            </div>
            <div class="rounded-xl border bg-card p-5">
                <h3 class="font-semibold">{{ $t('Entries') }}</h3>
                <p class="mt-3 text-3xl font-semibold tabular-nums">
                    {{ formatNumber(event.approved) }}
                    <span
                        v-if="event.capacity !== null"
                        class="text-base font-normal text-muted-foreground"
                    >
                        / {{ formatNumber(event.capacity) }}
                    </span>
                </p>
                <p class="text-sm text-muted-foreground">
                    {{ $t('approved teams') }}
                </p>
                <ul class="mt-4 grid gap-1 text-sm">
                    <li
                        v-for="category in categories"
                        :key="category.id"
                        class="flex justify-between gap-3"
                    >
                        <span>{{ category.name }}</span>
                        <span class="text-muted-foreground tabular-nums">
                            {{ formatNumber(category.approved)
                            }}<template v-if="category.capacity !== null">
                                /
                                {{ formatNumber(category.capacity) }}</template
                            >
                        </span>
                    </li>
                </ul>
                <p
                    v-if="categories.length === 0"
                    class="mt-4 text-sm text-muted-foreground"
                >
                    {{
                        $t(
                            'Add at least one category before opening the event.',
                        )
                    }}
                </p>
            </div>
        </section>

        <section v-if="tab === 'categories'">
            <CategoriesEditor
                :event-slug="event.slug"
                :categories="categories"
                :editable="can.edit"
            />
        </section>

        <section v-if="tab === 'entries'">
            <RegistrationsPanel
                :event-slug="event.slug"
                :registrations="registrations"
                :categories="
                    categories.map((c) => ({ id: c.id, name: c.name }))
                "
                :teams="teamOptions"
                :team-members="teamMembers"
                :can="can"
                :accepts-entries="acceptsEntries"
            />
        </section>

        <section v-if="tab === 'invitations'" class="grid gap-4">
            <p class="text-sm text-muted-foreground">
                {{ $t('Only invited teams can see this event and apply.') }}
            </p>
            <div v-if="can.edit" class="flex flex-wrap gap-2">
                <FormSelect v-model="inviteTeamId" :aria-label="$t('Team')">
                    <FormOption value="">{{ $t('Choose a team') }}</FormOption>
                    <FormOption
                        v-for="team in invitableTeams"
                        :key="team.id"
                        :value="team.id"
                    >
                        {{ team.name }}
                    </FormOption>
                </FormSelect>
                <Button
                    :disabled="inviteTeamId === ''"
                    data-test="invite-team"
                    @click="inviteTeam"
                >
                    {{ $t('Invite') }}
                </Button>
            </div>
            <ul class="grid gap-2">
                <li
                    v-for="team in invitations"
                    :key="team.id"
                    class="flex items-center justify-between rounded-md border px-3 py-2 text-sm"
                >
                    {{ team.name }}
                    <Button
                        v-if="can.edit"
                        variant="ghost"
                        size="icon"
                        :aria-label="$t('Remove invitation')"
                        @click="removeInvitation(team.id)"
                    >
                        <X class="size-4" />
                    </Button>
                </li>
                <li
                    v-if="invitations.length === 0"
                    class="text-sm text-muted-foreground"
                >
                    {{ $t('No teams invited yet.') }}
                </li>
            </ul>
        </section>

        <section v-if="tab === 'results'">
            <ResultsEditor
                :event-slug="event.slug"
                :registrations="registrations"
                :finished="event.status === 'finished'"
                :published-at="event.resultsPublishedAt"
                :timezone="event.timezone"
                :can="can"
            />
        </section>

        <section
            v-if="tab === 'settings' && can.edit"
            class="max-w-3xl rounded-xl border bg-card p-5"
        >
            <EventDetailsForm
                :event="event"
                :departments="editableDepartments"
                :visibilities="visibilities"
            />
        </section>

        <section v-if="tab === 'media'" class="max-w-3xl">
            <EventMediaPanel
                :slug="event.slug"
                :cover="event.cover"
                :documents="event.documents"
                :can-edit="can.edit"
            />
        </section>

        <section v-if="tab === 'activity'" class="rounded-xl border bg-card">
            <ul class="divide-y text-sm">
                <li
                    v-for="entry in activity"
                    :key="entry.id"
                    class="flex flex-wrap justify-between gap-2 px-4 py-3"
                >
                    <span
                        >{{ entry.description
                        }}<span
                            v-if="entry.causer"
                            class="text-muted-foreground"
                        >
                            · {{ entry.causer }}</span
                        ></span
                    >
                    <span class="text-muted-foreground tabular-nums">{{
                        formatDate(entry.at)
                    }}</span>
                </li>
                <li
                    v-if="activity.length === 0"
                    class="px-4 py-6 text-center text-muted-foreground"
                >
                    {{ $t('No activity yet.') }}
                </li>
            </ul>
        </section>
    </div>
</template>
