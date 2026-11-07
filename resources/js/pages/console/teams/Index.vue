<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import { Plus } from '@lucide/vue';
import { trans } from 'laravel-vue-i18n';
import { h, ref } from 'vue';
import TeamStatusBadge from '@/components/app/TeamStatusBadge.vue';
import DataTable from '@/components/data-table/DataTable.vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { formatDate, formatNumber } from '@/lib/i18n';
import { dashboard } from '@/routes/console';
import { index, show, store } from '@/routes/console/teams';
import type { ListColumn, TableResult } from '@/types/table';

type TeamRow = {
    id: number;
    slug: string;
    name: string;
    status: string;
    tier: string;
    department: string | null;
    members: number;
    membersLimit: number;
    createdAt: string | null;
};

type Option = { id: number; slug: string; name: string };

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Organisation console', href: dashboard() },
            { title: 'Teams', href: index() },
        ],
    },
});

defineProps<{
    teams: TableResult<TeamRow>;
    filters: { statuses: string[]; tiers: Option[]; departments: Option[] };
    editableDepartments: Option[];
    can: { create: boolean; unsupervised: boolean };
}>();

const creating = ref(false);

const selectClass =
    'h-9 rounded-md border border-input bg-transparent px-3 text-sm shadow-xs';

const columns: ListColumn<TeamRow>[] = [
    {
        id: 'name',
        header: () => trans('Name'),
        meta: { sortable: true },
        cell: ({ row }) =>
            h(
                Link,
                {
                    href: show(row.original.slug).url,
                    class: 'font-medium text-foreground hover:underline',
                },
                () => row.original.name,
            ),
    },
    {
        id: 'status',
        header: () => trans('Status'),
        cell: ({ row }) => h(TeamStatusBadge, { status: row.original.status }),
    },
    {
        id: 'tier',
        header: () => trans('Tier'),
        cell: ({ row }) => row.original.tier,
    },
    {
        id: 'department',
        header: () => trans('Supervised by'),
        cell: ({ row }) =>
            row.original.department ??
            h(
                'span',
                { class: 'text-muted-foreground' },
                trans('No department'),
            ),
    },
    {
        id: 'members_count',
        header: () => trans('Members'),
        meta: { sortable: true },
        cell: ({ row }) =>
            h(
                'span',
                {
                    class: [
                        'tabular-nums',
                        row.original.members >= row.original.membersLimit &&
                            'font-semibold text-destructive',
                    ],
                },
                `${formatNumber(row.original.members)} / ${formatNumber(row.original.membersLimit)}`,
            ),
    },
    {
        id: 'created_at',
        header: () => trans('Created'),
        meta: { sortable: true },
        cell: ({ row }) =>
            h(
                'span',
                { class: 'text-muted-foreground tabular-nums' },
                formatDate(row.original.createdAt),
            ),
    },
];
</script>

<template>
    <Head :title="$t('Teams')" />

    <div class="flex flex-col gap-6 p-4 md:p-6">
        <Heading
            :title="$t('Teams')"
            :description="
                $t(
                    'Every team on the platform, its tier and who supervises it.',
                )
            "
        />

        <DataTable
            :columns="columns"
            :rows="teams"
            :search-placeholder="$t('Search teams')"
            :empty-text="$t('No teams match.')"
            :only="['teams']"
        >
            <template #filters="{ filter }">
                <select
                    v-model="filter.status"
                    :class="selectClass"
                    :aria-label="$t('Status')"
                    data-test="filter-status"
                >
                    <option :value="undefined">{{ $t('All statuses') }}</option>
                    <option
                        v-for="status in filters.statuses"
                        :key="status"
                        :value="status"
                    >
                        {{ $t(`team.status.${status}`) }}
                    </option>
                </select>
                <select
                    v-model="filter.tier"
                    :class="selectClass"
                    :aria-label="$t('Tier')"
                >
                    <option :value="undefined">{{ $t('All tiers') }}</option>
                    <option
                        v-for="tier in filters.tiers"
                        :key="tier.slug"
                        :value="tier.slug"
                    >
                        {{ tier.name }}
                    </option>
                </select>
                <select
                    v-if="filters.departments.length > 1"
                    v-model="filter.department"
                    :class="selectClass"
                    :aria-label="$t('Supervised by')"
                >
                    <option :value="undefined">
                        {{ $t('All departments') }}
                    </option>
                    <option
                        v-for="department in filters.departments"
                        :key="department.slug"
                        :value="department.slug"
                    >
                        {{ department.name }}
                    </option>
                </select>
            </template>

            <template v-if="can.create" #actions>
                <Dialog v-model:open="creating">
                    <DialogTrigger as-child>
                        <Button data-test="new-team">
                            <Plus class="size-4" />
                            {{ $t('New team') }}
                        </Button>
                    </DialogTrigger>
                    <DialogContent class="sm:max-w-lg">
                        <DialogHeader>
                            <DialogTitle>{{ $t('New team') }}</DialogTitle>
                            <DialogDescription>
                                {{
                                    $t(
                                        'Optionally invite the captain now; they will set up the rest of the team.',
                                    )
                                }}
                            </DialogDescription>
                        </DialogHeader>

                        <Form
                            v-bind="store.form()"
                            v-slot="{ errors, processing }"
                            class="grid gap-4"
                            @success="creating = false"
                        >
                            <div class="grid gap-2">
                                <Label for="team-name">{{
                                    $t('Team name')
                                }}</Label>
                                <Input
                                    id="team-name"
                                    name="name"
                                    required
                                    maxlength="120"
                                />
                                <InputError :message="errors.name" />
                            </div>
                            <div class="grid gap-4 sm:grid-cols-2">
                                <div class="grid gap-2">
                                    <Label for="team-tier">{{
                                        $t('Tier')
                                    }}</Label>
                                    <select
                                        id="team-tier"
                                        name="tier_id"
                                        :class="selectClass"
                                    >
                                        <option
                                            v-for="tier in filters.tiers"
                                            :key="tier.id"
                                            :value="tier.id"
                                        >
                                            {{ tier.name }}
                                        </option>
                                    </select>
                                    <InputError :message="errors.tier_id" />
                                </div>
                                <div class="grid gap-2">
                                    <Label for="team-department">{{
                                        $t('Supervised by')
                                    }}</Label>
                                    <select
                                        id="team-department"
                                        name="department_id"
                                        :class="selectClass"
                                    >
                                        <option
                                            v-if="can.unsupervised"
                                            value=""
                                        >
                                            {{ $t('No department') }}
                                        </option>
                                        <option
                                            v-for="department in editableDepartments"
                                            :key="department.id"
                                            :value="department.id"
                                        >
                                            {{ department.name }}
                                        </option>
                                    </select>
                                    <InputError
                                        :message="errors.department_id"
                                    />
                                </div>
                            </div>
                            <div class="grid gap-2">
                                <Label for="team-location">{{
                                    $t('Location')
                                }}</Label>
                                <Input
                                    id="team-location"
                                    name="location"
                                    maxlength="120"
                                />
                                <InputError :message="errors.location" />
                            </div>
                            <div class="grid gap-2">
                                <Label for="team-captain">{{
                                    $t('Captain’s email (optional)')
                                }}</Label>
                                <Input
                                    id="team-captain"
                                    name="captain_email"
                                    type="email"
                                    dir="ltr"
                                />
                                <InputError :message="errors.captain_email" />
                            </div>

                            <DialogFooter>
                                <Button type="submit" :disabled="processing">
                                    {{ $t('Create team') }}
                                </Button>
                            </DialogFooter>
                        </Form>
                    </DialogContent>
                </Dialog>
            </template>
        </DataTable>
    </div>
</template>
