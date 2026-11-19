<script setup lang="ts">
import DatePicker from '@/components/forms/DatePicker.vue';
import FormOption from '@/components/forms/FormOption.vue';
import FormSelect from '@/components/forms/FormSelect.vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import { Plus } from '@lucide/vue';
import { trans } from 'laravel-vue-i18n';
import { computed, h, ref } from 'vue';
import DataTable from '@/components/data-table/DataTable.vue';
import FinanceNav from '@/components/finance/FinanceNav.vue';
import Money from '@/components/finance/Money.vue';
import TransactionStatusBadge from '@/components/finance/TransactionStatusBadge.vue';
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
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { dashboard as consoleDashboard } from '@/routes/console';
import { dashboard } from '@/routes/console/finance';
import { store, submit } from '@/routes/console/finance/transactions';
import type { ListColumn, TableResult } from '@/types/table';

type MoneyValue = { amountMinor: number; currency: string; formatted: string };
type TransactionRow = {
    id: number;
    number: string | null;
    date: string;
    description: string;
    type: string;
    status: string;
    from: string;
    to: string;
    amount: MoneyValue;
    overrideReason: string | null;
    rejectionReason: string | null;
};
type AccountOption = {
    id: number;
    name: string;
    currency: string;
    internal: boolean;
};

const props = defineProps<{
    transactions: TableResult<TransactionRow>;
    statuses: string[];
    types: string[];
    accounts: AccountOption[];
    can: { edit: boolean; approve: boolean };
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Organisation console', href: consoleDashboard() },
            { title: 'Finance', href: dashboard() },
        ],
    },
});

const open = ref(false);
const form = useForm({
    type: 'allocation',
    date: new Date().toISOString().slice(0, 10),
    from_account_id: null as number | null,
    to_account_id: null as number | null,
    amount: '',
    rate_to_lyd: '',
    description: '',
    reference: '',
});

const fromCurrency = computed(
    () =>
        props.accounts.find((account) => account.id === form.from_account_id)
            ?.currency,
);
const toOptions = computed(() =>
    props.accounts.filter(
        (account) =>
            account.id !== form.from_account_id &&
            (!fromCurrency.value || account.currency === fromCurrency.value),
    ),
);

function create(): void {
    form.transform((data) => ({
        ...data,
        rate_to_lyd: data.rate_to_lyd || null,
        reference: data.reference || null,
    })).post(store().url, {
        preserveScroll: true,
        onSuccess: () => {
            open.value = false;
            form.reset();
        },
    });
}

function submitForApproval(row: TransactionRow): void {
    router.post(submit(row.id).url, {}, { preserveScroll: true });
}

const filterClass =
    'h-9 rounded-md border border-input bg-transparent px-3 text-sm shadow-xs';

const columns: ListColumn<TransactionRow>[] = [
    {
        id: 'date',
        header: () => trans('Date'),
        meta: { sortable: true },
        cell: ({ row }) =>
            h('div', { class: 'grid' }, [
                h(
                    'span',
                    { class: 'tabular-nums', dir: 'ltr' },
                    row.original.date,
                ),
                row.original.number &&
                    h(
                        'span',
                        { class: 'text-xs text-muted-foreground', dir: 'ltr' },
                        row.original.number,
                    ),
            ]),
    },
    {
        id: 'description',
        header: () => trans('Details'),
        cell: ({ row }) =>
            h('div', { class: 'grid gap-0.5' }, [
                h('span', row.original.description),
                h(
                    'span',
                    { class: 'text-xs text-muted-foreground' },
                    trans(':from to :to', {
                        from: row.original.from,
                        to: row.original.to,
                    }),
                ),
                row.original.rejectionReason &&
                    h(
                        'span',
                        { class: 'text-xs text-destructive' },
                        row.original.rejectionReason,
                    ),
                row.original.overrideReason &&
                    h(
                        'span',
                        { class: 'text-xs text-amber-700 dark:text-amber-400' },
                        trans('Overdraft allowed: :reason', {
                            reason: row.original.overrideReason,
                        }),
                    ),
            ]),
    },
    {
        id: 'status',
        header: () => trans('Status'),
        cell: ({ row }) =>
            h('div', { class: 'grid gap-1' }, [
                h(TransactionStatusBadge, { status: row.original.status }),
                h(
                    'span',
                    { class: 'text-xs text-muted-foreground' },
                    trans(`finance.transaction_type.${row.original.type}`),
                ),
            ]),
    },
    {
        id: 'amount',
        header: () => trans('Amount'),
        cell: ({ row }) =>
            h(
                'div',
                { class: 'text-end' },
                h(Money, { value: row.original.amount }),
            ),
    },
    {
        id: 'actions',
        header: () => h('span', { class: 'sr-only' }, trans('Actions')),
        cell: ({ row }) =>
            props.can.edit && row.original.status === 'draft'
                ? h(
                      Button,
                      {
                          size: 'sm',
                          variant: 'outline',
                          onClick: () => submitForApproval(row.original),
                      },
                      () => trans('Send for approval'),
                  )
                : null,
    },
];
</script>

<template>
    <Head :title="$t('Transactions')" />

    <div class="flex flex-col gap-6 p-4 md:p-6">
        <Heading
            :title="$t('Finance')"
            :description="
                $t(
                    'Every movement between accounts. Posted entries never change; mistakes are reversed.',
                )
            "
        />

        <FinanceNav current="transactions" :can-approve="can.approve" />

        <DataTable
            tour="transactions"
            :columns="columns"
            :rows="transactions"
            :search-placeholder="$t('Search by number, details or reference')"
            :empty-text="$t('No transactions match.')"
            :only="['transactions']"
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
                        {{ $t(`finance.transaction_status.${status}`) }}
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
                        {{ $t(`finance.transaction_type.${type}`) }}
                    </FormOption>
                </FormSelect>
                <FormSelect
                    v-model="filter.account"
                    :class="filterClass"
                    :aria-label="$t('Account')"
                >
                    <FormOption :value="undefined">{{
                        $t('All accounts')
                    }}</FormOption>
                    <FormOption
                        v-for="account in accounts"
                        :key="account.id"
                        :value="String(account.id)"
                    >
                        {{ account.name }} ({{ account.currency }})
                    </FormOption>
                </FormSelect>
            </template>
            <template v-if="can.edit" #actions>
                <Button data-test="new-transaction" @click="open = true">
                    <Plus class="size-4" />
                    {{ $t('New movement') }}
                </Button>
            </template>
        </DataTable>

        <Dialog v-model:open="open">
            <DialogContent class="sm:max-w-lg">
                <DialogHeader>
                    <DialogTitle>{{ $t('New movement') }}</DialogTitle>
                    <DialogDescription>{{
                        $t(
                            'Saved as a draft. Another person approves it before the money moves.',
                        )
                    }}</DialogDescription>
                </DialogHeader>
                <form class="grid gap-4" @submit.prevent="create">
                    <div class="grid gap-2">
                        <Label for="tx-type">{{
                            $t('Kind of movement')
                        }}</Label>
                        <FormSelect id="tx-type" v-model="form.type">
                            <FormOption value="allocation">
                                {{ $t('finance.transaction_type.allocation') }}
                                — {{ $t('moving money between funds') }}
                            </FormOption>
                            <FormOption value="adjustment">
                                {{ $t('finance.transaction_type.adjustment') }}
                                — {{ $t('correcting a balance') }}
                            </FormOption>
                        </FormSelect>
                        <InputError :message="form.errors.type" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="tx-from">{{ $t('From account') }}</Label>
                        <FormSelect
                            id="tx-from"
                            v-model="form.from_account_id"
                            required
                        >
                            <FormOption :value="null" disabled>
                                {{ $t('Choose an account') }}
                            </FormOption>
                            <FormOption
                                v-for="account in accounts"
                                :key="account.id"
                                :value="account.id"
                            >
                                {{ account.name }} ({{ account.currency }})
                            </FormOption>
                        </FormSelect>
                        <InputError :message="form.errors.from_account_id" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="tx-to">{{ $t('To account') }}</Label>
                        <FormSelect
                            id="tx-to"
                            v-model="form.to_account_id"
                            required
                        >
                            <FormOption :value="null" disabled>
                                {{ $t('Choose an account') }}
                            </FormOption>
                            <FormOption
                                v-for="account in toOptions"
                                :key="account.id"
                                :value="account.id"
                            >
                                {{ account.name }} ({{ account.currency }})
                            </FormOption>
                        </FormSelect>
                        <InputError :message="form.errors.to_account_id" />
                    </div>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div class="grid gap-2">
                            <Label for="tx-amount"
                                >{{ $t('Amount') }}
                                <span v-if="fromCurrency" dir="ltr"
                                    >({{ fromCurrency }})</span
                                ></Label
                            >
                            <Input
                                id="tx-amount"
                                v-model="form.amount"
                                inputmode="decimal"
                                dir="ltr"
                                required
                            />
                            <InputError :message="form.errors.amount" />
                        </div>
                        <div class="grid gap-2">
                            <Label for="tx-date">{{ $t('Date') }}</Label>
                            <DatePicker
                                id="tx-date"
                                v-model="form.date"
                                required
                            />
                            <InputError :message="form.errors.date" />
                        </div>
                    </div>
                    <div
                        v-if="fromCurrency && fromCurrency !== 'LYD'"
                        class="grid gap-2"
                    >
                        <Label for="tx-rate">{{
                            $t('Exchange rate to LYD')
                        }}</Label>
                        <Input
                            id="tx-rate"
                            v-model="form.rate_to_lyd"
                            inputmode="decimal"
                            dir="ltr"
                        />
                        <InputError :message="form.errors.rate_to_lyd" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="tx-description">{{
                            $t('What is this for?')
                        }}</Label>
                        <Input
                            id="tx-description"
                            v-model="form.description"
                            required
                            maxlength="500"
                        />
                        <InputError :message="form.errors.description" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="tx-reference">{{
                            $t('Reference (bank, cheque, decision)')
                        }}</Label>
                        <Input
                            id="tx-reference"
                            v-model="form.reference"
                            maxlength="120"
                            dir="ltr"
                        />
                    </div>
                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            @click="open = false"
                            >{{ $t('Cancel') }}</Button
                        >
                        <Button type="submit" :disabled="form.processing">{{
                            $t('Save draft')
                        }}</Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    </div>
</template>
