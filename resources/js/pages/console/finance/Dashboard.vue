<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { AlertTriangle } from '@lucide/vue';
import FinanceNav from '@/components/finance/FinanceNav.vue';
import Money from '@/components/finance/Money.vue';
import Heading from '@/components/Heading.vue';
import { dashboard as consoleDashboard } from '@/routes/console';
import { approvals, dashboard } from '@/routes/console/finance';
import { show as showAccount } from '@/routes/console/finance/accounts';
import { index as transactionsIndex } from '@/routes/console/finance/transactions';

type MoneyValue = { amountMinor: number; currency: string; formatted: string };

defineProps<{
    internal: {
        id: number;
        name: string;
        type: string;
        currency: string;
        restricted: boolean;
        balance: MoneyValue;
    }[];
    pending: { transactions: number; drafts: number };
    recent: {
        id: number;
        number: string | null;
        date: string;
        description: string;
        from: string;
        to: string;
        amount: MoneyValue;
    }[];
    mismatches: number;
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
</script>

<template>
    <Head :title="$t('Finance')" />

    <div class="flex flex-col gap-6 p-4 md:p-6">
        <Heading
            :title="$t('Finance')"
            :description="
                $t('Balances, pending approvals and the latest movements.')
            "
        />

        <FinanceNav
            current="dashboard"
            :can-approve="can.approve"
            :pending="pending.transactions"
        />

        <div
            v-if="mismatches > 0"
            class="flex items-start gap-3 rounded-xl border border-destructive/40 bg-destructive/10 p-4 text-sm"
            role="alert"
        >
            <AlertTriangle class="mt-0.5 size-4 shrink-0 text-destructive" />
            <p>
                {{
                    $t(
                        ':count account balances do not match their transactions. Tell the platform administrator.',
                        { count: String(mismatches) },
                    )
                }}
            </p>
        </div>

        <div class="grid gap-4 sm:grid-cols-2">
            <Link
                v-if="can.approve"
                :href="approvals()"
                class="rounded-xl border bg-card p-5 transition-colors hover:bg-accent"
            >
                <p class="text-sm text-muted-foreground">
                    {{ $t('Waiting for approval') }}
                </p>
                <p class="mt-1 text-3xl font-semibold tabular-nums">
                    {{ pending.transactions }}
                </p>
            </Link>
            <div v-else class="rounded-xl border bg-card p-5">
                <p class="text-sm text-muted-foreground">
                    {{ $t('Waiting for approval') }}
                </p>
                <p class="mt-1 text-3xl font-semibold tabular-nums">
                    {{ pending.transactions }}
                </p>
            </div>
            <Link
                :href="
                    transactionsIndex({
                        query: { filter: { status: 'draft' } },
                    })
                "
                class="rounded-xl border bg-card p-5 transition-colors hover:bg-accent"
            >
                <p class="text-sm text-muted-foreground">
                    {{ $t('Drafts not yet submitted') }}
                </p>
                <p class="mt-1 text-3xl font-semibold tabular-nums">
                    {{ pending.drafts }}
                </p>
            </Link>
        </div>

        <section class="grid gap-3">
            <h3 class="font-semibold">{{ $t('Foundation accounts') }}</h3>
            <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                <Link
                    v-for="account in internal"
                    :key="account.id"
                    :href="showAccount(account.id)"
                    class="rounded-xl border bg-card p-4 transition-colors hover:bg-accent"
                >
                    <p class="text-sm font-medium">{{ account.name }}</p>
                    <p class="text-xs text-muted-foreground">
                        {{ $t(`finance.account_type.${account.type}`)
                        }}<template v-if="account.restricted">
                            · {{ $t('Restricted') }}</template
                        >
                    </p>
                    <p class="mt-2 text-xl font-semibold">
                        <Money :value="account.balance" />
                    </p>
                </Link>
            </div>
        </section>

        <section class="grid gap-3">
            <h3 class="font-semibold">{{ $t('Latest posted movements') }}</h3>
            <div class="overflow-x-auto rounded-xl border bg-card">
                <table class="w-full text-sm">
                    <tbody>
                        <tr v-if="recent.length === 0">
                            <td
                                class="px-4 py-6 text-center text-muted-foreground"
                            >
                                {{ $t('Nothing posted yet.') }}
                            </td>
                        </tr>
                        <tr
                            v-for="row in recent"
                            :key="row.id"
                            class="border-b last:border-0"
                        >
                            <td
                                class="px-4 py-3 whitespace-nowrap text-muted-foreground tabular-nums"
                                dir="ltr"
                            >
                                {{ row.date }}
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
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</template>
