<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { Plus, Trash2 } from '@lucide/vue';
import { computed } from 'vue';
import FinanceNav from '@/components/finance/FinanceNav.vue';
import Money from '@/components/finance/Money.vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { dashboard as consoleDashboard } from '@/routes/console';
import { dashboard } from '@/routes/console/finance';
import { submit } from '@/routes/console/finance/receipts';

type MoneyValue = { amountMinor: number; currency: string; formatted: string };

const props = defineProps<{
    receipt: {
        id: number;
        number: string | null;
        date: string;
        source: string;
        payer: string;
        sponsorship: string | null;
        amount: MoneyValue;
        status: string;
        method: string;
        reference: string | null;
        inKindItems: { description: string; quantity: number; value: string }[];
        rejectionReason: string | null;
        createdBy: string | null;
    };
    proposal: { accountId: number; account: string; amount: string }[];
    lines: {
        id: number;
        account: string;
        amount: MoneyValue;
        status: string;
        number: string | null;
    }[];
    accounts: { id: number; name: string }[];
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

const decimals = props.receipt.amount.currency === 'LYD' ? 3 : 2;
const scale = 10 ** decimals;

const form = useForm({
    lines: props.proposal.map((line) => ({
        account_id: line.accountId as number | null,
        amount: line.amount,
    })),
});

/** Remaining in minor units, so the check is exact. */
const remainingMinor = computed(
    () =>
        props.receipt.amount.amountMinor -
        form.lines.reduce(
            (sum, line) => sum + Math.round((Number(line.amount) || 0) * scale),
            0,
        ),
);
const remaining = computed(() =>
    (remainingMinor.value / scale).toFixed(decimals),
);
const errors = computed(
    () => form.errors as Record<string, string | undefined>,
);

function send(): void {
    form.post(submit(props.receipt.id).url, { preserveScroll: true });
}

const selectClass =
    'h-9 rounded-md border border-input bg-transparent px-3 text-sm shadow-xs';
</script>

<template>
    <Head :title="receipt.number ?? $t('Receipt')" />

    <div class="flex flex-col gap-6 p-4 md:p-6">
        <div class="flex flex-wrap items-center gap-3">
            <Heading
                :title="receipt.number ?? $t('New receipt')"
                :description="`${receipt.payer}${receipt.sponsorship ? ' · ' + receipt.sponsorship : ''}`"
            />
            <Badge variant="secondary">{{
                $t(`finance.document_status.${receipt.status}`)
            }}</Badge>
        </div>

        <FinanceNav current="receipts" />

        <div class="grid gap-6 lg:grid-cols-[18rem_minmax(0,1fr)]">
            <dl
                class="grid content-start gap-2 rounded-xl border bg-card p-5 text-sm"
            >
                <dt class="text-muted-foreground">{{ $t('Amount') }}</dt>
                <dd class="text-xl font-semibold">
                    <Money :value="receipt.amount" />
                </dd>
                <dt class="text-muted-foreground">{{ $t('Date') }}</dt>
                <dd dir="ltr" class="text-start tabular-nums">
                    {{ receipt.date }}
                </dd>
                <dt class="text-muted-foreground">{{ $t('How') }}</dt>
                <dd>{{ $t(`finance.payment_method.${receipt.method}`) }}</dd>
                <template v-if="receipt.reference">
                    <dt class="text-muted-foreground">{{ $t('Reference') }}</dt>
                    <dd dir="ltr" class="text-start">
                        {{ receipt.reference }}
                    </dd>
                </template>
                <template v-if="receipt.createdBy">
                    <dt class="text-muted-foreground">
                        {{ $t('Recorded by') }}
                    </dt>
                    <dd>{{ receipt.createdBy }}</dd>
                </template>
                <template v-if="receipt.rejectionReason">
                    <dt class="text-muted-foreground">
                        {{ $t('Rejected because') }}
                    </dt>
                    <dd class="text-destructive">
                        {{ receipt.rejectionReason }}
                    </dd>
                </template>
                <template v-if="receipt.inKindItems.length">
                    <dt class="text-muted-foreground">
                        {{ $t('Items received') }}
                    </dt>
                    <dd>
                        <ul class="grid gap-1">
                            <li
                                v-for="(item, index) in receipt.inKindItems"
                                :key="index"
                            >
                                {{ item.description }} ×
                                <span dir="ltr">{{ item.quantity }}</span> ·
                                <span dir="ltr">{{ item.value }}</span>
                            </li>
                        </ul>
                    </dd>
                </template>
            </dl>

            <section
                v-if="receipt.status === 'draft'"
                class="grid content-start gap-3 rounded-xl border bg-card p-5"
            >
                <h3 class="font-semibold">
                    {{ $t('Split between accounts') }}
                </h3>
                <p class="text-sm text-muted-foreground">
                    {{
                        $t(
                            'This follows the sponsorship rules. Change it if needed; the lines must add up exactly.',
                        )
                    }}
                </p>
                <div
                    v-for="(line, index) in form.lines"
                    :key="index"
                    class="flex flex-wrap items-center gap-2"
                >
                    <select
                        v-model="line.account_id"
                        :class="selectClass"
                        class="min-w-48 flex-1"
                        :aria-label="$t('Account')"
                        :disabled="!can.edit"
                    >
                        <option
                            v-for="account in accounts"
                            :key="account.id"
                            :value="account.id"
                        >
                            {{ account.name }}
                        </option>
                    </select>
                    <Input
                        v-model="line.amount"
                        inputmode="decimal"
                        dir="ltr"
                        class="w-36 tabular-nums"
                        :aria-label="$t('Amount')"
                        :disabled="!can.edit"
                    />
                    <Button
                        v-if="can.edit"
                        type="button"
                        variant="ghost"
                        size="icon"
                        :aria-label="$t('Remove')"
                        @click="form.lines.splice(index, 1)"
                    >
                        <Trash2 class="size-4" />
                    </Button>
                </div>
                <Button
                    v-if="can.edit"
                    type="button"
                    variant="outline"
                    size="sm"
                    class="justify-self-start"
                    @click="
                        form.lines.push({
                            account_id: accounts[0]?.id ?? null,
                            amount: '',
                        })
                    "
                >
                    <Plus class="size-4" />
                    {{ $t('Add a line') }}
                </Button>
                <p
                    class="text-sm"
                    :class="
                        remainingMinor === 0
                            ? 'text-muted-foreground'
                            : 'font-medium text-destructive'
                    "
                >
                    {{
                        $t('Left to place: :amount', {
                            amount: `${remaining} ${receipt.amount.currency}`,
                        })
                    }}
                </p>
                <InputError
                    :message="
                        errors.lines ??
                        errors['lines.0.account_id'] ??
                        errors.status
                    "
                />
                <div v-if="can.edit" class="flex justify-end">
                    <Button
                        :disabled="
                            form.processing ||
                            remainingMinor !== 0 ||
                            form.lines.length === 0
                        "
                        data-test="submit-receipt"
                        @click="send"
                    >
                        {{ $t('Send for approval') }}
                    </Button>
                </div>
            </section>

            <section
                v-else
                class="grid content-start gap-3 rounded-xl border bg-card p-5"
            >
                <h3 class="font-semibold">
                    {{ $t('Split between accounts') }}
                </h3>
                <ul class="grid gap-2 text-sm">
                    <li
                        v-for="line in lines"
                        :key="line.id"
                        class="flex items-center justify-between gap-2 rounded-lg border p-3"
                    >
                        <span
                            >{{ line.account }}
                            <span
                                v-if="line.number"
                                class="ms-2 text-xs text-muted-foreground"
                                dir="ltr"
                                >{{ line.number }}</span
                            ></span
                        >
                        <Money :value="line.amount" />
                    </li>
                </ul>
                <p
                    v-if="receipt.status === 'submitted'"
                    class="text-sm text-muted-foreground"
                >
                    {{
                        $t(
                            'A finance approver posts it from the Approvals page.',
                        )
                    }}
                </p>
            </section>
        </div>
    </div>
</template>
