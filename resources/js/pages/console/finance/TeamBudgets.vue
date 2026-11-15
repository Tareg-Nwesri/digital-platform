<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { ShieldCheck } from '@lucide/vue';
import { ref } from 'vue';
import FinanceNav from '@/components/finance/FinanceNav.vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { formatDate } from '@/lib/i18n';
import { review } from '@/routes/console/finance/budgets';
import {
    approve as approveExpense,
    receipt as receiptRoute,
    reject as rejectExpense,
} from '@/routes/console/finance/expenses';
import {
    approve as approveRequest,
    changes as changesRoute,
    reject as rejectRequest,
} from '@/routes/console/finance/requests';

type RequestRow = {
    id: number;
    team: string;
    project: string | null;
    status: string;
    amount: string;
    amountValue: string;
    approved: string | null;
    purpose: string;
    breakdown: { label: string; amount: string }[];
    source: string;
    requestedBy: string | null;
    note: string | null;
    mine: boolean;
    createdAt: string | null;
};

type ExpenseRow = {
    id: number;
    team: string | null;
    status: string;
    amount: string;
    vendor: string;
    budget: string;
    date: string;
    items: { description: string; amount: string }[];
    createdBy: string | null;
    reason: string | null;
    mine: boolean;
};

defineProps<{
    requests: RequestRow[];
    expenses: ExpenseRow[];
    can: { edit: boolean; approve: boolean };
    confirmed: boolean;
}>();

const statusVariant: Record<
    string,
    'default' | 'secondary' | 'destructive' | 'outline'
> = {
    submitted: 'secondary',
    changes_requested: 'outline',
    approved: 'default',
    partly_approved: 'default',
    posted: 'default',
    rejected: 'destructive',
    cancelled: 'outline',
    draft: 'outline',
};

type Decision =
    | { kind: 'approve-request'; row: RequestRow }
    | { kind: 'reject-request'; row: RequestRow }
    | { kind: 'changes'; row: RequestRow }
    | { kind: 'reject-expense'; row: ExpenseRow };

const deciding = ref<Decision | null>(null);
const form = useForm({ amount: '', note: '', reason: '' });

function open(decision: Decision): void {
    form.reset();
    form.clearErrors();

    if (decision.kind === 'approve-request') {
        form.amount = decision.row.amountValue;
    }

    deciding.value = decision;
}

function submit(): void {
    const decision = deciding.value;

    if (!decision) {
        return;
    }

    const options = {
        preserveScroll: true,
        onSuccess: () => (deciding.value = null),
    };

    switch (decision.kind) {
        case 'approve-request':
            form.transform((data) => ({
                amount: data.amount || null,
                note: data.note || null,
            })).post(approveRequest(decision.row.id).url, options);
            break;
        case 'reject-request':
            form.transform((data) => ({ reason: data.reason })).post(
                rejectRequest(decision.row.id).url,
                options,
            );
            break;
        case 'changes':
            form.transform((data) => ({ note: data.note })).post(
                changesRoute(decision.row.id).url,
                options,
            );
            break;
        case 'reject-expense':
            form.transform((data) => ({ reason: data.reason })).post(
                rejectExpense(decision.row.id).url,
                options,
            );
            break;
    }
}

function approve(row: ExpenseRow): void {
    router.post(approveExpense(row.id).url, {}, { preserveScroll: true });
}
</script>

<template>
    <Head :title="$t('Team budgets')" />

    <div class="flex flex-col gap-6 p-4 md:p-6">
        <FinanceNav current="budgets" :can-approve="can.approve" />
        <div class="flex flex-wrap items-start justify-between gap-3">
            <Heading
                :title="$t('Team budgets')"
                :description="
                    $t('Funding requests and expenses sent by the teams.')
                "
            />
            <Button v-if="can.approve && !confirmed" as-child>
                <Link :href="review().url" data-test="review-approve">
                    <ShieldCheck class="size-4" />
                    {{ $t('Review and approve') }}
                </Link>
            </Button>
        </div>

        <section class="grid gap-3 rounded-xl border bg-card p-5">
            <h2 class="font-semibold">{{ $t('Funding requests') }}</h2>
            <p
                v-if="requests.length === 0"
                class="text-sm text-muted-foreground"
            >
                {{ $t('No requests yet.') }}
            </p>
            <article
                v-for="row in requests"
                :key="row.id"
                class="grid gap-2 rounded-lg border p-4 text-sm"
                :data-test="`request-${row.id}`"
            >
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <span class="font-medium">
                        {{ row.team }}
                        <span v-if="row.project" class="text-muted-foreground">
                            · {{ row.project }}</span
                        >
                    </span>
                    <Badge :variant="statusVariant[row.status] ?? 'outline'">{{
                        $t(`finance.request_status.${row.status}`)
                    }}</Badge>
                </div>
                <p>
                    <span class="font-semibold tabular-nums" dir="ltr">{{
                        row.amount
                    }}</span>
                    · {{ row.source }}
                    <template v-if="row.approved">
                        · {{ $t('Approved') }}
                        <span dir="ltr">{{ row.approved }}</span></template
                    >
                </p>
                <p>{{ row.purpose }}</p>
                <ul
                    v-if="row.breakdown.length"
                    class="grid gap-1 text-xs text-muted-foreground"
                >
                    <li
                        v-for="(line, index) in row.breakdown"
                        :key="index"
                        class="flex justify-between gap-2"
                    >
                        <span>{{ line.label }}</span>
                        <span dir="ltr">{{ line.amount }}</span>
                    </li>
                </ul>
                <p class="text-xs text-muted-foreground">
                    {{ row.requestedBy }} · {{ formatDate(row.createdAt) }}
                </p>
                <p v-if="row.note" class="text-xs text-muted-foreground">
                    {{ row.note }}
                </p>
                <div
                    v-if="row.status === 'submitted'"
                    class="flex flex-wrap gap-2"
                >
                    <Button
                        v-if="confirmed && !row.mine"
                        size="sm"
                        @click="open({ kind: 'approve-request', row })"
                        >{{ $t('Approve') }}</Button
                    >
                    <Button
                        v-if="can.edit"
                        size="sm"
                        variant="outline"
                        @click="open({ kind: 'changes', row })"
                        >{{ $t('Ask for changes') }}</Button
                    >
                    <Button
                        v-if="can.edit"
                        size="sm"
                        variant="ghost"
                        class="text-destructive"
                        @click="open({ kind: 'reject-request', row })"
                        >{{ $t('Reject') }}</Button
                    >
                </div>
            </article>
        </section>

        <section class="grid gap-3 rounded-xl border bg-card p-5">
            <h2 class="font-semibold">{{ $t('Expenses') }}</h2>
            <p
                v-if="expenses.length === 0"
                class="text-sm text-muted-foreground"
            >
                {{ $t('No expenses yet.') }}
            </p>
            <article
                v-for="row in expenses"
                :key="row.id"
                class="grid gap-2 rounded-lg border p-4 text-sm"
                :data-test="`expense-${row.id}`"
            >
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <span class="font-medium"
                        >{{ row.team }} · {{ row.vendor }}</span
                    >
                    <Badge :variant="statusVariant[row.status] ?? 'outline'">{{
                        $t(`finance.document_status.${row.status}`)
                    }}</Badge>
                </div>
                <p>
                    <span class="font-semibold tabular-nums" dir="ltr">{{
                        row.amount
                    }}</span>
                    · {{ row.budget }} · {{ formatDate(row.date) }}
                </p>
                <ul class="grid gap-1 text-xs text-muted-foreground">
                    <li
                        v-for="(item, index) in row.items"
                        :key="index"
                        class="flex justify-between gap-2"
                    >
                        <span>{{ item.description }}</span>
                        <span dir="ltr">{{ item.amount }}</span>
                    </li>
                </ul>
                <p v-if="row.reason" class="text-xs text-destructive">
                    {{ row.reason }}
                </p>
                <div class="flex flex-wrap items-center gap-2">
                    <a
                        :href="receiptRoute(row.id).url"
                        class="text-xs underline"
                        >{{ $t('Receipt') }}</a
                    >
                    <template v-if="row.status === 'submitted'">
                        <Button
                            v-if="confirmed && !row.mine"
                            size="sm"
                            @click="approve(row)"
                            >{{ $t('Approve') }}</Button
                        >
                        <Button
                            v-if="can.edit"
                            size="sm"
                            variant="ghost"
                            class="text-destructive"
                            @click="open({ kind: 'reject-expense', row })"
                            >{{ $t('Reject') }}</Button
                        >
                    </template>
                </div>
            </article>
        </section>

        <Dialog
            :open="deciding !== null"
            @update:open="(value) => !value && (deciding = null)"
        >
            <DialogContent class="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>
                        {{
                            deciding?.kind === 'approve-request'
                                ? $t('Approve')
                                : deciding?.kind === 'changes'
                                  ? $t('Ask for changes')
                                  : $t('Reject')
                        }}
                    </DialogTitle>
                </DialogHeader>
                <form class="grid gap-4" @submit.prevent="submit">
                    <template v-if="deciding?.kind === 'approve-request'">
                        <div class="grid gap-2">
                            <Label for="decision-amount">{{
                                $t('Amount to approve')
                            }}</Label>
                            <Input
                                id="decision-amount"
                                v-model="form.amount"
                                inputmode="decimal"
                                dir="ltr"
                            />
                            <InputError :message="form.errors.amount" />
                        </div>
                        <div class="grid gap-2">
                            <Label for="decision-note">{{
                                $t('Note (optional)')
                            }}</Label>
                            <Input
                                id="decision-note"
                                v-model="form.note"
                                maxlength="1000"
                            />
                        </div>
                    </template>
                    <div
                        v-else-if="deciding?.kind === 'changes'"
                        class="grid gap-2"
                    >
                        <Label for="decision-changes">{{
                            $t('What should the team change?')
                        }}</Label>
                        <Input
                            id="decision-changes"
                            v-model="form.note"
                            maxlength="1000"
                            required
                        />
                        <InputError :message="form.errors.note" />
                    </div>
                    <div v-else class="grid gap-2">
                        <Label for="decision-reason">{{ $t('Reason') }}</Label>
                        <Input
                            id="decision-reason"
                            v-model="form.reason"
                            maxlength="500"
                            required
                        />
                        <InputError :message="form.errors.reason" />
                    </div>
                    <InputError
                        :message="
                            (form.errors as Record<string, string>).approver ??
                            (form.errors as Record<string, string>).status
                        "
                    />
                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            @click="deciding = null"
                            >{{ $t('Cancel') }}</Button
                        >
                        <Button type="submit" :disabled="form.processing">{{
                            $t('Confirm')
                        }}</Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    </div>
</template>
