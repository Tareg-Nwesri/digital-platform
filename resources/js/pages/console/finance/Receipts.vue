<script setup lang="ts">
import DatePicker from '@/components/forms/DatePicker.vue';
import FormOption from '@/components/forms/FormOption.vue';
import FormSelect from '@/components/forms/FormSelect.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { Plus, Trash2 } from '@lucide/vue';
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
import { show, store } from '@/routes/console/finance/receipts';
import type { ListColumn, TableResult } from '@/types/table';

type MoneyValue = { amountMinor: number; currency: string; formatted: string };
type ReceiptRow = {
    id: number;
    number: string | null;
    date: string;
    source: string;
    payer: string;
    sponsorship: string | null;
    amount: MoneyValue;
    status: string;
};

const props = defineProps<{
    receipts: TableResult<ReceiptRow>;
    statuses: string[];
    sources: string[];
    methods: string[];
    currencies: string[];
    sponsorships: {
        id: number;
        title: string;
        sponsor: string;
        currency: string;
    }[];
    can: { edit: boolean };
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
    sponsorship_id: null as number | null,
    source: 'sponsorship',
    payer_name: '',
    date: new Date().toISOString().slice(0, 10),
    amount: '',
    currency: 'LYD',
    rate_to_lyd: '',
    method: 'bank',
    reference: '',
    in_kind_items: [] as {
        description: string;
        quantity: number;
        value: string;
    }[],
});

const errors = computed(
    () => form.errors as Record<string, string | undefined>,
);
const inKind = computed(() => form.method === 'in_kind');

function chooseSponsorship(id: number | null): void {
    form.sponsorship_id = id;
    const sponsorship = props.sponsorships.find((item) => item.id === id);

    if (sponsorship) {
        form.currency = sponsorship.currency;
        form.source = 'sponsorship';
    }
}

function create(): void {
    form.transform((data) => ({
        ...data,
        payer_name: data.sponsorship_id ? null : data.payer_name || null,
        amount: inKind.value ? null : data.amount,
        rate_to_lyd: data.currency === 'LYD' ? null : data.rate_to_lyd || null,
        reference: data.reference || null,
        in_kind_items: inKind.value ? data.in_kind_items : null,
    })).post(store().url, { onSuccess: () => (open.value = false) });
}

const filterClass =
    'h-9 rounded-md border border-input bg-transparent px-3 text-sm shadow-xs';

const columns: ListColumn<ReceiptRow>[] = [
    {
        id: 'date',
        header: () => trans('Date'),
        meta: { sortable: true },
        cell: ({ row }) =>
            h(
                Link,
                {
                    href: show(row.original.id).url,
                    class: 'grid hover:underline',
                },
                () => [
                    h(
                        'span',
                        { class: 'tabular-nums', dir: 'ltr' },
                        row.original.date,
                    ),
                    row.original.number &&
                        h(
                            'span',
                            {
                                class: 'text-xs text-muted-foreground',
                                dir: 'ltr',
                            },
                            row.original.number,
                        ),
                ],
            ),
    },
    {
        id: 'payer',
        header: () => trans('From'),
        cell: ({ row }) =>
            h('div', { class: 'grid' }, [
                h('span', row.original.payer),
                h(
                    'span',
                    { class: 'text-xs text-muted-foreground' },
                    row.original.sponsorship ??
                        trans(`finance.income_source.${row.original.source}`),
                ),
            ]),
    },
    {
        id: 'status',
        header: () => trans('Status'),
        cell: ({ row }) =>
            h(
                Badge,
                {
                    variant:
                        row.original.status === 'posted'
                            ? 'default'
                            : 'secondary',
                },
                () => trans(`finance.document_status.${row.original.status}`),
            ),
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
];
</script>

<template>
    <Head :title="$t('Receipts')" />

    <div class="flex flex-col gap-6 p-4 md:p-6">
        <Heading
            :title="$t('Finance')"
            :description="
                $t(
                    'Money and gifts received. Each receipt is split into accounts, then approved.',
                )
            "
        />

        <FinanceNav current="receipts" />

        <DataTable
            :columns="columns"
            :rows="receipts"
            :search-placeholder="$t('Search by number or reference')"
            :empty-text="$t('No receipts yet.')"
            :only="['receipts']"
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
                        {{ $t(`finance.document_status.${status}`) }}
                    </FormOption>
                </FormSelect>
                <FormSelect
                    v-model="filter.source"
                    :class="filterClass"
                    :aria-label="$t('Source')"
                >
                    <FormOption :value="undefined">{{
                        $t('All sources')
                    }}</FormOption>
                    <FormOption
                        v-for="source in sources"
                        :key="source"
                        :value="source"
                    >
                        {{ $t(`finance.income_source.${source}`) }}
                    </FormOption>
                </FormSelect>
            </template>
            <template v-if="can.edit" #actions>
                <Button data-test="new-receipt" @click="open = true">
                    <Plus class="size-4" />
                    {{ $t('Record a receipt') }}
                </Button>
            </template>
        </DataTable>

        <Dialog v-model:open="open">
            <DialogContent class="max-h-[90vh] overflow-y-auto sm:max-w-xl">
                <DialogHeader>
                    <DialogTitle>{{ $t('Record a receipt') }}</DialogTitle>
                    <DialogDescription>{{
                        $t('Next you check how it is split between accounts.')
                    }}</DialogDescription>
                </DialogHeader>
                <form class="grid gap-4" @submit.prevent="create">
                    <div class="grid gap-2">
                        <Label for="receipt-sponsorship">{{
                            $t('Sponsorship')
                        }}</Label>
                        <FormSelect
                            id="receipt-sponsorship"
                            :model-value="form.sponsorship_id ?? ''"
                            @change="
                                (value) =>
                                    chooseSponsorship(
                                        value === '' || value === null
                                            ? null
                                            : Number(value),
                                    )
                            "
                        >
                            <FormOption value="">
                                {{ $t('Not from a sponsorship') }}
                            </FormOption>
                            <FormOption
                                v-for="item in sponsorships"
                                :key="item.id"
                                :value="item.id"
                            >
                                {{ item.sponsor }} · {{ item.title }}
                            </FormOption>
                        </FormSelect>
                        <InputError :message="form.errors.sponsorship_id" />
                    </div>
                    <template v-if="!form.sponsorship_id">
                        <div class="grid gap-4 sm:grid-cols-2">
                            <div class="grid gap-2">
                                <Label for="receipt-source">{{
                                    $t('Source')
                                }}</Label>
                                <FormSelect
                                    id="receipt-source"
                                    v-model="form.source"
                                >
                                    <FormOption
                                        v-for="source in sources.filter(
                                            (s) => s !== 'sponsorship',
                                        )"
                                        :key="source"
                                        :value="source"
                                    >
                                        {{
                                            $t(
                                                `finance.income_source.${source}`,
                                            )
                                        }}
                                    </FormOption>
                                </FormSelect>
                            </div>
                            <div class="grid gap-2">
                                <Label for="receipt-payer">{{
                                    $t('Received from')
                                }}</Label>
                                <Input
                                    id="receipt-payer"
                                    v-model="form.payer_name"
                                    maxlength="160"
                                />
                                <InputError :message="form.errors.payer_name" />
                            </div>
                        </div>
                    </template>
                    <div class="grid gap-4 sm:grid-cols-3">
                        <div class="grid gap-2">
                            <Label for="receipt-method">{{ $t('How') }}</Label>
                            <FormSelect
                                id="receipt-method"
                                v-model="form.method"
                            >
                                <FormOption
                                    v-for="method in methods"
                                    :key="method"
                                    :value="method"
                                >
                                    {{ $t(`finance.payment_method.${method}`) }}
                                </FormOption>
                            </FormSelect>
                        </div>
                        <div class="grid gap-2">
                            <Label for="receipt-date">{{ $t('Date') }}</Label>
                            <DatePicker
                                id="receipt-date"
                                v-model="form.date"
                                no-future
                            />
                            <InputError :message="form.errors.date" />
                        </div>
                        <div class="grid gap-2">
                            <Label for="receipt-currency">{{
                                $t('Currency')
                            }}</Label>
                            <FormSelect
                                id="receipt-currency"
                                v-model="form.currency"
                                dir="ltr"
                                :disabled="!!form.sponsorship_id"
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
                    <div v-if="!inKind" class="grid gap-4 sm:grid-cols-2">
                        <div class="grid gap-2">
                            <Label for="receipt-amount">{{
                                $t('Amount')
                            }}</Label>
                            <Input
                                id="receipt-amount"
                                v-model="form.amount"
                                inputmode="decimal"
                                dir="ltr"
                            />
                            <InputError :message="form.errors.amount" />
                        </div>
                        <div v-if="form.currency !== 'LYD'" class="grid gap-2">
                            <Label for="receipt-rate">{{
                                $t('Exchange rate to LYD')
                            }}</Label>
                            <Input
                                id="receipt-rate"
                                v-model="form.rate_to_lyd"
                                inputmode="decimal"
                                dir="ltr"
                            />
                            <InputError :message="form.errors.rate_to_lyd" />
                        </div>
                    </div>
                    <fieldset v-else class="grid gap-2 rounded-lg border p-3">
                        <legend class="px-1 text-sm font-medium">
                            {{ $t('Items received') }}
                        </legend>
                        <div
                            v-for="(item, index) in form.in_kind_items"
                            :key="index"
                            class="flex flex-wrap items-center gap-2"
                        >
                            <Input
                                v-model="item.description"
                                class="min-w-40 flex-1"
                                :placeholder="$t('Description')"
                                :aria-label="$t('Description')"
                            />
                            <Input
                                v-model.number="item.quantity"
                                type="number"
                                min="1"
                                class="w-20"
                                dir="ltr"
                                :aria-label="$t('Quantity')"
                            />
                            <Input
                                v-model="item.value"
                                inputmode="decimal"
                                class="w-28"
                                dir="ltr"
                                :placeholder="$t('Total value')"
                                :aria-label="$t('Total value')"
                            />
                            <Button
                                type="button"
                                variant="ghost"
                                size="icon"
                                :aria-label="$t('Remove')"
                                @click="form.in_kind_items.splice(index, 1)"
                            >
                                <Trash2 class="size-4" />
                            </Button>
                        </div>
                        <InputError
                            :message="
                                errors.in_kind_items ??
                                errors['in_kind_items.0.value'] ??
                                errors['in_kind_items.0.description']
                            "
                        />
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            class="justify-self-start"
                            @click="
                                form.in_kind_items.push({
                                    description: '',
                                    quantity: 1,
                                    value: '',
                                })
                            "
                        >
                            <Plus class="size-4" />
                            {{ $t('Add an item') }}
                        </Button>
                    </fieldset>
                    <div class="grid gap-2">
                        <Label for="receipt-reference">{{
                            $t('Reference (bank, cheque, decision)')
                        }}</Label>
                        <Input
                            id="receipt-reference"
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
                            $t('Continue')
                        }}</Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    </div>
</template>
