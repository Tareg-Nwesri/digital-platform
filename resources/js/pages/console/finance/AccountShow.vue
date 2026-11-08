<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import FinanceNav from '@/components/finance/FinanceNav.vue';
import Money from '@/components/finance/Money.vue';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { dashboard as consoleDashboard } from '@/routes/console';
import { dashboard } from '@/routes/console/finance';
import { show } from '@/routes/console/finance/accounts';

type MoneyValue = { amountMinor: number; currency: string; formatted: string };

const props = defineProps<{
    account: {
        id: number;
        name: string;
        type: string;
        currency: string;
        restricted: boolean;
        internal: boolean;
        purpose: string | null;
        archived: boolean;
        balance: MoneyValue;
    };
    statement: {
        from: string;
        to: string;
        opening: MoneyValue;
        closing: MoneyValue;
        truncated: boolean;
        rows: {
            id: number;
            number: string | null;
            date: string;
            description: string;
            type: string;
            counterpart: string;
            direction: 'in' | 'out';
            amount: MoneyValue;
            running: MoneyValue;
            reversed: boolean;
        }[];
    };
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Organisation console', href: consoleDashboard() },
            { title: 'Finance', href: dashboard() },
        ],
    },
});

const from = ref(props.statement.from);
const to = ref(props.statement.to);

function apply(): void {
    router.get(
        show(props.account.id).url,
        { from: from.value, to: to.value },
        { preserveScroll: true, replace: true },
    );
}
</script>

<template>
    <Head :title="account.name" />

    <div class="flex flex-col gap-6 p-4 md:p-6">
        <div class="flex flex-wrap items-end justify-between gap-3">
            <Heading
                :title="account.name"
                :description="`${$t(`finance.account_type.${account.type}`)} · ${account.currency}${account.purpose ? ' · ' + account.purpose : ''}`"
            />
            <div class="text-end">
                <p class="text-xs text-muted-foreground">
                    {{ $t('Current balance') }}
                </p>
                <p class="text-2xl font-semibold">
                    <Money :value="account.balance" />
                </p>
            </div>
        </div>

        <FinanceNav current="accounts" />

        <form class="flex flex-wrap items-end gap-3" @submit.prevent="apply">
            <div class="grid gap-1.5">
                <Label for="statement-from">{{ $t('From') }}</Label>
                <Input
                    id="statement-from"
                    v-model="from"
                    type="date"
                    dir="ltr"
                    class="w-44"
                />
            </div>
            <div class="grid gap-1.5">
                <Label for="statement-to">{{ $t('To') }}</Label>
                <Input
                    id="statement-to"
                    v-model="to"
                    type="date"
                    dir="ltr"
                    class="w-44"
                />
            </div>
            <Button type="submit" variant="outline">{{
                $t('Show statement')
            }}</Button>
        </form>

        <div class="overflow-x-auto rounded-xl border bg-card">
            <table class="w-full text-sm">
                <thead class="border-b text-xs text-muted-foreground">
                    <tr>
                        <th class="px-4 py-2 text-start font-medium">
                            {{ $t('Date') }}
                        </th>
                        <th class="px-4 py-2 text-start font-medium">
                            {{ $t('Details') }}
                        </th>
                        <th class="px-4 py-2 text-end font-medium">
                            {{ $t('Amount') }}
                        </th>
                        <th class="px-4 py-2 text-end font-medium">
                            {{ $t('Balance') }}
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <tr class="border-b bg-muted/40">
                        <td class="px-4 py-2 tabular-nums" dir="ltr">
                            {{ statement.from }}
                        </td>
                        <td class="px-4 py-2 font-medium" colspan="2">
                            {{ $t('Opening balance') }}
                        </td>
                        <td class="px-4 py-2 text-end">
                            <Money :value="statement.opening" />
                        </td>
                    </tr>
                    <tr v-if="statement.rows.length === 0">
                        <td
                            colspan="4"
                            class="px-4 py-6 text-center text-muted-foreground"
                        >
                            {{ $t('No movements in this period.') }}
                        </td>
                    </tr>
                    <tr
                        v-for="row in statement.rows"
                        :key="row.id"
                        class="border-b"
                        :class="
                            row.reversed && 'text-muted-foreground line-through'
                        "
                    >
                        <td
                            class="px-4 py-3 whitespace-nowrap tabular-nums"
                            dir="ltr"
                        >
                            {{ row.date }}
                        </td>
                        <td class="px-4 py-3">
                            <div>{{ row.description }}</div>
                            <div class="text-xs text-muted-foreground">
                                <span dir="ltr">{{ row.number }}</span> ·
                                {{ $t(`finance.transaction_type.${row.type}`) }}
                                ·
                                {{
                                    row.direction === 'in'
                                        ? $t('from :name', {
                                              name: row.counterpart,
                                          })
                                        : $t('to :name', {
                                              name: row.counterpart,
                                          })
                                }}
                            </div>
                        </td>
                        <td class="px-4 py-3 text-end">
                            <Money :value="row.amount" :sign="row.direction" />
                        </td>
                        <td class="px-4 py-3 text-end">
                            <Money :value="row.running" />
                        </td>
                    </tr>
                    <tr class="bg-muted/40">
                        <td class="px-4 py-2 tabular-nums" dir="ltr">
                            {{ statement.to }}
                        </td>
                        <td class="px-4 py-2 font-medium" colspan="2">
                            {{ $t('Closing balance') }}
                        </td>
                        <td class="px-4 py-2 text-end font-semibold">
                            <Money :value="statement.closing" />
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
        <p v-if="statement.truncated" class="text-sm text-muted-foreground">
            {{
                $t(
                    'Only the first 500 movements are shown. Choose a shorter period.',
                )
            }}
        </p>
    </div>
</template>
