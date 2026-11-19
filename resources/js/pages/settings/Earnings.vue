<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import Money from '@/components/finance/Money.vue';
import Heading from '@/components/Heading.vue';
import { Badge } from '@/components/ui/badge';
import { earnings } from '@/routes/account';

type MoneyValue = { amountMinor: number; currency: string; formatted: string };

defineProps<{
    payouts: {
        id: number;
        type: string;
        amount: MoneyValue;
        status: string;
        reason: string;
        paidOn: string | null;
        reference: string | null;
    }[];
    totals: { year: number; amount: MoneyValue }[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'My earnings', href: earnings() }],
    },
});
</script>

<template>
    <Head :title="$t('My earnings')" />

    <div class="space-y-6">
        <Heading
            variant="small"
            :title="$t('My earnings')"
            :description="
                $t('Payments the foundation has approved or made to you.')
            "
        />

        <div
            v-if="totals.length > 0"
            class="grid gap-3 sm:grid-cols-2"
            data-tour="earnings-totals"
        >
            <div
                v-for="total in totals"
                :key="`${total.year}-${total.amount.currency}`"
                class="rounded-xl border bg-card p-4"
            >
                <p class="text-sm text-muted-foreground tabular-nums" dir="ltr">
                    {{ total.year }}
                </p>
                <p class="mt-1 text-xl font-semibold">
                    <Money :value="total.amount" />
                </p>
            </div>
        </div>

        <p v-if="payouts.length === 0" class="text-sm text-muted-foreground">
            {{ $t('No payments yet.') }}
        </p>

        <ul class="grid gap-2" data-tour="earnings-list">
            <li
                v-for="payout in payouts"
                :key="payout.id"
                class="flex flex-wrap items-start justify-between gap-3 rounded-xl border bg-card p-4 text-sm"
            >
                <div class="grid gap-0.5">
                    <p class="font-medium">
                        {{ $t(`finance.payout_type.${payout.type}`) }}
                    </p>
                    <p class="text-muted-foreground">{{ payout.reason }}</p>
                    <p
                        v-if="payout.paidOn"
                        class="text-xs text-muted-foreground"
                    >
                        <span dir="ltr" class="tabular-nums">{{
                            payout.paidOn
                        }}</span>
                        <template v-if="payout.reference">
                            ·
                            <span dir="ltr">{{
                                payout.reference
                            }}</span></template
                        >
                    </p>
                </div>
                <div class="grid justify-items-end gap-1">
                    <Money :value="payout.amount" />
                    <Badge
                        :variant="
                            payout.status === 'paid' ? 'default' : 'secondary'
                        "
                        >{{
                            $t(`finance.payout_status.${payout.status}`)
                        }}</Badge
                    >
                </div>
            </li>
        </ul>
    </div>
</template>
