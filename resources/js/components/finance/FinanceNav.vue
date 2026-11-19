<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { approvals, dashboard } from '@/routes/console/finance';
import { index as accounts } from '@/routes/console/finance/accounts';
import { index as budgets } from '@/routes/console/finance/budgets';
import { index as payouts } from '@/routes/console/finance/payouts';
import { index as periods } from '@/routes/console/finance/periods';
import { index as receipts } from '@/routes/console/finance/receipts';
import { index as sponsors } from '@/routes/console/finance/sponsors';
import { index as transactions } from '@/routes/console/finance/transactions';

defineProps<{
    current:
        | 'dashboard'
        | 'accounts'
        | 'transactions'
        | 'sponsors'
        | 'receipts'
        | 'payouts'
        | 'budgets'
        | 'approvals'
        | 'periods';
    canApprove?: boolean;
    pending?: number;
}>();

const links = [
    ['dashboard', 'Overview', dashboard()],
    ['accounts', 'Accounts', accounts()],
    ['transactions', 'Transactions', transactions()],
    ['sponsors', 'Sponsors', sponsors()],
    ['receipts', 'Receipts', receipts()],
    ['payouts', 'Payouts', payouts()],
    ['budgets', 'Team budgets', budgets()],
    ['approvals', 'Approvals', approvals()],
    ['periods', 'Periods', periods()],
] as const;
</script>

<template>
    <nav data-tour="finance-nav" class="section-tabs">
        <template v-for="[key, label, href] in links" :key="key">
            <Link
                v-if="key !== 'approvals' || canApprove"
                :href="href"
                class="section-tab"
                :aria-current="current === key ? 'page' : undefined"
            >
                {{ $t(label) }}
                <span
                    v-if="key === 'approvals' && pending"
                    class="section-tab-count"
                    >{{ pending }}</span
                >
            </Link>
        </template>
    </nav>
</template>
