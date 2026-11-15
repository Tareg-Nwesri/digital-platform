<script setup lang="ts">
import FormOption from '@/components/forms/FormOption.vue';
import FormSelect from '@/components/forms/FormSelect.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { Plus } from '@lucide/vue';
import { computed, ref } from 'vue';
import FinanceNav from '@/components/finance/FinanceNav.vue';
import Money from '@/components/finance/Money.vue';
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
import { dashboard as consoleDashboard } from '@/routes/console';
import { dashboard } from '@/routes/console/finance';
import { show, store } from '@/routes/console/finance/accounts';

type MoneyValue = { amountMinor: number; currency: string; formatted: string };
type AccountRow = {
    id: number;
    name: string;
    type: string;
    currency: string;
    restricted: boolean;
    internal: boolean;
    archived: boolean;
    balance: MoneyValue;
};

const props = defineProps<{
    accounts: AccountRow[];
    creatable: string[];
    currencies: string[];
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

const internal = computed(() =>
    props.accounts.filter((account) => account.internal),
);
const external = computed(() =>
    props.accounts.filter((account) => !account.internal),
);

const open = ref(false);
const form = useForm({
    type: props.creatable[0] ?? 'earmarked_fund',
    name_ar: '',
    name_en: '',
    currency: props.currencies[0] ?? 'LYD',
    purpose: '',
});

function create(): void {
    form.transform((data) => ({
        ...data,
        name_ar: data.name_ar || null,
        purpose: data.purpose || null,
    })).post(store().url, {
        onSuccess: () => (open.value = false),
    });
}
</script>

<template>
    <Head :title="$t('Accounts')" />

    <div class="flex flex-col gap-6 p-4 md:p-6">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <Heading
                :title="$t('Finance')"
                :description="$t('Every account and its current balance.')"
            />
            <Button
                v-if="can.edit"
                data-test="new-account"
                @click="open = true"
            >
                <Plus class="size-4" />
                {{ $t('Open an account') }}
            </Button>
        </div>

        <FinanceNav current="accounts" />

        <section
            v-for="[title, rows] in [
                ['Foundation accounts', internal],
                ['Outside parties', external],
            ] as const"
            :key="title"
            class="grid gap-3"
        >
            <h3 class="font-semibold">{{ $t(title) }}</h3>
            <div class="overflow-x-auto rounded-xl border bg-card">
                <table class="w-full text-sm">
                    <thead class="border-b text-xs text-muted-foreground">
                        <tr>
                            <th class="px-4 py-2 text-start font-medium">
                                {{ $t('Account') }}
                            </th>
                            <th class="px-4 py-2 text-start font-medium">
                                {{ $t('Type') }}
                            </th>
                            <th class="px-4 py-2 text-end font-medium">
                                {{ $t('Balance') }}
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="rows.length === 0">
                            <td
                                colspan="3"
                                class="px-4 py-6 text-center text-muted-foreground"
                            >
                                {{ $t('None yet.') }}
                            </td>
                        </tr>
                        <tr
                            v-for="account in rows"
                            :key="account.id"
                            class="border-b last:border-0"
                        >
                            <td class="px-4 py-3">
                                <Link
                                    :href="show(account.id)"
                                    class="font-medium hover:underline"
                                    >{{ account.name }}</Link
                                >
                                <Badge
                                    v-if="account.archived"
                                    variant="outline"
                                    class="ms-2"
                                    >{{ $t('Archived') }}</Badge
                                >
                                <Badge
                                    v-if="account.restricted"
                                    variant="secondary"
                                    class="ms-2"
                                    >{{ $t('Restricted') }}</Badge
                                >
                            </td>
                            <td class="px-4 py-3 text-muted-foreground">
                                {{ $t(`finance.account_type.${account.type}`) }}
                            </td>
                            <td class="px-4 py-3 text-end">
                                <Money :value="account.balance" />
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>

        <Dialog v-model:open="open">
            <DialogContent class="sm:max-w-lg">
                <DialogHeader>
                    <DialogTitle>{{ $t('Open an account') }}</DialogTitle>
                </DialogHeader>
                <form class="grid gap-4" @submit.prevent="create">
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div class="grid gap-2">
                            <Label for="account-type">{{ $t('Type') }}</Label>
                            <FormSelect id="account-type" v-model="form.type">
                                <FormOption
                                    v-for="type in creatable"
                                    :key="type"
                                    :value="type"
                                >
                                    {{ $t(`finance.account_type.${type}`) }}
                                </FormOption>
                            </FormSelect>
                            <InputError :message="form.errors.type" />
                        </div>
                        <div class="grid gap-2">
                            <Label for="account-currency">{{
                                $t('Currency')
                            }}</Label>
                            <FormSelect
                                id="account-currency"
                                v-model="form.currency"
                                dir="ltr"
                            >
                                <FormOption
                                    v-for="currency in currencies"
                                    :key="currency"
                                    :value="currency"
                                >
                                    {{ currency }}
                                </FormOption>
                            </FormSelect>
                        </div>
                    </div>
                    <div class="grid gap-2">
                        <Label for="account-name-ar">{{
                            $t('Name (Arabic)')
                        }}</Label>
                        <Input
                            id="account-name-ar"
                            v-model="form.name_ar"
                            dir="rtl"
                            maxlength="160"
                        />
                    </div>
                    <div class="grid gap-2">
                        <Label for="account-name-en">{{
                            $t('Name (English)')
                        }}</Label>
                        <Input
                            id="account-name-en"
                            v-model="form.name_en"
                            dir="ltr"
                            required
                            maxlength="160"
                        />
                        <InputError :message="form.errors.name_en" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="account-purpose">{{
                            $t('What the money may be used for')
                        }}</Label>
                        <Input
                            id="account-purpose"
                            v-model="form.purpose"
                            maxlength="500"
                        />
                    </div>
                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            @click="open = false"
                            >{{ $t('Cancel') }}</Button
                        >
                        <Button type="submit" :disabled="form.processing">{{
                            $t('Open account')
                        }}</Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    </div>
</template>
