<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import FinanceNav from '@/components/finance/FinanceNav.vue';
import Money from '@/components/finance/Money.vue';
import SponsorshipForm from '@/components/finance/SponsorshipForm.vue';
import type {
    SponsorshipOptions,
    SponsorshipValue,
} from '@/components/finance/SponsorshipForm.vue';
import Heading from '@/components/Heading.vue';
import { Badge } from '@/components/ui/badge';
import { dashboard as consoleDashboard } from '@/routes/console';
import { dashboard } from '@/routes/console/finance';
import { show as showReceipt } from '@/routes/console/finance/receipts';
import { show as showSponsor } from '@/routes/console/finance/sponsors';

type MoneyValue = { amountMinor: number; currency: string; formatted: string };

defineProps<{
    sponsorship: SponsorshipValue & { sponsor: { id: number; name: string } };
    totals: {
        pledged: MoneyValue;
        received: MoneyValue;
        outstanding: MoneyValue;
    };
    receipts: {
        id: number;
        number: string | null;
        date: string;
        amount: MoneyValue;
        status: string;
        source: string;
    }[];
    options: SponsorshipOptions;
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
</script>

<template>
    <Head :title="sponsorship.title" />

    <div class="flex flex-col gap-6 p-4 md:p-6">
        <div class="flex flex-wrap items-center gap-3">
            <Heading
                :title="sponsorship.title"
                :description="sponsorship.sponsor.name"
            />
            <Badge variant="secondary">{{
                $t(`finance.sponsorship_status.${sponsorship.status}`)
            }}</Badge>
            <Link
                :href="showSponsor(sponsorship.sponsor.id)"
                class="text-sm text-muted-foreground hover:underline"
                >{{ $t('Open sponsor') }}</Link
            >
        </div>

        <FinanceNav current="sponsors" />

        <div class="grid gap-4 sm:grid-cols-3">
            <div
                v-for="[label, value] in [
                    ['Pledged', totals.pledged],
                    ['Received', totals.received],
                    ['Still to come', totals.outstanding],
                ] as const"
                :key="label"
                class="rounded-xl border bg-card p-4"
            >
                <p class="text-sm text-muted-foreground">{{ $t(label) }}</p>
                <p class="mt-1 text-xl font-semibold">
                    <Money :value="value" />
                </p>
            </div>
        </div>

        <div class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_24rem]">
            <section class="rounded-xl border bg-card p-5">
                <SponsorshipForm
                    :sponsor-id="sponsorship.sponsor.id"
                    :sponsorship="sponsorship"
                    :options="options"
                    :disabled="!can.edit"
                />
            </section>

            <section
                class="grid content-start gap-3 rounded-xl border bg-card p-5"
            >
                <h3 class="font-semibold">{{ $t('Receipts') }}</h3>
                <p
                    v-if="receipts.length === 0"
                    class="text-sm text-muted-foreground"
                >
                    {{ $t('Nothing received yet.') }}
                </p>
                <ul class="grid gap-2 text-sm">
                    <li v-for="receipt in receipts" :key="receipt.id">
                        <Link
                            :href="showReceipt(receipt.id)"
                            class="flex items-center justify-between gap-2 rounded-lg border p-3 hover:bg-accent"
                        >
                            <span>
                                <span dir="ltr" class="tabular-nums">{{
                                    receipt.number ?? receipt.date
                                }}</span>
                                <span
                                    class="ms-2 text-xs text-muted-foreground"
                                    >{{
                                        $t(
                                            `finance.document_status.${receipt.status}`,
                                        )
                                    }}</span
                                >
                            </span>
                            <Money :value="receipt.amount" />
                        </Link>
                    </li>
                </ul>
            </section>
        </div>
    </div>
</template>
