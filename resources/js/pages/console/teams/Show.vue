<script setup lang="ts">
import FormOption from '@/components/forms/FormOption.vue';
import FormSelect from '@/components/forms/FormSelect.vue';
import { Form, Head, router, setLayoutProps } from '@inertiajs/vue3';
import { trans } from 'laravel-vue-i18n';
import { computed, reactive, ref, watch } from 'vue';
import TeamStatusBadge from '@/components/app/TeamStatusBadge.vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { formatDate, formatNumber } from '@/lib/i18n';
import { dashboard } from '@/routes/console';
import {
    features as featuresRoute,
    index,
    status as statusRoute,
    tier as tierRoute,
    update,
} from '@/routes/console/teams';

type Option = { id: number; slug: string; name: string };

type Feature = {
    name: string;
    type: 'number' | 'boolean';
    tierValue: boolean | number;
    override: boolean | number | null;
    effective: boolean | number;
};

const props = defineProps<{
    team: {
        id: number;
        slug: string;
        name: string;
        status: string;
        statusReason: string | null;
        location: string | null;
        isPublic: boolean;
        tier: Option;
        department: Option | null;
        createdAt: string | null;
        approvedAt: string | null;
    };
    members: {
        id: number;
        name: string;
        role: string | null;
        canLogin: boolean;
        joinedAt: string | null;
    }[];
    features: Feature[];
    transitions: string[];
    can: {
        update: boolean;
        changeStatus: boolean;
        assignTier: boolean;
        overrideFeatures: boolean;
    };
    canLeaveUnsupervised: boolean;
    tiers: Option[];
    editableDepartments: Option[];
    activity: {
        id: number;
        description: string;
        causer: string | null;
        at: string | null;
    }[];
}>();

setLayoutProps({
    breadcrumbs: [
        { title: 'Organisation console', href: dashboard() },
        { title: 'Teams', href: index() },
        { title: props.team.name },
    ],
});

type Tab = 'overview' | 'members' | 'features' | 'settings';

const tabs = computed(() =>
    (
        [
            ['overview', 'Overview', true],
            ['members', 'Members', true],
            ['features', 'Features', true],
            ['settings', 'Settings', props.can.update],
        ] as [Tab, string, boolean][]
    ).filter(([, , visible]) => visible),
);
const tab = ref<Tab>('overview');

const showValue = (feature: Feature, value: boolean | number) =>
    feature.type === 'number'
        ? formatNumber(Number(value))
        : value
          ? trans('On')
          : trans('Off');

/* Status */
const statusDraft = ref(props.transitions[0] ?? '');
const reasonDraft = ref('');
const statusErrors = ref<Record<string, string>>({});
const needsReason = computed(() =>
    ['suspended', 'archived'].includes(statusDraft.value),
);

watch(
    () => props.transitions,
    (value) => {
        statusDraft.value = value[0] ?? '';
        reasonDraft.value = '';
    },
);

function changeStatus() {
    router.patch(
        statusRoute(props.team.slug).url,
        { status: statusDraft.value, reason: reasonDraft.value || null },
        {
            preserveScroll: true,
            onError: (errors) => (statusErrors.value = errors),
            onSuccess: () => (statusErrors.value = {}),
        },
    );
}

/* Tier */
const tierDraft = ref(props.team.tier.id);
watch(
    () => props.team.tier.id,
    (value) => (tierDraft.value = value),
);

function changeTier() {
    router.patch(
        tierRoute(props.team.slug).url,
        { tier_id: tierDraft.value },
        { preserveScroll: true },
    );
}

/* Feature overrides: '' means "use the tier value". */
const overrideDrafts = reactive<Record<string, string>>({});
const featureErrors = ref<Record<string, string>>({});

function draftOf(feature: Feature): string {
    if (feature.override === null) {
        return '';
    }

    return feature.type === 'number'
        ? String(feature.override)
        : feature.override
          ? 'on'
          : 'off';
}

watch(
    () => props.features,
    (features) => {
        for (const feature of features) {
            overrideDrafts[feature.name] = draftOf(feature);
        }
    },
    { immediate: true },
);

const changedFeatures = computed(() =>
    props.features.filter(
        (feature) => overrideDrafts[feature.name] !== draftOf(feature),
    ),
);

function saveFeatures() {
    const payload = changedFeatures.value.map((feature) => {
        const draft = overrideDrafts[feature.name];
        let value: boolean | number | null = null;

        if (draft !== '') {
            value = feature.type === 'number' ? Number(draft) : draft === 'on';
        }

        return { name: feature.name, value };
    });

    router.put(
        featuresRoute(props.team.slug).url,
        { features: payload },
        {
            preserveScroll: true,
            onError: (errors) => (featureErrors.value = errors),
            onSuccess: () => (featureErrors.value = {}),
        },
    );
}

function featureError(name: string): string | undefined {
    const index = changedFeatures.value.findIndex(
        (feature) => feature.name === name,
    );

    return index === -1
        ? undefined
        : featureErrors.value[`features.${index}.value`];
}
</script>

<template>
    <Head :title="team.name" />

    <div class="flex flex-col gap-6 p-4 md:p-6">
        <div class="flex flex-wrap items-center gap-3">
            <Heading
                :title="team.name"
                :description="
                    team.department
                        ? $t('Supervised by :department', {
                              department: team.department.name,
                          })
                        : $t('No department')
                "
            />
            <TeamStatusBadge :status="team.status" />
            <Badge variant="outline">{{ team.tier.name }}</Badge>
        </div>

        <nav class="section-tabs" role="tablist" data-tour="team-tabs">
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

        <!-- Overview -->
        <section
            v-if="tab === 'overview'"
            class="grid gap-6 lg:grid-cols-2"
            data-tour="team-overview"
        >
            <div class="rounded-xl border bg-card p-5">
                <h3 class="font-semibold">{{ $t('Details') }}</h3>
                <dl
                    class="mt-3 grid grid-cols-[auto_1fr] gap-x-6 gap-y-2 text-sm"
                >
                    <dt class="text-muted-foreground">{{ $t('Status') }}</dt>
                    <dd><TeamStatusBadge :status="team.status" /></dd>
                    <template v-if="team.statusReason">
                        <dt class="text-muted-foreground">
                            {{ $t('Reason') }}
                        </dt>
                        <dd>{{ team.statusReason }}</dd>
                    </template>
                    <dt class="text-muted-foreground">{{ $t('Members') }}</dt>
                    <dd class="tabular-nums">
                        {{ formatNumber(members.length) }}
                    </dd>
                    <dt class="text-muted-foreground">{{ $t('Location') }}</dt>
                    <dd>{{ team.location ?? '—' }}</dd>
                    <dt class="text-muted-foreground">
                        {{ $t('Public profile') }}
                    </dt>
                    <dd>{{ team.isPublic ? $t('Yes') : $t('No') }}</dd>
                    <dt class="text-muted-foreground">{{ $t('Created') }}</dt>
                    <dd class="tabular-nums">
                        {{ formatDate(team.createdAt) }}
                    </dd>
                    <template v-if="team.approvedAt">
                        <dt class="text-muted-foreground">
                            {{ $t('Approved') }}
                        </dt>
                        <dd class="tabular-nums">
                            {{ formatDate(team.approvedAt) }}
                        </dd>
                    </template>
                </dl>
            </div>

            <div class="grid content-start gap-6">
                <div
                    v-if="can.changeStatus && transitions.length"
                    class="rounded-xl border bg-card p-5"
                    data-test="status-form"
                    data-tour="team-status"
                >
                    <h3 class="font-semibold">{{ $t('Change status') }}</h3>
                    <div class="mt-3 grid gap-3">
                        <FormSelect
                            v-model="statusDraft"
                            :aria-label="$t('New status')"
                        >
                            <FormOption
                                v-for="status in transitions"
                                :key="status"
                                :value="status"
                            >
                                {{ $t(`team.status.${status}`) }}
                            </FormOption>
                        </FormSelect>
                        <InputError :message="statusErrors.status" />
                        <div v-if="needsReason" class="grid gap-2">
                            <Label for="status-reason">{{
                                $t('Reason')
                            }}</Label>
                            <Input
                                id="status-reason"
                                v-model="reasonDraft"
                                maxlength="500"
                            />
                            <InputError :message="statusErrors.reason" />
                        </div>
                        <div>
                            <Button
                                :variant="
                                    needsReason ? 'destructive' : 'default'
                                "
                                @click="changeStatus"
                            >
                                {{ $t('Apply') }}
                            </Button>
                        </div>
                    </div>
                </div>

                <div
                    v-if="can.assignTier"
                    class="rounded-xl border bg-card p-5"
                >
                    <h3 class="font-semibold">{{ $t('Tier') }}</h3>
                    <p class="mt-1 text-sm text-muted-foreground">
                        {{
                            $t(
                                'The tier sets the default features. Overrides on the Features tab still win.',
                            )
                        }}
                    </p>
                    <div class="mt-3 flex flex-wrap gap-2">
                        <FormSelect
                            v-model="tierDraft"
                            :aria-label="$t('Tier')"
                        >
                            <FormOption
                                v-for="tier in tiers"
                                :key="tier.id"
                                :value="tier.id"
                            >
                                {{ tier.name }}
                            </FormOption>
                        </FormSelect>
                        <Button
                            variant="outline"
                            :disabled="tierDraft === team.tier.id"
                            @click="changeTier"
                        >
                            {{ $t('Change tier') }}
                        </Button>
                    </div>
                </div>

                <div class="rounded-xl border bg-card p-5">
                    <h3 class="font-semibold">{{ $t('Recent activity') }}</h3>
                    <ul class="mt-3 divide-y text-sm">
                        <li
                            v-for="entry in activity"
                            :key="entry.id"
                            class="flex flex-wrap justify-between gap-2 py-2"
                        >
                            <span>
                                {{ entry.description }}
                                <span
                                    v-if="entry.causer"
                                    class="text-muted-foreground"
                                    >· {{ entry.causer }}</span
                                >
                            </span>
                            <time
                                v-if="entry.at"
                                class="text-muted-foreground tabular-nums"
                                >{{ formatDate(entry.at) }}</time
                            >
                        </li>
                        <li
                            v-if="!activity.length"
                            class="py-2 text-muted-foreground"
                        >
                            {{ $t('Nothing to show yet.') }}
                        </li>
                    </ul>
                </div>
            </div>
        </section>

        <!-- Members -->
        <section
            v-if="tab === 'members'"
            class="overflow-x-auto rounded-xl border bg-card"
        >
            <table class="w-full text-sm">
                <thead class="border-b bg-muted/40 text-muted-foreground">
                    <tr>
                        <th class="px-4 py-3 text-start font-semibold">
                            {{ $t('Name') }}
                        </th>
                        <th class="px-4 py-3 text-start font-semibold">
                            {{ $t('Role') }}
                        </th>
                        <th class="px-4 py-3 text-start font-semibold">
                            {{ $t('Login') }}
                        </th>
                        <th class="px-4 py-3 text-start font-semibold">
                            {{ $t('Joined') }}
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="member in members"
                        :key="member.id"
                        class="border-b last:border-0"
                    >
                        <td class="px-4 py-3 font-medium">{{ member.name }}</td>
                        <td class="px-4 py-3">
                            <Badge
                                :variant="
                                    member.role === 'captain'
                                        ? 'default'
                                        : 'outline'
                                "
                            >
                                {{
                                    member.role
                                        ? $t(`team.role.${member.role}`)
                                        : '—'
                                }}
                            </Badge>
                        </td>
                        <td class="px-4 py-3 text-muted-foreground">
                            {{ member.canLogin ? $t('Yes') : $t('No login') }}
                        </td>
                        <td
                            class="px-4 py-3 text-muted-foreground tabular-nums"
                        >
                            {{ formatDate(member.joinedAt) }}
                        </td>
                    </tr>
                    <tr v-if="!members.length">
                        <td
                            colspan="4"
                            class="px-4 py-8 text-center text-muted-foreground"
                        >
                            {{
                                $t(
                                    'No members yet. Invite a captain to get the team started.',
                                )
                            }}
                        </td>
                    </tr>
                </tbody>
            </table>
        </section>

        <!-- Features -->
        <section v-if="tab === 'features'" class="rounded-xl border bg-card">
            <div class="p-5 pb-0">
                <h3 class="font-semibold">{{ $t('Features') }}</h3>
                <p class="mt-1 text-sm text-muted-foreground">
                    {{
                        $t(
                            'Each feature comes from the tier unless this team has an override.',
                        )
                    }}
                </p>
            </div>
            <div class="mt-4 overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="border-y bg-muted/40 text-muted-foreground">
                        <tr>
                            <th class="px-4 py-3 text-start font-semibold">
                                {{ $t('Feature') }}
                            </th>
                            <th class="px-4 py-3 text-start font-semibold">
                                {{ $t('Tier value') }}
                            </th>
                            <th class="px-4 py-3 text-start font-semibold">
                                {{ $t('Override') }}
                            </th>
                            <th class="px-4 py-3 text-start font-semibold">
                                {{ $t('Effective') }}
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="feature in features"
                            :key="feature.name"
                            class="border-b last:border-0"
                            :data-test="`feature-${feature.name}`"
                        >
                            <td class="px-4 py-3 font-medium">
                                {{ $t(`feature.${feature.name}`) }}
                            </td>
                            <td
                                class="px-4 py-3 text-muted-foreground tabular-nums"
                            >
                                {{ showValue(feature, feature.tierValue) }}
                            </td>
                            <td class="px-4 py-3">
                                <template v-if="can.overrideFeatures">
                                    <Input
                                        v-if="feature.type === 'number'"
                                        v-model="overrideDrafts[feature.name]"
                                        type="number"
                                        min="1"
                                        max="500"
                                        class="w-28"
                                        dir="ltr"
                                        :placeholder="$t('Tier')"
                                        :aria-label="
                                            $t(`feature.${feature.name}`)
                                        "
                                    />
                                    <FormSelect
                                        v-else
                                        v-model="overrideDrafts[feature.name]"
                                        :aria-label="
                                            $t(`feature.${feature.name}`)
                                        "
                                    >
                                        <FormOption value="">
                                            {{ $t('Use tier') }}
                                        </FormOption>
                                        <FormOption value="on">
                                            {{ $t('On') }}
                                        </FormOption>
                                        <FormOption value="off">
                                            {{ $t('Off') }}
                                        </FormOption>
                                    </FormSelect>
                                    <InputError
                                        :message="featureError(feature.name)"
                                    />
                                </template>
                                <span v-else class="text-muted-foreground">
                                    {{
                                        feature.override === null
                                            ? '—'
                                            : showValue(
                                                  feature,
                                                  feature.override,
                                              )
                                    }}
                                </span>
                            </td>
                            <td class="px-4 py-3 font-semibold tabular-nums">
                                {{ showValue(feature, feature.effective) }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <div v-if="can.overrideFeatures" class="flex justify-end p-4">
                <Button
                    :disabled="!changedFeatures.length"
                    @click="saveFeatures"
                >
                    {{ $t('Save overrides') }}
                </Button>
            </div>
        </section>

        <!-- Settings -->
        <section
            v-if="tab === 'settings'"
            class="max-w-xl rounded-xl border bg-card p-5"
        >
            <Form
                v-bind="update.form(team.slug)"
                v-slot="{ errors, processing }"
                class="grid gap-4"
                :options="{ preserveScroll: true }"
            >
                <div class="grid gap-2">
                    <Label for="team-name">{{ $t('Team name') }}</Label>
                    <Input
                        id="team-name"
                        name="name"
                        :default-value="team.name"
                        required
                        maxlength="120"
                    />
                    <InputError :message="errors.name" />
                </div>
                <div class="grid gap-2">
                    <Label for="team-slug">{{ $t('Web address') }}</Label>
                    <Input
                        id="team-slug"
                        name="slug"
                        dir="ltr"
                        :default-value="team.slug"
                    />
                    <InputError :message="errors.slug" />
                </div>
                <div class="grid gap-2">
                    <Label for="team-location">{{ $t('Location') }}</Label>
                    <Input
                        id="team-location"
                        name="location"
                        :default-value="team.location ?? ''"
                        maxlength="120"
                    />
                    <InputError :message="errors.location" />
                </div>
                <div class="grid gap-2">
                    <Label for="team-department">{{
                        $t('Supervised by')
                    }}</Label>
                    <FormSelect
                        id="team-department"
                        name="department_id"
                        :default-value="team.department?.id ?? ''"
                    >
                        <FormOption v-if="canLeaveUnsupervised" value="">
                            {{ $t('No department') }}
                        </FormOption>
                        <FormOption
                            v-for="department in editableDepartments"
                            :key="department.id"
                            :value="department.id"
                        >
                            {{ department.name }}
                        </FormOption>
                    </FormSelect>
                    <InputError :message="errors.department_id" />
                </div>
                <div>
                    <Button type="submit" :disabled="processing">{{
                        $t('Save changes')
                    }}</Button>
                </div>
            </Form>
        </section>
    </div>
</template>
