<script setup lang="ts">
import FormOption from '@/components/forms/FormOption.vue';
import FormSelect from '@/components/forms/FormSelect.vue';
import { department as departmentReport } from '@/routes/console/reports';
import { Form, Head, Link, router } from '@inertiajs/vue3';
import { trans } from 'laravel-vue-i18n';
import { computed, reactive, ref, watch } from 'vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { formatDate, formatNumber } from '@/lib/i18n';
import { dashboard } from '@/routes/console';
import {
    ceiling as ceilingRoute,
    index,
    update,
} from '@/routes/console/departments';
import {
    destroy as revokeInvitation,
    store as storeInvitation,
} from '@/routes/console/departments/invitations';
import {
    admin as setAdmin,
    destroy as removeMember,
} from '@/routes/console/departments/members';
import { sync as syncPermissions } from '@/routes/console/departments/permissions';

type Member = {
    id: number;
    name: string;
    email: string | null;
    isAdmin: boolean;
    canLogin: boolean;
    permissions: string[];
};

type Department = {
    id: number;
    slug: string;
    name: string;
    name_en: string;
    name_ar: string | null;
    description_en: string | null;
    description_ar: string | null;
    is_public: boolean;
};

const props = defineProps<{
    department: Department;
    isAdmin: boolean;
    canEditCeiling: boolean;
    canEditDetails: boolean;
    canViewReport: boolean;
    canManageMembers: boolean;
    stats: { members: number; admins: number; teams: number };
    myPermissions: string[];
    ceiling: string[];
    catalogue: { area: string; actions: string[] }[];
    activity: {
        id: number;
        event: string | null;
        description: string;
        causer: string | null;
        at: string | null;
    }[];
    members?: Member[];
    invitations?: {
        id: number;
        email: string;
        role: string;
        expiresAt: string | null;
    }[];
    inviteRoles?: string[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Organisation console', href: dashboard() },
            { title: 'Departments', href: index() },
        ],
    },
});

type Tab = 'overview' | 'members' | 'permissions' | 'settings';

const tabs = computed(() =>
    (
        [
            ['overview', 'Overview', true],
            ['members', 'Members', props.canManageMembers],
            ['permissions', 'Permissions', props.canManageMembers],
            ['settings', 'Settings', props.canEditDetails],
        ] as [Tab, string, boolean][]
    ).filter(([, , visible]) => visible),
);

const tab = ref<Tab>('overview');

const areaLabel = (area: string) => trans(`area.${area}`);
const actionLabel = (action: string) => trans(`action.${action}`);
const permissionLabel = (name: string) => {
    const [area, ...rest] = name.split('.');

    return `${areaLabel(area)} · ${actionLabel(rest.join('.'))}`;
};

/* Ceiling editor (super admin). */
const ceilingDraft = ref<string[]>([...props.ceiling]);
watch(
    () => props.ceiling,
    (value) => (ceilingDraft.value = [...value]),
);

function toggleIn(list: string[], name: string, on: boolean): string[] {
    return on
        ? [...new Set([...list, name])]
        : list.filter((item) => item !== name);
}

function saveCeiling() {
    router.put(
        ceilingRoute(props.department.slug).url,
        { permissions: ceilingDraft.value },
        { preserveScroll: true },
    );
}

/* Member permission matrix (department admin). */
const delegableColumns = computed(() =>
    props.catalogue.flatMap(({ area, actions }) =>
        actions
            .map((action) => `${area}.${action}`)
            .filter((name) => props.ceiling.includes(name)),
    ),
);

const grantable = computed(() =>
    (props.members ?? []).filter((member) => !member.isAdmin),
);

const drafts = reactive<Record<number, string[]>>({});
watch(
    () => props.members,
    (members) => {
        for (const member of members ?? []) {
            drafts[member.id] = [...member.permissions];
        }
    },
    { immediate: true },
);

function isDirty(member: Member): boolean {
    const draft = [...(drafts[member.id] ?? [])].sort().join('|');

    return draft !== [...member.permissions].sort().join('|');
}

function savePermissions(member: Member) {
    router.put(
        syncPermissions({ department: props.department.slug, user: member.id })
            .url,
        { permissions: drafts[member.id] ?? [] },
        { preserveScroll: true },
    );
}

function toggleAdmin(member: Member) {
    router.patch(
        setAdmin({ department: props.department.slug, user: member.id }).url,
        { is_admin: !member.isAdmin },
        { preserveScroll: true },
    );
}

function remove(member: Member) {
    if (
        !window.confirm(
            trans('Remove :name from this department?', { name: member.name }),
        )
    ) {
        return;
    }

    router.delete(
        removeMember({ department: props.department.slug, user: member.id })
            .url,
        {
            preserveScroll: true,
        },
    );
}

function revoke(invitationId: number) {
    router.delete(
        revokeInvitation({
            department: props.department.slug,
            invitation: invitationId,
        }).url,
        { preserveScroll: true },
    );
}

const isPublicDraft = ref(props.department.is_public);
</script>

<template>
    <Head :title="department.name" />

    <div class="flex flex-col gap-6 p-4 md:p-6">
        <div class="flex flex-wrap items-center gap-3">
            <Heading :title="department.name" :description="$t('Department')" />
            <Badge v-if="isAdmin" variant="outline">{{ $t('Admin') }}</Badge>
            <Badge :variant="department.is_public ? 'secondary' : 'outline'">
                {{ department.is_public ? $t('Public') : $t('Internal') }}
            </Badge>
            <Link
                v-if="canViewReport"
                :href="departmentReport(department.slug)"
                class="ms-auto text-sm font-medium text-primary hover:underline"
                data-test="department-report"
                data-tour="department-report"
                >{{ $t('Report') }}</Link
            >
        </div>

        <nav
            class="flex gap-1 overflow-x-auto border-b"
            role="tablist"
            data-tour="department-tabs"
        >
            <button
                v-for="[key, label] in tabs"
                :key="key"
                type="button"
                role="tab"
                :aria-selected="tab === key"
                class="-mb-px border-b-2 px-3 py-2 text-sm font-medium whitespace-nowrap transition-colors"
                :class="
                    tab === key
                        ? 'border-primary text-foreground'
                        : 'border-transparent text-muted-foreground hover:text-foreground'
                "
                :data-test="`tab-${key}`"
                @click="tab = key"
            >
                {{ $t(label) }}
            </button>
        </nav>

        <!-- Overview -->
        <section
            v-if="tab === 'overview'"
            class="grid gap-6"
            data-tour="department-overview"
        >
            <div class="grid gap-4 sm:grid-cols-3">
                <div class="rounded-xl border bg-card p-5">
                    <p class="text-sm text-muted-foreground">
                        {{ $t('Members') }}
                    </p>
                    <p class="mt-1 text-3xl font-semibold tabular-nums">
                        {{ formatNumber(stats.members) }}
                    </p>
                </div>
                <div class="rounded-xl border bg-card p-5">
                    <p class="text-sm text-muted-foreground">
                        {{ $t('Admins') }}
                    </p>
                    <p class="mt-1 text-3xl font-semibold tabular-nums">
                        {{ formatNumber(stats.admins) }}
                    </p>
                </div>
                <div class="rounded-xl border bg-card p-5">
                    <p class="text-sm text-muted-foreground">
                        {{ $t('Supervised teams') }}
                    </p>
                    <p class="mt-1 text-3xl font-semibold tabular-nums">
                        {{ formatNumber(stats.teams) }}
                    </p>
                </div>
            </div>

            <div class="rounded-xl border bg-card p-5">
                <h3 class="font-semibold">{{ $t('Your access') }}</h3>
                <div class="mt-3 flex flex-wrap gap-2">
                    <Badge
                        v-for="name in myPermissions"
                        :key="name"
                        variant="secondary"
                    >
                        {{ permissionLabel(name) }}
                    </Badge>
                    <span
                        v-if="!myPermissions.length"
                        class="text-sm text-muted-foreground"
                    >
                        {{ $t('No areas granted yet.') }}
                    </span>
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
                            >
                                · {{ entry.causer }}
                            </span>
                        </span>
                        <time
                            v-if="entry.at"
                            class="text-muted-foreground tabular-nums"
                        >
                            {{ formatDate(entry.at) }}
                        </time>
                    </li>
                    <li
                        v-if="!activity.length"
                        class="py-2 text-muted-foreground"
                    >
                        {{ $t('Nothing to show yet.') }}
                    </li>
                </ul>
            </div>
        </section>

        <!-- Members -->
        <section v-if="tab === 'members' && members" class="grid gap-6">
            <div class="overflow-x-auto rounded-xl border bg-card">
                <table class="w-full text-sm">
                    <thead class="border-b bg-muted/40 text-muted-foreground">
                        <tr>
                            <th class="px-4 py-3 text-start font-semibold">
                                {{ $t('Name') }}
                            </th>
                            <th class="px-4 py-3 text-start font-semibold">
                                {{ $t('Email') }}
                            </th>
                            <th class="px-4 py-3 text-start font-semibold">
                                {{ $t('Role') }}
                            </th>
                            <th class="px-4 py-3" />
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="member in members"
                            :key="member.id"
                            class="border-b last:border-0"
                            :data-test="`member-${member.id}`"
                        >
                            <td class="px-4 py-3 font-medium">
                                {{ member.name }}
                            </td>
                            <td
                                class="px-4 py-3 text-muted-foreground"
                                dir="ltr"
                            >
                                {{ member.email ?? $t('No login') }}
                            </td>
                            <td class="px-4 py-3">
                                <Badge
                                    :variant="
                                        member.isAdmin ? 'default' : 'outline'
                                    "
                                >
                                    {{
                                        member.isAdmin
                                            ? $t('Admin')
                                            : $t('Member')
                                    }}
                                </Badge>
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex justify-end gap-2">
                                    <Button
                                        v-if="canEditCeiling"
                                        variant="outline"
                                        size="sm"
                                        @click="toggleAdmin(member)"
                                    >
                                        {{
                                            member.isAdmin
                                                ? $t('Remove admin')
                                                : $t('Make admin')
                                        }}
                                    </Button>
                                    <Button
                                        v-if="canEditCeiling || !member.isAdmin"
                                        variant="ghost"
                                        size="sm"
                                        class="text-destructive"
                                        @click="remove(member)"
                                    >
                                        {{ $t('Remove') }}
                                    </Button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="grid gap-6 lg:grid-cols-2">
                <div class="rounded-xl border bg-card p-5">
                    <h3 class="font-semibold">{{ $t('Invite someone') }}</h3>
                    <Form
                        v-bind="storeInvitation.form(department.slug)"
                        v-slot="{ errors, processing }"
                        class="mt-4 grid gap-4"
                        reset-on-success
                        :options="{ preserveScroll: true }"
                    >
                        <div class="grid gap-2">
                            <Label for="invite-email">{{ $t('Email') }}</Label>
                            <Input
                                id="invite-email"
                                name="email"
                                type="email"
                                dir="ltr"
                                required
                            />
                            <InputError :message="errors.email" />
                        </div>
                        <div class="grid gap-2">
                            <Label for="invite-role">{{ $t('Role') }}</Label>
                            <FormSelect
                                id="invite-role"
                                name="role"
                                class="h-9 rounded-md border border-input bg-transparent px-3 text-sm shadow-xs"
                            >
                                <FormOption
                                    v-for="role in inviteRoles"
                                    :key="role"
                                    :value="role"
                                >
                                    {{ $t(`role.${role}`) }}
                                </FormOption>
                            </FormSelect>
                            <InputError :message="errors.role" />
                        </div>
                        <div>
                            <Button type="submit" :disabled="processing">
                                {{ $t('Send invitation') }}
                            </Button>
                        </div>
                    </Form>
                </div>

                <div class="rounded-xl border bg-card p-5">
                    <h3 class="font-semibold">
                        {{ $t('Pending invitations') }}
                    </h3>
                    <ul class="mt-3 divide-y text-sm">
                        <li
                            v-for="invitation in invitations"
                            :key="invitation.id"
                            class="flex items-center justify-between gap-2 py-2"
                        >
                            <span>
                                <span dir="ltr">{{ invitation.email }}</span>
                                <span class="text-muted-foreground">
                                    · {{ $t(`role.${invitation.role}`) }}
                                    <template v-if="invitation.expiresAt">
                                        · {{ $t('expires') }}
                                        {{ formatDate(invitation.expiresAt) }}
                                    </template>
                                </span>
                            </span>
                            <Button
                                variant="ghost"
                                size="sm"
                                @click="revoke(invitation.id)"
                            >
                                {{ $t('Revoke') }}
                            </Button>
                        </li>
                        <li
                            v-if="!invitations?.length"
                            class="py-2 text-muted-foreground"
                        >
                            {{ $t('No pending invitations.') }}
                        </li>
                    </ul>
                </div>
            </div>
        </section>

        <!-- Permissions -->
        <section v-if="tab === 'permissions' && members" class="grid gap-6">
            <div
                v-if="canEditCeiling"
                class="rounded-xl border bg-card p-5"
                data-test="ceiling-editor"
            >
                <h3 class="font-semibold">{{ $t('Permission ceiling') }}</h3>
                <p class="mt-1 text-sm text-muted-foreground">
                    {{
                        $t(
                            'The most this department can do. Admins can approve in every area they can edit; members get only what an admin gives them.',
                        )
                    }}
                </p>
                <div class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                    <fieldset
                        v-for="group in catalogue"
                        :key="group.area"
                        class="rounded-lg border p-3"
                    >
                        <legend class="px-1 text-sm font-semibold">
                            {{ areaLabel(group.area) }}
                        </legend>
                        <Label
                            v-for="action in group.actions"
                            :key="action"
                            class="flex items-center gap-2 py-1 font-normal"
                        >
                            <Checkbox
                                :model-value="
                                    ceilingDraft.includes(
                                        `${group.area}.${action}`,
                                    )
                                "
                                @update:model-value="
                                    (on) =>
                                        (ceilingDraft = toggleIn(
                                            ceilingDraft,
                                            `${group.area}.${action}`,
                                            on === true,
                                        ))
                                "
                            />
                            {{ actionLabel(action) }}
                        </Label>
                    </fieldset>
                </div>
                <Button class="mt-4" @click="saveCeiling">{{
                    $t('Save ceiling')
                }}</Button>
            </div>

            <div class="rounded-xl border bg-card">
                <div class="p-5 pb-0">
                    <h3 class="font-semibold">
                        {{ $t('Member permissions') }}
                    </h3>
                    <p class="mt-1 text-sm text-muted-foreground">
                        {{
                            $t(
                                'Edit includes view. Only permissions inside the ceiling are shown.',
                            )
                        }}
                    </p>
                </div>
                <div class="mt-4 overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead
                            class="border-y bg-muted/40 text-muted-foreground"
                        >
                            <tr>
                                <th class="px-4 py-3 text-start font-semibold">
                                    {{ $t('Member') }}
                                </th>
                                <th
                                    v-for="name in delegableColumns"
                                    :key="name"
                                    class="px-3 py-3 text-center font-semibold whitespace-nowrap"
                                >
                                    {{ permissionLabel(name) }}
                                </th>
                                <th class="px-4 py-3" />
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="member in grantable"
                                :key="member.id"
                                class="border-b last:border-0"
                            >
                                <td
                                    class="px-4 py-3 font-medium whitespace-nowrap"
                                >
                                    {{ member.name }}
                                </td>
                                <td
                                    v-for="name in delegableColumns"
                                    :key="name"
                                    class="px-3 py-3 text-center"
                                >
                                    <Checkbox
                                        :aria-label="`${member.name}: ${permissionLabel(name)}`"
                                        :model-value="
                                            (drafts[member.id] ?? []).includes(
                                                name,
                                            )
                                        "
                                        @update:model-value="
                                            (on) =>
                                                (drafts[member.id] = toggleIn(
                                                    drafts[member.id] ?? [],
                                                    name,
                                                    on === true,
                                                ))
                                        "
                                    />
                                </td>
                                <td class="px-4 py-3 text-end">
                                    <Button
                                        size="sm"
                                        :variant="
                                            isDirty(member)
                                                ? 'default'
                                                : 'outline'
                                        "
                                        :disabled="!isDirty(member)"
                                        @click="savePermissions(member)"
                                    >
                                        {{ $t('Save') }}
                                    </Button>
                                </td>
                            </tr>
                            <tr v-if="!grantable.length">
                                <td
                                    :colspan="delegableColumns.length + 2"
                                    class="px-4 py-8 text-center text-muted-foreground"
                                >
                                    {{
                                        $t(
                                            'No members to give permissions to yet.',
                                        )
                                    }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </section>

        <!-- Settings -->
        <section
            v-if="tab === 'settings'"
            class="max-w-xl rounded-xl border bg-card p-5"
        >
            <Form
                v-bind="update.form(department.slug)"
                v-slot="{ errors, processing }"
                class="grid gap-4"
                :transform="(data) => ({ ...data, is_public: isPublicDraft })"
                :options="{ preserveScroll: true }"
            >
                <div class="grid gap-2">
                    <Label for="name_en">{{ $t('Name (English)') }}</Label>
                    <Input
                        id="name_en"
                        name="name_en"
                        :default-value="department.name_en"
                        required
                    />
                    <InputError :message="errors.name_en" />
                </div>
                <div class="grid gap-2">
                    <Label for="name_ar">{{ $t('Name (Arabic)') }}</Label>
                    <Input
                        id="name_ar"
                        name="name_ar"
                        dir="rtl"
                        :default-value="department.name_ar ?? ''"
                    />
                    <InputError :message="errors.name_ar" />
                </div>
                <div class="grid gap-2">
                    <Label for="slug">{{ $t('Web address') }}</Label>
                    <Input
                        id="slug"
                        name="slug"
                        dir="ltr"
                        :default-value="department.slug"
                    />
                    <InputError :message="errors.slug" />
                </div>
                <div class="grid gap-2">
                    <Label for="description_en">{{
                        $t('Description (English)')
                    }}</Label>
                    <textarea
                        id="description_en"
                        name="description_en"
                        rows="3"
                        class="rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs"
                        :value="department.description_en ?? ''"
                    />
                    <InputError :message="errors.description_en" />
                </div>
                <div class="grid gap-2">
                    <Label for="description_ar">{{
                        $t('Description (Arabic)')
                    }}</Label>
                    <textarea
                        id="description_ar"
                        name="description_ar"
                        rows="3"
                        dir="rtl"
                        class="rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs"
                        :value="department.description_ar ?? ''"
                    />
                    <InputError :message="errors.description_ar" />
                </div>
                <Label class="flex items-center gap-2 font-normal">
                    <Checkbox v-model="isPublicDraft" />
                    {{ $t('Show on the public About page') }}
                </Label>
                <div>
                    <Button type="submit" :disabled="processing">{{
                        $t('Save changes')
                    }}</Button>
                </div>
            </Form>
        </section>
    </div>
</template>
