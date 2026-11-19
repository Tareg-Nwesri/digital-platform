<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { trans } from 'laravel-vue-i18n';
import FinanceNav from '@/components/finance/FinanceNav.vue';
import Heading from '@/components/Heading.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { formatDate, formatNumber } from '@/lib/i18n';
import { dashboard as consoleDashboard } from '@/routes/console';
import { dashboard } from '@/routes/console/finance';
import { close } from '@/routes/console/finance/periods';

type Period = {
    id: number;
    startsOn: string;
    endsOn: string;
    closedAt: string | null;
    posted: number;
};

defineProps<{
    periods: Period[];
    can: { close: boolean };
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Organisation console', href: consoleDashboard() },
            { title: 'Finance', href: dashboard() },
        ],
    },
});

function closePeriod(period: Period): void {
    if (
        window.confirm(
            trans(
                'Close this month? Nothing more can be posted to it, and this cannot be undone.',
            ),
        )
    ) {
        router.post(close(period.id).url, {}, { preserveScroll: true });
    }
}

const monthName = (date: string) =>
    formatDate(date, { month: 'long', year: 'numeric' });
</script>

<template>
    <Head :title="$t('Periods')" />

    <div class="flex flex-col gap-6 p-4 md:p-6">
        <Heading
            :title="$t('Finance')"
            :description="
                $t(
                    'Each month is a period. Close a month once its accounts are checked.',
                )
            "
        />

        <FinanceNav current="periods" :can-approve="can.close" />

        <ul class="grid gap-2" data-tour="periods-list">
            <li
                v-for="period in periods"
                :key="period.id"
                class="flex flex-wrap items-center justify-between gap-3 rounded-xl border bg-card px-4 py-3"
            >
                <div>
                    <p class="font-medium">{{ monthName(period.startsOn) }}</p>
                    <p class="text-xs text-muted-foreground">
                        {{
                            $t(':count posted entries', {
                                count: formatNumber(period.posted),
                            })
                        }}
                    </p>
                </div>
                <div class="flex items-center gap-2">
                    <Badge :variant="period.closedAt ? 'outline' : 'default'">{{
                        period.closedAt ? $t('Closed') : $t('Open')
                    }}</Badge>
                    <Button
                        v-if="can.close && !period.closedAt"
                        data-tour="periods-close"
                        size="sm"
                        variant="outline"
                        @click="closePeriod(period)"
                        >{{ $t('Close month') }}</Button
                    >
                </div>
            </li>
        </ul>
    </div>
</template>
