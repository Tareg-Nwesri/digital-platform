<script setup lang="ts">
import FormOption from '@/components/forms/FormOption.vue';
import FormSelect from '@/components/forms/FormSelect.vue';
import SearchSelect from '@/components/forms/SearchSelect.vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import { Plus } from '@lucide/vue';
import { trans } from 'laravel-vue-i18n';
import { computed, h, ref } from 'vue';
import DataTable from '@/components/data-table/DataTable.vue';
import FinanceNav from '@/components/finance/FinanceNav.vue';
import Money from '@/components/finance/Money.vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
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
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { dashboard as consoleDashboard } from '@/routes/console';
import { dashboard } from '@/routes/console/finance';
import { cancel, store, submit } from '@/routes/console/finance/payouts';
import type { ListColumn, TableResult } from '@/types/table';

type MoneyValue = { amountMinor: number; currency: string; formatted: string };
type PayoutRow = {
    id: number;
    payee: string;
    type: string;
    amount: MoneyValue;
    source: string;
    reason: string;
    status: string;
    needsSecondApproval: boolean;
    guardian: string | null;
    reference: string | null;
    paidOn: string | null;
    rejectionReason: string | null;
};

const props = defineProps<{
    payouts: TableResult<PayoutRow>;
    statuses: string[];
    types: string[];
    currencies: string[];
    sources: { id: number; name: string; currency: string }[];
    people: { id: number; name: string; minor: boolean }[];
    can: { edit: boolean };
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Organisation console', href: consoleDashboard() },
            { title: 'Finance', href: dashboard() },
            { title: 'Payouts' },
        ],
    },
});

const open = ref(false);
const form = useForm({
    user_id: null as number | null,
    type: 'bonus',
    amount: '',
    currency: 'LYD',
    rate_to_lyd: '',
    source_account_id: null as number | null,
    reason: '',
    guardian_name: '',
    guardian_contact: '',
});

const personOptions = computed(() =>
    props.people.map((person) => ({
        value: person.id,
        label: person.name,
        hint: person.minor ? trans('Under 18') : null,
    })),
);
const payeeIsMinor = computed(
    () =>
        props.people.find((person) => person.id === form.user_id)?.minor ??
        false,
);
const sources = computed(() =>
    props.sources.filter((source) => source.currency === form.currency),
);

function create(): void {
    form.transform((data) => ({
        ...data,
        rate_to_lyd: data.currency === 'LYD' ? null : data.rate_to_lyd || null,
        guardian_name: data.guardian_name || null,
        guardian_contact: data.guardian_contact || null,
    })).post(store().url, {
        preserveScroll: true,
        onSuccess: () => {
            open.value = false;
            form.reset();
        },
    });
}

function act(route: typeof submit, row: PayoutRow): void {
    router.post(route(row.id).url, {}, { preserveScroll: true });
}

const filterClass =
    'h-9 rounded-md border border-input bg-transparent px-3 text-sm shadow-xs';

const columns: ListColumn<PayoutRow>[] = [
    {
        id: 'payee',
        header: () => trans('Paid to'),
        cell: ({ row }) =>
            h('div', { class: 'grid gap-0.5' }, [
                h('span', { class: 'font-medium' }, row.original.payee),
                h(
                    'span',
                    { class: 'text-xs text-muted-foreground' },
                    `${trans(`finance.payout_type.${row.original.type}`)} · ${row.original.reason}`,
                ),
                row.original.guardian &&
                    h(
                        'span',
                        { class: 'text-xs text-muted-foreground' },
                        trans('Guardian: :name', {
                            name: row.original.guardian,
                        }),
                    ),
                row.original.rejectionReason &&
                    h(
                        'span',
                        { class: 'text-xs text-destructive' },
                        row.original.rejectionReason,
                    ),
            ]),
    },
    {
        id: 'status',
        header: () => trans('Status'),
        cell: ({ row }) =>
            h('div', { class: 'grid gap-1' }, [
                h(
                    Badge,
                    {
                        variant:
                            row.original.status === 'paid'
                                ? 'default'
                                : 'secondary',
                    },
                    () => trans(`finance.payout_status.${row.original.status}`),
                ),
                row.original.needsSecondApproval &&
                    h(
                        'span',
                        { class: 'text-xs text-muted-foreground' },
                        trans('Needs two approvals'),
                    ),
                row.original.reference &&
                    h(
                        'span',
                        { class: 'text-xs text-muted-foreground', dir: 'ltr' },
                        `${row.original.paidOn} · ${row.original.reference}`,
                    ),
            ]),
    },
    {
        id: 'amount',
        header: () => trans('Amount'),
        meta: { align: 'end' },
        cell: ({ row }) =>
            h('div', { class: 'grid justify-items-end' }, [
                h(Money, { value: row.original.amount }),
                h(
                    'span',
                    { class: 'text-xs text-muted-foreground' },
                    trans('from :name', { name: row.original.source }),
                ),
            ]),
    },
    {
        id: 'actions',
        header: () => h('span', { class: 'sr-only' }, trans('Actions')),
        cell: ({ row }) =>
            props.can.edit
                ? h('div', { class: 'flex justify-end gap-1' }, [
                      row.original.status === 'draft' &&
                          h(
                              Button,
                              {
                                  size: 'sm',
                                  variant: 'outline',
                                  onClick: () => act(submit, row.original),
                              },
                              () => trans('Send for approval'),
                          ),
                      ['draft', 'submitted', 'approved'].includes(
                          row.original.status,
                      ) &&
                          h(
                              Button,
                              {
                                  size: 'sm',
                                  variant: 'ghost',
                                  onClick: () =>
                                      window.confirm(
                                          trans('Cancel this payout?'),
                                      ) && act(cancel, row.original),
                              },
                              () => trans('Cancel'),
                          ),
                  ])
                : null,
    },
];
</script>

<template>
    <Head :title="$t('Payouts')" />

    <div class="flex flex-col gap-6 p-4 md:p-6">
        <Heading
            eyebrow="Finance"
            :title="$t('Payouts')"
            :description="
                $t(
                    'Bonuses, stipends and other payments to people. Money moves only when a payout is marked paid.',
                )
            "
        />

        <FinanceNav current="payouts" />

        <DataTable
            tour="payouts"
            :columns="columns"
            :rows="payouts"
            :search-placeholder="$t('Search by reason or reference')"
            :empty-text="$t('No payouts yet.')"
            :only="['payouts']"
        >
            <template #filters="{ filter }">
                <FormSelect
                    v-model="filter.status"
                    :class="filterClass"
                    :aria-label="$t('Status')"
                >
                    <FormOption :value="undefined">{{
                        $t('All statuses')
                    }}</FormOption>
                    <FormOption
                        v-for="status in statuses"
                        :key="status"
                        :value="status"
                    >
                        {{ $t(`finance.payout_status.${status}`) }}
                    </FormOption>
                </FormSelect>
                <FormSelect
                    v-model="filter.type"
                    :class="filterClass"
                    :aria-label="$t('Type')"
                >
                    <FormOption :value="undefined">{{
                        $t('All types')
                    }}</FormOption>
                    <FormOption v-for="type in types" :key="type" :value="type">
                        {{ $t(`finance.payout_type.${type}`) }}
                    </FormOption>
                </FormSelect>
            </template>
            <template v-if="can.edit" #actions>
                <Button data-test="new-payout" @click="open = true">
                    <Plus class="size-4" />
                    {{ $t('New payout') }}
                </Button>
            </template>
        </DataTable>

        <Dialog v-model:open="open">
            <DialogContent class="max-h-[90vh] overflow-y-auto sm:max-w-lg">
                <DialogHeader>
                    <DialogTitle>{{ $t('New payout') }}</DialogTitle>
                    <DialogDescription>{{
                        $t(
                            'From 5,000 LYD a payout needs two different approvers.',
                        )
                    }}</DialogDescription>
                </DialogHeader>
                <form class="grid gap-4" @submit.prevent="create">
                    <div class="grid gap-2">
                        <Label for="payout-person-search">{{
                            $t('Paid to')
                        }}</Label>
                        <SearchSelect
                            id="payout-person-search"
                            v-model="form.user_id"
                            :options="personOptions"
                            :placeholder="$t('Search people')"
                            :invalid="!!form.errors.user_id"
                        />
                        <InputError :message="form.errors.user_id" />
                    </div>
                    <div
                        v-if="payeeIsMinor"
                        class="grid gap-4 rounded-lg border border-amber-500/40 p-3 sm:grid-cols-2"
                    >
                        <p class="text-sm sm:col-span-2">
                            {{
                                $t(
                                    'This person is under 18. A guardian receives the payment.',
                                )
                            }}
                        </p>
                        <div class="grid gap-2">
                            <Label for="payout-guardian">{{
                                $t('Guardian name')
                            }}</Label>
                            <Input
                                id="payout-guardian"
                                v-model="form.guardian_name"
                                maxlength="160"
                            />
                            <InputError :message="form.errors.guardian_name" />
                        </div>
                        <div class="grid gap-2">
                            <Label for="payout-guardian-contact">{{
                                $t('Guardian contact')
                            }}</Label>
                            <Input
                                id="payout-guardian-contact"
                                v-model="form.guardian_contact"
                                maxlength="160"
                                dir="ltr"
                            />
                        </div>
                    </div>
                    <InputError v-else :message="form.errors.guardian_name" />
                    <div class="grid gap-4 sm:grid-cols-3">
                        <div class="grid gap-2">
                            <Label for="payout-type">{{ $t('Type') }}</Label>
                            <FormSelect id="payout-type" v-model="form.type">
                                <FormOption
                                    v-for="type in types"
                                    :key="type"
                                    :value="type"
                                >
                                    {{ $t(`finance.payout_type.${type}`) }}
                                </FormOption>
                            </FormSelect>
                        </div>
                        <div class="grid gap-2">
                            <Label for="payout-amount">{{
                                $t('Amount')
                            }}</Label>
                            <Input
                                id="payout-amount"
                                v-model="form.amount"
                                inputmode="decimal"
                                dir="ltr"
                                required
                            />
                            <InputError :message="form.errors.amount" />
                        </div>
                        <div class="grid gap-2">
                            <Label for="payout-currency">{{
                                $t('Currency')
                            }}</Label>
                            <FormSelect
                                id="payout-currency"
                                v-model="form.currency"
                                dir="ltr"
                            >
                                <FormOption
                                    v-for="currency in currencies"
                                    :key="currency"
                                    :value="currency"
                                >
                                    {{ currency }}
                                </FormOption>
                            </FormSelect>
                        </div>
                    </div>
                    <div v-if="form.currency !== 'LYD'" class="grid gap-2">
                        <Label for="payout-rate">{{
                            $t('Exchange rate to LYD')
                        }}</Label>
                        <Input
                            id="payout-rate"
                            v-model="form.rate_to_lyd"
                            inputmode="decimal"
                            dir="ltr"
                        />
                        <InputError :message="form.errors.rate_to_lyd" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="payout-source">{{ $t('Paid from') }}</Label>
                        <FormSelect
                            id="payout-source"
                            v-model="form.source_account_id"
                            required
                        >
                            <FormOption :value="null" disabled>
                                {{ $t('Choose an account') }}
                            </FormOption>
                            <FormOption
                                v-for="source in sources"
                                :key="source.id"
                                :value="source.id"
                            >
                                {{ source.name }}
                            </FormOption>
                        </FormSelect>
                        <InputError :message="form.errors.source_account_id" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="payout-reason">{{ $t('Reason') }}</Label>
                        <Input
                            id="payout-reason"
                            v-model="form.reason"
                            maxlength="500"
                            required
                        />
                        <InputError :message="form.errors.reason" />
                    </div>
                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            @click="open = false"
                            >{{ $t('Cancel') }}</Button
                        >
                        <Button
                            type="submit"
                            :disabled="form.processing || form.user_id === null"
                            >{{ $t('Save draft') }}</Button
                        >
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    </div>
</template>
