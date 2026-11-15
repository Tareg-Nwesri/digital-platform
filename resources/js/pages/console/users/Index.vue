<script setup lang="ts">
import FormOption from '@/components/forms/FormOption.vue';
import FormSelect from '@/components/forms/FormSelect.vue';
import { Head, router } from '@inertiajs/vue3';
import { LogIn, Power, PowerOff } from '@lucide/vue';
import { trans } from 'laravel-vue-i18n';
import { h, ref } from 'vue';
import DataTable from '@/components/data-table/DataTable.vue';
import Heading from '@/components/Heading.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { formatDate, formatNumber } from '@/lib/i18n';
import { dashboard } from '@/routes/console';
import { active as activeRoute, index } from '@/routes/console/users';
import { take } from '@/routes/impersonate';
import type { ListColumn, TableResult } from '@/types/table';

type UserRow = {
    id: number;
    name: string;
    email: string | null;
    isActive: boolean;
    canLogin: boolean;
    isSuperAdmin: boolean;
    memberships: number;
    lastLoginAt: string | null;
    canImpersonate: boolean;
    canToggleActive: boolean;
};

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Organisation console', href: dashboard() },
            { title: 'Users', href: index() },
        ],
    },
});

defineProps<{ users: TableResult<UserRow> }>();

const confirming = ref<UserRow | null>(null);
const processing = ref(false);

function setActive(user: UserRow, active: boolean): void {
    router.patch(
        activeRoute(user.id).url,
        { active },
        {
            preserveScroll: true,
            only: ['users', 'flash'],
            onStart: () => (processing.value = true),
            onFinish: () => {
                processing.value = false;
                confirming.value = null;
            },
        },
    );
}

function impersonate(user: UserRow): void {
    router.post(take(user.id).url);
}

const columns: ListColumn<UserRow>[] = [
    {
        id: 'name_en',
        header: () => trans('Name'),
        meta: { sortable: true },
        cell: ({ row }) =>
            h('div', { class: 'flex flex-wrap items-center gap-2' }, [
                h(
                    'span',
                    { class: 'font-medium text-foreground' },
                    row.original.name,
                ),
                row.original.isSuperAdmin &&
                    h(Badge, { variant: 'secondary' }, () =>
                        trans('Super admin'),
                    ),
                !row.original.canLogin &&
                    h(Badge, { variant: 'outline' }, () => trans('No login')),
            ]),
    },
    {
        id: 'email',
        header: () => trans('Email'),
        meta: { sortable: true },
        cell: ({ row }) =>
            h(
                'span',
                { class: 'text-muted-foreground', dir: 'ltr' },
                row.original.email ?? '—',
            ),
    },
    {
        id: 'status',
        header: () => trans('Status'),
        cell: ({ row }) =>
            h(
                Badge,
                {
                    variant: row.original.isActive ? 'default' : 'destructive',
                    'data-active': String(row.original.isActive),
                },
                () =>
                    row.original.isActive ? trans('Active') : trans('Inactive'),
            ),
    },
    {
        id: 'memberships',
        header: () => trans('Memberships'),
        cell: ({ row }) =>
            h(
                'span',
                { class: 'tabular-nums' },
                formatNumber(row.original.memberships),
            ),
    },
    {
        id: 'last_login_at',
        header: () => trans('Last sign-in'),
        meta: { sortable: true },
        cell: ({ row }) =>
            h(
                'span',
                { class: 'text-muted-foreground tabular-nums' },
                row.original.lastLoginAt
                    ? formatDate(row.original.lastLoginAt)
                    : trans('Never'),
            ),
    },
    {
        id: 'actions',
        header: () => h('span', { class: 'sr-only' }, trans('Actions')),
        cell: ({ row }) =>
            h('div', { class: 'flex justify-end gap-2' }, [
                row.original.canImpersonate &&
                    h(
                        Button,
                        {
                            size: 'sm',
                            variant: 'outline',
                            'data-test': `impersonate-${row.original.id}`,
                            onClick: () => impersonate(row.original),
                        },
                        () => [h(LogIn, { class: 'size-4' }), trans('Act as')],
                    ),
                row.original.canToggleActive &&
                    (row.original.isActive
                        ? h(
                              Button,
                              {
                                  size: 'sm',
                                  variant: 'ghost',
                                  class: 'text-destructive',
                                  'data-test': `deactivate-${row.original.id}`,
                                  onClick: () =>
                                      (confirming.value = row.original),
                              },
                              () => [
                                  h(PowerOff, { class: 'size-4' }),
                                  trans('Deactivate'),
                              ],
                          )
                        : h(
                              Button,
                              {
                                  size: 'sm',
                                  variant: 'ghost',
                                  'data-test': `activate-${row.original.id}`,
                                  onClick: () => setActive(row.original, true),
                              },
                              () => [
                                  h(Power, { class: 'size-4' }),
                                  trans('Activate'),
                              ],
                          )),
            ]),
    },
];
</script>

<template>
    <Head :title="$t('Users')" />

    <div class="flex flex-col gap-6 p-4 md:p-6">
        <Heading
            :title="$t('Users')"
            :description="
                $t(
                    'Everyone with an account on the platform. Act as a user to see what they see.',
                )
            "
        />

        <DataTable
            :columns="columns"
            :rows="users"
            :search-placeholder="$t('Search by name or email')"
            :empty-text="$t('No users match.')"
            :only="['users']"
        >
            <template #filters="{ filter }">
                <FormSelect
                    v-model="filter.active"
                    :aria-label="$t('Status')"
                    data-test="filter-active"
                >
                    <FormOption :value="undefined">{{
                        $t('All users')
                    }}</FormOption>
                    <FormOption value="1">{{ $t('Active') }}</FormOption>
                    <FormOption value="0">{{ $t('Inactive') }}</FormOption>
                </FormSelect>
            </template>
        </DataTable>

        <Dialog
            :open="confirming !== null"
            @update:open="(open) => !open && (confirming = null)"
        >
            <DialogContent class="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>
                        {{
                            $t('Deactivate :name?', {
                                name: confirming?.name ?? '',
                            })
                        }}
                    </DialogTitle>
                    <DialogDescription>
                        {{
                            $t(
                                'They will be signed out now and cannot sign in until you activate them again. Their memberships are kept.',
                            )
                        }}
                    </DialogDescription>
                </DialogHeader>
                <DialogFooter>
                    <Button variant="outline" @click="confirming = null">
                        {{ $t('Cancel') }}
                    </Button>
                    <Button
                        variant="destructive"
                        :disabled="processing"
                        data-test="confirm-deactivate"
                        @click="confirming && setActive(confirming, false)"
                    >
                        {{ $t('Deactivate') }}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </div>
</template>
