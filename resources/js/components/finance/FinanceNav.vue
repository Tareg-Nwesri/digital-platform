<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { approvals, dashboard } from '@/routes/console/finance';
import { index as accounts } from '@/routes/console/finance/accounts';
import { index as periods } from '@/routes/console/finance/periods';
import { index as transactions } from '@/routes/console/finance/transactions';

defineProps<{
    current:
        | 'dashboard'
        | 'accounts'
        | 'transactions'
        | 'approvals'
        | 'periods';
    canApprove?: boolean;
    pending?: number;
}>();

const links = [
    ['dashboard', 'Overview', dashboard()],
    ['accounts', 'Accounts', accounts()],
    ['transactions', 'Transactions', transactions()],
    ['approvals', 'Approvals', approvals()],
    ['periods', 'Periods', periods()],
] as const;
</script>

<template>
    <nav class="flex gap-1 overflow-x-auto border-b">
        <template v-for="[key, label, href] in links" :key="key">
            <Link
                v-if="key !== 'approvals' || canApprove"
                :href="href"
                class="-mb-px flex items-center gap-2 border-b-2 px-3 py-2 text-sm font-medium whitespace-nowrap transition-colors"
                :class="
                    current === key
                        ? 'border-primary text-foreground'
                        : 'border-transparent text-muted-foreground hover:text-foreground'
                "
                :aria-current="current === key ? 'page' : undefined"
            >
                {{ $t(label) }}
                <span
                    v-if="key === 'approvals' && pending"
                    class="rounded-full bg-primary px-1.5 text-xs text-primary-foreground tabular-nums"
                    >{{ pending }}</span
                >
            </Link>
        </template>
    </nav>
</template>
