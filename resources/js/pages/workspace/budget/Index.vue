<script setup lang="ts">
import DatePicker from '@/components/forms/DatePicker.vue';
import FormOption from '@/components/forms/FormOption.vue';
import FormSelect from '@/components/forms/FormSelect.vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import { Plus, Receipt, Trash2, Wallet } from '@lucide/vue';
import { trans } from 'laravel-vue-i18n';
import { computed, ref } from 'vue';
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
import {
    cancel as cancelRoute,
    store as storeRequest,
    update as updateRequest,
} from '@/routes/workspace/budget/requests';
import {
    receipt as receiptRoute,
    store as storeExpense,
} from '@/routes/workspace/budget/expenses';

type FundingRow = {
    id: number;
    status: string;
    amount: string;
    amountValue: string;
    currency: string;
    approved: string | null;
    purpose: string;
    project: string | null;
    projectId: number | null;
    source: string;
    sourceId: number;
    breakdown: { label: string; amount: string }[];
    note: string | null;
    createdAt: string | null;
};

const props = defineProps<{
    team: { slug: string; name: string };
    budgets: {
        id: number;
        name: string;
        currency: string;
        balance: string;
        funded: string;
        spent: string;
    }[];
    requests: FundingRow[];
    expenses: {
        id: number;
        status: string;
        amount: string;
        vendor: string;
        budget: string;
        date: string;
        items: number;
        reason: string | null;
    }[];
    sources: { id: number; name: string; currency: string }[];
    projects: { id: number; title: string }[];
    vendors: { id: number; name: string }[];
    currencies: string[];
    can: { request: boolean; spend: boolean };
}>();

const statusVariant: Record<
    string,
    'default' | 'secondary' | 'destructive' | 'outline'
> = {
    draft: 'outline',
    submitted: 'secondary',
    changes_requested: 'outline',
    approved: 'default',
    partly_approved: 'default',
    posted: 'default',
    rejected: 'destructive',
    cancelled: 'outline',
};

/* Funding request */
const requestOpen = ref(false);
const editing = ref<FundingRow | null>(null);
const requestForm = useForm({
    source_account_id: props.sources[0]?.id ?? (null as number | null),
    project_id: null as number | null,
    amount: '',
    currency: props.sources[0]?.currency ?? 'LYD',
    purpose: '',
    breakdown: [] as { label: string; amount: string }[],
});

function openRequest(row: FundingRow | null = null): void {
    editing.value = row;
    requestForm.clearErrors();

    if (row) {
        requestForm.source_account_id = row.sourceId;
        requestForm.project_id = row.projectId;
        requestForm.amount = row.amountValue;
        requestForm.currency = row.currency;
        requestForm.purpose = row.purpose;
        requestForm.breakdown = row.breakdown.map((line) => ({ ...line }));
    } else {
        requestForm.reset();
    }

    requestOpen.value = true;
}

function pickSource(): void {
    const source = props.sources.find(
        (s) => s.id === requestForm.source_account_id,
    );

    if (source) {
        requestForm.currency = source.currency;
    }
}

function sendRequest(): void {
    const options = {
        preserveScroll: true,
        onSuccess: () => {
            requestOpen.value = false;
            requestForm.reset();
        },
    };

    if (editing.value) {
        requestForm.put(
            updateRequest([props.team.slug, editing.value.id]).url,
            options,
        );
    } else {
        requestForm.post(storeRequest(props.team.slug).url, options);
    }
}

function cancelRequest(row: FundingRow): void {
    if (window.confirm(trans('Cancel this funding request?'))) {
        router.post(
            cancelRoute([props.team.slug, row.id]).url,
            {},
            { preserveScroll: true },
        );
    }
}

/* Expense */
const expenseOpen = ref(false);
const expenseForm = useForm({
    budget_account_id: props.budgets[0]?.id ?? (null as number | null),
    vendor_id: null as number | null,
    vendor_name: '',
    date: new Date().toISOString().slice(0, 10),
    amount: '',
    items: [{ description: '', amount: '' }] as {
        description: string;
        amount: string;
    }[],
    receipt: null as File | null,
});

const itemsTotal = computed(() =>
    expenseForm.items
        .reduce((sum, item) => sum + (Number.parseFloat(item.amount) || 0), 0)
        .toFixed(3),
);

function sendExpense(): void {
    expenseForm
        .transform((data) => ({
            ...data,
            amount: itemsTotal.value,
            vendor_name: data.vendor_id ? null : data.vendor_name || null,
        }))
        .post(storeExpense(props.team.slug).url, {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => {
                expenseOpen.value = false;
                expenseForm.reset();
            },
        });
}

const errorsOf = (form: { errors: object }): Record<string, string> =>
    form.errors as Record<string, string>;
</script>

<template>
    <Head :title="$t('Budget')" />

    <div class="flex flex-col gap-6 p-4 md:p-6">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <Heading
                :title="$t('Budget')"
                :description="
                    $t(
                        'Ask the foundation for funding, and record what you spend with its receipt.',
                    )
                "
            />
            <div class="flex flex-wrap gap-2">
                <Button
                    v-if="can.request && sources.length"
                    data-test="new-request"
                    @click="openRequest()"
                >
                    <Wallet class="size-4" />
                    {{ $t('Request funding') }}
                </Button>
                <Button
                    v-if="can.spend"
                    variant="outline"
                    data-test="new-expense"
                    @click="expenseOpen = true"
                >
                    <Receipt class="size-4" />
                    {{ $t('Record an expense') }}
                </Button>
            </div>
        </div>

        <p
            v-if="budgets.length === 0"
            class="rounded-xl border bg-card p-5 text-sm text-muted-foreground"
        >
            {{
                $t(
                    'Your team has no budget yet. It is created when your first funding request is approved.',
                )
            }}
        </p>
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <div
                v-for="budget in budgets"
                :key="budget.id"
                class="grid gap-2 rounded-xl border bg-card p-5"
                :data-test="`budget-${budget.id}`"
            >
                <p class="text-sm text-muted-foreground">{{ budget.name }}</p>
                <p class="text-3xl font-semibold tabular-nums" dir="ltr">
                    {{ budget.balance }}
                </p>
                <p class="text-xs text-muted-foreground">
                    {{ $t('Funded') }}
                    <span dir="ltr">{{ budget.funded }}</span>
                    · {{ $t('Spent') }}
                    <span dir="ltr">{{ budget.spent }}</span>
                </p>
            </div>
        </div>

        <div class="grid gap-6 lg:grid-cols-2">
            <section
                class="grid content-start gap-3 rounded-xl border bg-card p-5"
            >
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
                    class="grid gap-2 rounded-lg border p-3 text-sm"
                    :data-test="`request-${row.id}`"
                >
                    <div
                        class="flex flex-wrap items-center justify-between gap-2"
                    >
                        <span class="font-medium tabular-nums" dir="ltr">{{
                            row.amount
                        }}</span>
                        <Badge
                            :variant="statusVariant[row.status] ?? 'outline'"
                            >{{
                                $t(`finance.request_status.${row.status}`)
                            }}</Badge
                        >
                    </div>
                    <p>{{ row.purpose }}</p>
                    <p class="text-xs text-muted-foreground">
                        {{ row.project ?? team.name }} · {{ row.source }} ·
                        {{ formatDate(row.createdAt) }}
                    </p>
                    <p v-if="row.approved" class="text-xs">
                        {{ $t('Approved') }}:
                        <span dir="ltr">{{ row.approved }}</span>
                    </p>
                    <p v-if="row.note" class="text-xs text-muted-foreground">
                        {{ row.note }}
                    </p>
                    <div
                        v-if="
                            [
                                'draft',
                                'submitted',
                                'changes_requested',
                            ].includes(row.status)
                        "
                        class="flex flex-wrap gap-2"
                    >
                        <Button
                            v-if="row.status === 'changes_requested'"
                            size="sm"
                            @click="openRequest(row)"
                            >{{ $t('Edit and resend') }}</Button
                        >
                        <Button
                            size="sm"
                            variant="ghost"
                            class="text-destructive"
                            @click="cancelRequest(row)"
                            >{{ $t('Cancel request') }}</Button
                        >
                    </div>
                </article>
            </section>

            <section
                class="grid content-start gap-3 rounded-xl border bg-card p-5"
            >
                <h2 class="font-semibold">{{ $t('Expenses') }}</h2>
                <p
                    v-if="expenses.length === 0"
                    class="text-sm text-muted-foreground"
                >
                    {{ $t('No expenses yet.') }}
                </p>
                <article
                    v-for="expense in expenses"
                    :key="expense.id"
                    class="grid gap-1 rounded-lg border p-3 text-sm"
                    :data-test="`expense-${expense.id}`"
                >
                    <div
                        class="flex flex-wrap items-center justify-between gap-2"
                    >
                        <span class="font-medium">{{ expense.vendor }}</span>
                        <Badge
                            :variant="
                                statusVariant[expense.status] ?? 'outline'
                            "
                            >{{
                                $t(`finance.document_status.${expense.status}`)
                            }}</Badge
                        >
                    </div>
                    <p class="text-xs text-muted-foreground">
                        <span dir="ltr">{{ expense.amount }}</span> ·
                        {{ expense.budget }} · {{ formatDate(expense.date) }}
                    </p>
                    <p v-if="expense.reason" class="text-xs text-destructive">
                        {{ expense.reason }}
                    </p>
                    <a
                        :href="receiptRoute([team.slug, expense.id]).url"
                        class="text-xs underline"
                        >{{ $t('Receipt') }}</a
                    >
                </article>
            </section>
        </div>

        <Dialog v-model:open="requestOpen">
            <DialogContent class="sm:max-w-lg">
                <DialogHeader>
                    <DialogTitle>{{
                        editing ? $t('Edit and resend') : $t('Request funding')
                    }}</DialogTitle>
                </DialogHeader>
                <form class="grid gap-4" @submit.prevent="sendRequest">
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div class="grid gap-2">
                            <Label for="request-source">{{ $t('From') }}</Label>
                            <FormSelect
                                id="request-source"
                                v-model="requestForm.source_account_id"
                                @change="pickSource"
                            >
                                <FormOption
                                    v-for="source in sources"
                                    :key="source.id"
                                    :value="source.id"
                                >
                                    {{ source.name }} ({{ source.currency }})
                                </FormOption>
                            </FormSelect>
                            <InputError
                                :message="requestForm.errors.source_account_id"
                            />
                        </div>
                        <div class="grid gap-2">
                            <Label for="request-project">{{ $t('For') }}</Label>
                            <FormSelect
                                id="request-project"
                                v-model="requestForm.project_id"
                            >
                                <FormOption :value="null">
                                    {{ $t('The whole team') }}
                                </FormOption>
                                <FormOption
                                    v-for="project in projects"
                                    :key="project.id"
                                    :value="project.id"
                                >
                                    {{ project.title }}
                                </FormOption>
                            </FormSelect>
                            <InputError
                                :message="errorsOf(requestForm).project_id"
                            />
                        </div>
                    </div>
                    <div class="grid gap-2">
                        <Label for="request-amount"
                            >{{ $t('Amount') }} ({{
                                requestForm.currency
                            }})</Label
                        >
                        <Input
                            id="request-amount"
                            v-model="requestForm.amount"
                            inputmode="decimal"
                            required
                            dir="ltr"
                        />
                        <InputError :message="requestForm.errors.amount" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="request-purpose">{{
                            $t('What is it for?')
                        }}</Label>
                        <textarea
                            id="request-purpose"
                            v-model="requestForm.purpose"
                            rows="3"
                            maxlength="1000"
                            required
                            class="rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs"
                        />
                        <InputError :message="requestForm.errors.purpose" />
                    </div>
                    <fieldset class="grid gap-2">
                        <legend class="mb-1 text-sm font-medium">
                            {{ $t('Breakdown (optional)') }}
                        </legend>
                        <div
                            v-for="(line, index) in requestForm.breakdown"
                            :key="index"
                            class="grid grid-cols-[1fr_8rem_auto] gap-2"
                        >
                            <Input
                                v-model="line.label"
                                :aria-label="$t('Item')"
                                :placeholder="$t('Item')"
                                maxlength="120"
                            />
                            <Input
                                v-model="line.amount"
                                :aria-label="$t('Amount')"
                                inputmode="decimal"
                                dir="ltr"
                            />
                            <Button
                                type="button"
                                variant="ghost"
                                size="icon"
                                :aria-label="$t('Remove')"
                                @click="requestForm.breakdown.splice(index, 1)"
                            >
                                <Trash2 class="size-4" />
                            </Button>
                        </div>
                        <div>
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                @click="
                                    requestForm.breakdown.push({
                                        label: '',
                                        amount: '',
                                    })
                                "
                            >
                                <Plus class="size-4" />
                                {{ $t('Add item') }}
                            </Button>
                        </div>
                    </fieldset>
                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            @click="requestOpen = false"
                            >{{ $t('Cancel') }}</Button
                        >
                        <Button
                            type="submit"
                            :disabled="requestForm.processing"
                            >{{ $t('Send request') }}</Button
                        >
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>

        <Dialog v-model:open="expenseOpen">
            <DialogContent class="sm:max-w-lg">
                <DialogHeader>
                    <DialogTitle>{{ $t('Record an expense') }}</DialogTitle>
                </DialogHeader>
                <form class="grid gap-4" @submit.prevent="sendExpense">
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div class="grid gap-2">
                            <Label for="expense-budget">{{
                                $t('Budget')
                            }}</Label>
                            <FormSelect
                                id="expense-budget"
                                v-model="expenseForm.budget_account_id"
                            >
                                <FormOption
                                    v-for="budget in budgets"
                                    :key="budget.id"
                                    :value="budget.id"
                                >
                                    {{ budget.name }} ({{ budget.currency }})
                                </FormOption>
                            </FormSelect>
                            <InputError
                                :message="expenseForm.errors.budget_account_id"
                            />
                        </div>
                        <div class="grid gap-2">
                            <Label for="expense-date">{{ $t('Date') }}</Label>
                            <DatePicker
                                id="expense-date"
                                v-model="expenseForm.date"
                                required
                                no-future
                            />
                            <InputError :message="expenseForm.errors.date" />
                        </div>
                    </div>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div class="grid gap-2">
                            <Label for="expense-vendor">{{
                                $t('Vendor')
                            }}</Label>
                            <FormSelect
                                id="expense-vendor"
                                v-model="expenseForm.vendor_id"
                            >
                                <FormOption :value="null">
                                    {{ $t('Not in the list') }}
                                </FormOption>
                                <FormOption
                                    v-for="vendor in vendors"
                                    :key="vendor.id"
                                    :value="vendor.id"
                                >
                                    {{ vendor.name }}
                                </FormOption>
                            </FormSelect>
                        </div>
                        <div v-if="!expenseForm.vendor_id" class="grid gap-2">
                            <Label for="expense-vendor-name">{{
                                $t('Vendor name')
                            }}</Label>
                            <Input
                                id="expense-vendor-name"
                                v-model="expenseForm.vendor_name"
                                maxlength="160"
                            />
                        </div>
                    </div>
                    <InputError :message="expenseForm.errors.vendor_name" />
                    <fieldset class="grid gap-2">
                        <legend class="mb-1 text-sm font-medium">
                            {{ $t('Items') }}
                        </legend>
                        <div
                            v-for="(item, index) in expenseForm.items"
                            :key="index"
                            class="grid grid-cols-[1fr_8rem_auto] gap-2"
                        >
                            <Input
                                v-model="item.description"
                                :aria-label="$t('Item')"
                                :placeholder="$t('Item')"
                                maxlength="160"
                                required
                            />
                            <Input
                                v-model="item.amount"
                                :aria-label="$t('Amount')"
                                inputmode="decimal"
                                dir="ltr"
                                required
                            />
                            <Button
                                type="button"
                                variant="ghost"
                                size="icon"
                                :disabled="expenseForm.items.length === 1"
                                :aria-label="$t('Remove')"
                                @click="expenseForm.items.splice(index, 1)"
                            >
                                <Trash2 class="size-4" />
                            </Button>
                        </div>
                        <div class="flex items-center justify-between gap-2">
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                @click="
                                    expenseForm.items.push({
                                        description: '',
                                        amount: '',
                                    })
                                "
                            >
                                <Plus class="size-4" />
                                {{ $t('Add item') }}
                            </Button>
                            <span
                                class="text-sm font-medium tabular-nums"
                                dir="ltr"
                            >
                                {{ itemsTotal }}
                            </span>
                        </div>
                        <InputError
                            :message="
                                expenseForm.errors.items ??
                                expenseForm.errors.amount
                            "
                        />
                    </fieldset>
                    <div class="grid gap-2">
                        <Label for="expense-receipt">{{
                            $t('Receipt (PDF or photo)')
                        }}</Label>
                        <input
                            id="expense-receipt"
                            type="file"
                            required
                            accept=".pdf,.jpg,.jpeg,.png"
                            class="text-sm file:me-3 file:rounded-md file:border file:bg-background file:px-3 file:py-1.5 file:text-sm"
                            @change="
                                expenseForm.receipt =
                                    ($event.target as HTMLInputElement)
                                        .files?.[0] ?? null
                            "
                        />
                        <InputError :message="expenseForm.errors.receipt" />
                    </div>
                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            @click="expenseOpen = false"
                            >{{ $t('Cancel') }}</Button
                        >
                        <Button
                            type="submit"
                            :disabled="expenseForm.processing"
                            >{{ $t('Send to finance') }}</Button
                        >
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    </div>
</template>
