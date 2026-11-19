<script setup lang="ts">
import DatePicker from '@/components/forms/DatePicker.vue';
import FormOption from '@/components/forms/FormOption.vue';
import FormSelect from '@/components/forms/FormSelect.vue';
import { Head, router, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import FinanceNav from '@/components/finance/FinanceNav.vue';
import Money from '@/components/finance/Money.vue';
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
import {
    approve,
    reject,
    reverse,
} from '@/routes/console/finance/transactions';
import {
    approve as approvePayout,
    paid as markPayoutPaid,
    reject as rejectPayout,
} from '@/routes/console/finance/payouts';
import {
    post as postReceipt,
    reject as rejectReceipt,
} from '@/routes/console/finance/receipts';

type MoneyValue = { amountMinor: number; currency: string; formatted: string };
type Row = {
    id: number;
    number: string | null;
    date: string;
    description: string;
    type: string;
    from: string;
    to: string;
    amount: MoneyValue;
};
type Pending = Row & {
    createdBy: string | null;
    reference: string | null;
    fromBalance: MoneyValue;
    fromIsInternal: boolean;
    isOwn: boolean;
};

type PendingReceipt = {
    id: number;
    date: string;
    payer: string;
    sponsorship: string | null;
    source: string;
    amount: MoneyValue;
    createdBy: string | null;
    isOwn: boolean;
    lines: { account: string; amount: MoneyValue }[];
};

type PendingPayout = {
    id: number;
    payee: string;
    type: string;
    amount: MoneyValue;
    source: string;
    reason: string;
    guardian: string | null;
    needsSecondApproval: boolean;
    stage: 'approve' | 'second' | 'pay';
    isOwn: boolean;
};

const props = defineProps<{
    transactions: Pending[];
    receipts: PendingReceipt[];
    payouts: PendingPayout[];
    paymentMethods: string[];
    reversible: Row[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Organisation console', href: consoleDashboard() },
            { title: 'Finance', href: dashboard() },
            { title: 'Approvals' },
        ],
    },
});

const page = usePage();
const errors = computed(
    () => page.props.errors as Record<string, string | undefined>,
);
const pageError = computed(
    () =>
        errors.value.approver ??
        errors.value.amount ??
        errors.value.date ??
        errors.value.status ??
        errors.value.transaction ??
        errors.value.currency ??
        errors.value.account,
);

type Action =
    | 'approve'
    | 'reject'
    | 'reverse'
    | 'reject-receipt'
    | 'reject-payout';
const active = ref<{ row: Row; action: Action } | null>(null);
const reason = ref('');
const processing = ref(false);

const wouldOverdraw = (row: Pending) =>
    row.fromIsInternal && row.fromBalance.amountMinor < row.amount.amountMinor;

function quickApprove(row: Pending): void {
    router.post(approve(row.id).url, {}, { preserveScroll: true });
}

function approvePayoutNow(payout: PendingPayout): void {
    router.post(approvePayout(payout.id).url, {}, { preserveScroll: true });
}

function openPayoutRejection(payout: PendingPayout): void {
    open(
        {
            id: payout.id,
            number: null,
            date: '',
            description: `${payout.payee}: ${payout.reason}`,
            type: 'payout',
            from: payout.source,
            to: payout.payee,
            amount: payout.amount,
        },
        'reject-payout',
    );
}

const paying = ref<PendingPayout | null>(null);
const payment = ref({
    method: 'bank',
    reference: '',
    paid_on: new Date().toISOString().slice(0, 10),
});

function openPayment(payout: PendingPayout): void {
    paying.value = payout;
    payment.value = {
        method: props.paymentMethods[0] ?? 'bank',
        reference: '',
        paid_on: new Date().toISOString().slice(0, 10),
    };
}

function confirmPayment(): void {
    if (!paying.value) {
        return;
    }

    router.post(markPayoutPaid(paying.value.id).url, payment.value, {
        preserveScroll: true,
        onStart: () => (processing.value = true),
        onFinish: () => (processing.value = false),
        onSuccess: () => (paying.value = null),
    });
}

function approveReceipt(receipt: PendingReceipt): void {
    router.post(postReceipt(receipt.id).url, {}, { preserveScroll: true });
}

function openReceiptRejection(receipt: PendingReceipt): void {
    open(
        {
            id: receipt.id,
            number: null,
            date: receipt.date,
            description: receipt.sponsorship ?? receipt.payer,
            type: 'income',
            from: receipt.payer,
            to: '',
            amount: receipt.amount,
        },
        'reject-receipt',
    );
}

function open(row: Row, action: Action): void {
    active.value = { row, action };
    reason.value = '';
}

function confirm(): void {
    if (!active.value) {
        return;
    }

    const { row, action } = active.value;
    const route = {
        approve,
        reject,
        reverse,
        'reject-receipt': rejectReceipt,
        'reject-payout': rejectPayout,
    }[action];

    router.post(
        route(row.id).url,
        { reason: reason.value },
        {
            preserveScroll: true,
            onStart: () => (processing.value = true),
            onFinish: () => (processing.value = false),
            onSuccess: () => (active.value = null),
        },
    );
}

const titles: Record<Action, string> = {
    approve: 'Approve with an overdraft',
    reject: 'Reject transaction',
    reverse: 'Reverse transaction',
    'reject-receipt': 'Reject receipt',
    'reject-payout': 'Reject payout',
};
const descriptions: Record<Action, string> = {
    approve:
        'The account goes below zero. Only finance admins can allow this, and the reason is recorded.',
    reject: 'The person who prepared it sees this reason.',
    reverse:
        'An opposite entry is posted today and linked to the original. This cannot be undone.',
    'reject-receipt': 'The person who recorded it sees this reason.',
    'reject-payout': 'The person who prepared it sees this reason.',
};
</script>

<template>
    <Head :title="$t('Approvals')" />

    <div class="flex flex-col gap-6 p-4 md:p-6">
        <Heading
            eyebrow="Finance"
            :title="$t('Approvals')"
            :description="
                $t(
                    'Approve or reject what others prepared. You cannot approve your own work.',
                )
            "
        />

        <FinanceNav
            current="approvals"
            can-approve
            :pending="transactions.length + receipts.length + payouts.length"
        />

        <InputError :message="pageError" />

        <section
            v-if="payouts.length > 0"
            class="grid gap-3"
            data-tour="approvals-payouts"
        >
            <h3 class="font-semibold">{{ $t('Payouts') }}</h3>
            <ul class="grid gap-3">
                <li
                    v-for="payout in payouts"
                    :key="payout.id"
                    class="flex flex-wrap items-start justify-between gap-4 rounded-xl border bg-card p-4"
                    :data-test="`pending-payout-${payout.id}`"
                >
                    <div class="grid gap-1">
                        <p class="font-medium">{{ payout.payee }}</p>
                        <p class="text-sm text-muted-foreground">
                            {{ $t(`finance.payout_type.${payout.type}`) }} ·
                            {{ payout.reason }}
                        </p>
                        <p class="text-xs text-muted-foreground">
                            {{ $t('from :name', { name: payout.source }) }}
                        </p>
                        <p
                            v-if="payout.guardian"
                            class="text-xs text-muted-foreground"
                        >
                            {{
                                $t('Guardian: :name', { name: payout.guardian })
                            }}
                        </p>
                        <p class="text-xs font-medium">
                            {{
                                payout.stage === 'pay'
                                    ? $t('Approved; waiting to be paid')
                                    : payout.stage === 'second'
                                      ? $t('Waiting for the second approval')
                                      : $t('Waiting for approval')
                            }}
                        </p>
                    </div>
                    <div class="grid justify-items-end gap-2">
                        <p class="text-lg font-semibold">
                            <Money :value="payout.amount" />
                        </p>
                        <p
                            v-if="payout.isOwn && payout.stage !== 'pay'"
                            class="text-xs text-muted-foreground"
                        >
                            {{ $t('Someone else must approve this.') }}
                        </p>
                        <div v-else class="flex flex-wrap gap-2">
                            <Button
                                v-if="payout.stage === 'pay'"
                                size="sm"
                                :data-test="`pay-payout-${payout.id}`"
                                @click="openPayment(payout)"
                                >{{ $t('Mark as paid') }}</Button
                            >
                            <template v-else>
                                <Button
                                    size="sm"
                                    :data-test="`approve-payout-${payout.id}`"
                                    @click="approvePayoutNow(payout)"
                                    >{{ $t('Approve') }}</Button
                                >
                                <Button
                                    size="sm"
                                    variant="ghost"
                                    class="text-destructive"
                                    @click="openPayoutRejection(payout)"
                                    >{{ $t('Reject') }}</Button
                                >
                            </template>
                        </div>
                    </div>
                </li>
            </ul>
        </section>

        <Dialog
            :open="paying !== null"
            @update:open="(value) => !value && (paying = null)"
        >
            <DialogContent class="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>{{ $t('Mark as paid') }}</DialogTitle>
                    <DialogDescription>{{
                        $t(
                            'The money leaves the account now. Enter how it was paid.',
                        )
                    }}</DialogDescription>
                </DialogHeader>
                <p v-if="paying" class="text-sm">
                    {{ paying.payee }} · <Money :value="paying.amount" />
                </p>
                <div class="grid gap-3">
                    <div class="grid gap-1.5">
                        <Label for="payment-method">{{ $t('How') }}</Label>
                        <FormSelect
                            id="payment-method"
                            v-model="payment.method"
                            class="h-9 w-full rounded-md border border-input bg-transparent px-3 text-sm shadow-xs"
                        >
                            <FormOption
                                v-for="method in paymentMethods"
                                :key="method"
                                :value="method"
                            >
                                {{ $t(`finance.payment_method.${method}`) }}
                            </FormOption>
                        </FormSelect>
                    </div>
                    <div class="grid gap-1.5">
                        <Label for="payment-reference">{{
                            $t('Reference')
                        }}</Label>
                        <Input
                            id="payment-reference"
                            v-model="payment.reference"
                            dir="ltr"
                            maxlength="120"
                        />
                        <InputError :message="errors.reference" />
                    </div>
                    <div class="grid gap-1.5">
                        <Label for="payment-date">{{ $t('Paid on') }}</Label>
                        <DatePicker
                            id="payment-date"
                            v-model="payment.paid_on"
                            no-future
                        />
                        <InputError :message="errors.paid_on ?? pageError" />
                    </div>
                </div>
                <DialogFooter>
                    <Button variant="outline" @click="paying = null">{{
                        $t('Cancel')
                    }}</Button>
                    <Button
                        :disabled="
                            processing || payment.reference.trim() === ''
                        "
                        data-test="confirm-payment"
                        @click="confirmPayment"
                        >{{ $t('Mark as paid') }}</Button
                    >
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <section
            v-if="receipts.length > 0"
            class="grid gap-3"
            data-tour="approvals-receipts"
        >
            <h3 class="font-semibold">
                {{ $t('Receipts waiting for approval') }}
            </h3>
            <ul class="grid gap-3">
                <li
                    v-for="receipt in receipts"
                    :key="receipt.id"
                    class="flex flex-wrap items-start justify-between gap-4 rounded-xl border bg-card p-4"
                    :data-test="`pending-receipt-${receipt.id}`"
                >
                    <div class="grid gap-1">
                        <p class="font-medium">
                            {{
                                receipt.sponsorship ??
                                $t(`finance.income_source.${receipt.source}`)
                            }}
                        </p>
                        <p class="text-sm text-muted-foreground">
                            {{ $t('from :name', { name: receipt.payer }) }}
                        </p>
                        <ul
                            class="mt-1 grid gap-0.5 text-xs text-muted-foreground"
                        >
                            <li
                                v-for="(line, index) in receipt.lines"
                                :key="index"
                            >
                                {{ line.account }}:
                                <Money :value="line.amount" />
                            </li>
                        </ul>
                        <p class="text-xs text-muted-foreground">
                            {{
                                $t('Prepared by :name', {
                                    name: receipt.createdBy ?? '—',
                                })
                            }}
                        </p>
                    </div>
                    <div class="grid justify-items-end gap-2">
                        <p class="text-lg font-semibold">
                            <Money :value="receipt.amount" />
                        </p>
                        <p
                            v-if="receipt.isOwn"
                            class="text-xs text-muted-foreground"
                        >
                            {{
                                $t(
                                    'You prepared this; someone else must approve it.',
                                )
                            }}
                        </p>
                        <div v-else class="flex flex-wrap gap-2">
                            <Button
                                size="sm"
                                :data-test="`approve-receipt-${receipt.id}`"
                                @click="approveReceipt(receipt)"
                                >{{ $t('Approve') }}</Button
                            >
                            <Button
                                size="sm"
                                variant="ghost"
                                class="text-destructive"
                                @click="openReceiptRejection(receipt)"
                                >{{ $t('Reject') }}</Button
                            >
                        </div>
                    </div>
                </li>
            </ul>
        </section>

        <section class="grid gap-3" data-tour="approvals-transactions">
            <h3 class="font-semibold">{{ $t('Waiting for approval') }}</h3>
            <p
                v-if="transactions.length === 0"
                class="text-sm text-muted-foreground"
            >
                {{ $t('Nothing is waiting for approval.') }}
            </p>
            <ul class="grid gap-3">
                <li
                    v-for="row in transactions"
                    :key="row.id"
                    class="flex flex-wrap items-start justify-between gap-4 rounded-xl border bg-card p-4"
                    :data-test="`pending-${row.id}`"
                >
                    <div class="grid gap-1">
                        <p class="font-medium">{{ row.description }}</p>
                        <p class="text-sm text-muted-foreground">
                            {{
                                $t(':from to :to', {
                                    from: row.from,
                                    to: row.to,
                                })
                            }}
                        </p>
                        <p class="text-xs text-muted-foreground">
                            <span dir="ltr" class="tabular-nums">{{
                                row.date
                            }}</span>
                            ·
                            {{
                                $t('Prepared by :name', {
                                    name: row.createdBy ?? '—',
                                })
                            }}
                            <template v-if="row.reference">
                                ·
                                <span dir="ltr">{{
                                    row.reference
                                }}</span></template
                            >
                        </p>
                        <p
                            v-if="wouldOverdraw(row)"
                            class="text-xs text-amber-700 dark:text-amber-400"
                        >
                            {{
                                $t('Available in :account: :balance', {
                                    account: row.from,
                                    balance: row.fromBalance.formatted,
                                })
                            }}
                        </p>
                    </div>
                    <div class="grid justify-items-end gap-2">
                        <p class="text-lg font-semibold">
                            <Money :value="row.amount" />
                        </p>
                        <p
                            v-if="row.isOwn"
                            class="text-xs text-muted-foreground"
                        >
                            {{
                                $t(
                                    'You prepared this; someone else must approve it.',
                                )
                            }}
                        </p>
                        <div v-else class="flex flex-wrap gap-2">
                            <Button
                                v-if="!wouldOverdraw(row)"
                                size="sm"
                                :data-test="`approve-${row.id}`"
                                @click="quickApprove(row)"
                                >{{ $t('Approve') }}</Button
                            >
                            <Button
                                v-else
                                size="sm"
                                @click="open(row, 'approve')"
                                >{{ $t('Approve with reason…') }}</Button
                            >
                            <Button
                                size="sm"
                                variant="ghost"
                                class="text-destructive"
                                @click="open(row, 'reject')"
                                >{{ $t('Reject') }}</Button
                            >
                        </div>
                    </div>
                </li>
            </ul>
        </section>

        <section class="grid gap-3" data-tour="approvals-posted">
            <h3 class="font-semibold">{{ $t('Recently posted') }}</h3>
            <p class="text-sm text-muted-foreground">
                {{
                    $t(
                        'Posted entries never change. To correct one, reverse it and post the right entry.',
                    )
                }}
            </p>
            <div class="overflow-x-auto rounded-xl border bg-card">
                <table class="w-full text-sm">
                    <tbody>
                        <tr v-if="reversible.length === 0">
                            <td
                                class="px-4 py-6 text-center text-muted-foreground"
                            >
                                {{ $t('Nothing posted yet.') }}
                            </td>
                        </tr>
                        <tr
                            v-for="row in reversible"
                            :key="row.id"
                            class="border-b last:border-0"
                        >
                            <td
                                class="px-4 py-3 whitespace-nowrap tabular-nums"
                                dir="ltr"
                            >
                                {{ row.number }}
                            </td>
                            <td class="px-4 py-3">
                                <div>{{ row.description }}</div>
                                <div class="text-xs text-muted-foreground">
                                    {{
                                        $t(':from to :to', {
                                            from: row.from,
                                            to: row.to,
                                        })
                                    }}
                                </div>
                            </td>
                            <td class="px-4 py-3 text-end">
                                <Money :value="row.amount" />
                            </td>
                            <td class="px-4 py-3 text-end">
                                <Button
                                    size="sm"
                                    variant="ghost"
                                    @click="open(row, 'reverse')"
                                    >{{ $t('Reverse') }}</Button
                                >
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>

        <Dialog
            :open="active !== null"
            @update:open="(value) => !value && (active = null)"
        >
            <DialogContent class="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>{{
                        active ? $t(titles[active.action]) : ''
                    }}</DialogTitle>
                    <DialogDescription>{{
                        active ? $t(descriptions[active.action]) : ''
                    }}</DialogDescription>
                </DialogHeader>
                <p v-if="active" class="text-sm">
                    {{ active.row.description }} ·
                    <Money :value="active.row.amount" />
                </p>
                <div class="grid gap-2">
                    <Label for="finance-reason">{{ $t('Reason') }}</Label>
                    <Input
                        id="finance-reason"
                        v-model="reason"
                        maxlength="500"
                    />
                    <InputError :message="errors.reason ?? pageError" />
                </div>
                <DialogFooter>
                    <Button variant="outline" @click="active = null">{{
                        $t('Cancel')
                    }}</Button>
                    <Button
                        :variant="
                            active?.action === 'approve'
                                ? 'default'
                                : 'destructive'
                        "
                        :disabled="processing || reason.trim() === ''"
                        data-test="confirm-finance-action"
                        @click="confirm"
                    >
                        {{
                            active?.action === 'approve'
                                ? $t('Approve')
                                : active?.action === 'reverse'
                                  ? $t('Reverse')
                                  : $t('Reject')
                        }}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </div>
</template>
