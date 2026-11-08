<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { Plus, Trash2 } from '@lucide/vue';
import { computed } from 'vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { store, update } from '@/routes/console/finance/sponsorships';

export type SponsorshipOptions = {
    kinds: string[];
    statuses: string[];
    currencies: string[];
    accounts: { id: number; name: string; currency: string }[];
};

export type SponsorshipValue = {
    id: number;
    title: string;
    kind: string;
    status: string;
    currency: string;
    pledged: string;
    startsOn: string | null;
    endsOn: string | null;
    purpose: string | null;
    restrictions: string | null;
    defaultAccountId: number | null;
    rules: {
        accountId: number;
        percent: string | null;
        fixed: string | null;
    }[];
};

const props = defineProps<{
    sponsorId: number;
    options: SponsorshipOptions;
    sponsorship?: SponsorshipValue | null;
    disabled?: boolean;
}>();

const emit = defineEmits<{ saved: [] }>();

const form = useForm({
    title: props.sponsorship?.title ?? '',
    kind: props.sponsorship?.kind ?? 'cash',
    status: props.sponsorship?.status ?? 'active',
    currency: props.sponsorship?.currency ?? 'LYD',
    pledged: props.sponsorship?.pledged ?? '',
    starts_on: props.sponsorship?.startsOn ?? '',
    ends_on: props.sponsorship?.endsOn ?? '',
    purpose: props.sponsorship?.purpose ?? '',
    restrictions: props.sponsorship?.restrictions ?? '',
    default_account_id:
        props.sponsorship?.defaultAccountId ?? (null as number | null),
    rules: (props.sponsorship?.rules ?? []).map((rule) => ({
        account_id: rule.accountId as number | null,
        mode: rule.fixed !== null ? 'fixed' : 'percent',
        value: rule.fixed ?? rule.percent ?? '',
    })),
});

const errors = computed(
    () => form.errors as Record<string, string | undefined>,
);
const accounts = computed(() =>
    props.options.accounts.filter(
        (account) => account.currency === form.currency,
    ),
);
const percentTotal = computed(() =>
    form.rules
        .filter((rule) => rule.mode === 'percent')
        .reduce((sum, rule) => sum + (Number(rule.value) || 0), 0),
);

function addRule(): void {
    form.rules.push({
        account_id: accounts.value[0]?.id ?? null,
        mode: 'percent',
        value: '',
    });
}

function submit(): void {
    const options = { preserveScroll: true, onSuccess: () => emit('saved') };

    form.transform((data) => ({
        title: data.title,
        kind: data.kind,
        status: data.status,
        currency: data.currency,
        pledged: data.pledged || '0',
        starts_on: data.starts_on || null,
        ends_on: data.ends_on || null,
        purpose: data.purpose || null,
        restrictions: data.restrictions || null,
        default_account_id: data.default_account_id,
        rules: data.rules.map((rule) => ({
            account_id: rule.account_id,
            percent: rule.mode === 'percent' ? rule.value : null,
            fixed: rule.mode === 'fixed' ? rule.value : null,
        })),
    }));

    if (props.sponsorship) {
        form.patch(update(props.sponsorship.id).url, options);
    } else {
        form.post(store(props.sponsorId).url, options);
    }
}

const selectClass =
    'h-9 w-full rounded-md border border-input bg-transparent px-3 text-sm shadow-xs';
</script>

<template>
    <form class="grid gap-4" @submit.prevent="submit">
        <div class="grid gap-2">
            <Label for="sponsorship-title">{{ $t('Title') }}</Label>
            <Input
                id="sponsorship-title"
                v-model="form.title"
                required
                maxlength="160"
                :disabled="disabled"
            />
            <InputError :message="form.errors.title" />
        </div>
        <div class="grid gap-4 sm:grid-cols-3">
            <div class="grid gap-2">
                <Label for="sponsorship-kind">{{ $t('Kind') }}</Label>
                <select
                    id="sponsorship-kind"
                    v-model="form.kind"
                    :class="selectClass"
                    :disabled="disabled"
                >
                    <option
                        v-for="kind in options.kinds"
                        :key="kind"
                        :value="kind"
                    >
                        {{ $t(`finance.sponsorship_kind.${kind}`) }}
                    </option>
                </select>
            </div>
            <div class="grid gap-2">
                <Label for="sponsorship-status">{{ $t('Status') }}</Label>
                <select
                    id="sponsorship-status"
                    v-model="form.status"
                    :class="selectClass"
                    :disabled="disabled"
                >
                    <option
                        v-for="status in options.statuses"
                        :key="status"
                        :value="status"
                    >
                        {{ $t(`finance.sponsorship_status.${status}`) }}
                    </option>
                </select>
            </div>
            <div class="grid gap-2">
                <Label for="sponsorship-currency">{{ $t('Currency') }}</Label>
                <select
                    id="sponsorship-currency"
                    v-model="form.currency"
                    :class="selectClass"
                    dir="ltr"
                    :disabled="disabled"
                >
                    <option
                        v-for="currency in options.currencies"
                        :key="currency"
                        :value="currency"
                    >
                        {{ currency }}
                    </option>
                </select>
            </div>
        </div>
        <div class="grid gap-4 sm:grid-cols-3">
            <div class="grid gap-2">
                <Label for="sponsorship-pledged">{{
                    $t('Pledged amount')
                }}</Label>
                <Input
                    id="sponsorship-pledged"
                    v-model="form.pledged"
                    inputmode="decimal"
                    dir="ltr"
                    :disabled="disabled"
                />
                <InputError :message="form.errors.pledged" />
            </div>
            <div class="grid gap-2">
                <Label for="sponsorship-starts">{{ $t('Starts') }}</Label>
                <Input
                    id="sponsorship-starts"
                    v-model="form.starts_on"
                    type="date"
                    dir="ltr"
                    :disabled="disabled"
                />
            </div>
            <div class="grid gap-2">
                <Label for="sponsorship-ends">{{ $t('Ends') }}</Label>
                <Input
                    id="sponsorship-ends"
                    v-model="form.ends_on"
                    type="date"
                    dir="ltr"
                    :disabled="disabled"
                />
                <InputError :message="form.errors.ends_on" />
            </div>
        </div>
        <div class="grid gap-2">
            <Label for="sponsorship-purpose">{{ $t('Purpose') }}</Label>
            <Input
                id="sponsorship-purpose"
                v-model="form.purpose"
                maxlength="5000"
                :disabled="disabled"
            />
        </div>

        <fieldset class="grid gap-3 rounded-lg border p-3">
            <legend class="px-1 text-sm font-medium">
                {{ $t('How incoming money is split') }}
            </legend>
            <p class="text-xs text-muted-foreground">
                {{
                    $t(
                        'Fixed amounts come first, then percentages of what is left. Anything remaining goes to the account below.',
                    )
                }}
            </p>
            <div
                v-for="(rule, index) in form.rules"
                :key="index"
                class="grid gap-1"
            >
                <div class="flex flex-wrap items-center gap-2">
                    <select
                        v-model="rule.account_id"
                        :class="selectClass"
                        class="min-w-40 flex-1"
                        :aria-label="$t('Account')"
                        :disabled="disabled"
                    >
                        <option
                            v-for="account in accounts"
                            :key="account.id"
                            :value="account.id"
                        >
                            {{ account.name }}
                        </option>
                    </select>
                    <select
                        v-model="rule.mode"
                        :class="selectClass"
                        class="w-36"
                        :aria-label="$t('Kind')"
                        :disabled="disabled"
                    >
                        <option value="percent">{{ $t('Percentage') }}</option>
                        <option value="fixed">{{ $t('Fixed amount') }}</option>
                    </select>
                    <Input
                        v-model="rule.value"
                        inputmode="decimal"
                        dir="ltr"
                        class="w-28"
                        :aria-label="$t('Value')"
                        :disabled="disabled"
                    />
                    <span
                        class="w-10 text-sm text-muted-foreground"
                        dir="ltr"
                        >{{
                            rule.mode === 'percent' ? '%' : form.currency
                        }}</span
                    >
                    <Button
                        v-if="!disabled"
                        type="button"
                        variant="ghost"
                        size="icon"
                        :aria-label="$t('Remove')"
                        @click="form.rules.splice(index, 1)"
                    >
                        <Trash2 class="size-4" />
                    </Button>
                </div>
                <InputError
                    :message="
                        errors[`rules.${index}.percent`] ??
                        errors[`rules.${index}.account_id`] ??
                        errors[`rules.${index}.fixed`]
                    "
                />
            </div>
            <p
                class="text-xs"
                :class="
                    percentTotal > 100
                        ? 'text-destructive'
                        : 'text-muted-foreground'
                "
            >
                {{
                    $t('Percentages total: :total%', {
                        total: String(percentTotal),
                    })
                }}
            </p>
            <InputError :message="errors.rules" />
            <Button
                v-if="!disabled"
                type="button"
                variant="outline"
                size="sm"
                class="justify-self-start"
                @click="addRule"
            >
                <Plus class="size-4" />
                {{ $t('Add a split line') }}
            </Button>
            <div class="grid gap-2">
                <Label for="sponsorship-default">{{
                    $t('Remainder goes to')
                }}</Label>
                <select
                    id="sponsorship-default"
                    v-model="form.default_account_id"
                    :class="selectClass"
                    :disabled="disabled"
                >
                    <option :value="null">{{ $t('General fund') }}</option>
                    <option
                        v-for="account in accounts"
                        :key="account.id"
                        :value="account.id"
                    >
                        {{ account.name }}
                    </option>
                </select>
                <InputError :message="form.errors.default_account_id" />
            </div>
        </fieldset>

        <div v-if="!disabled" class="flex justify-end">
            <Button
                type="submit"
                :disabled="form.processing"
                data-test="save-sponsorship"
                >{{ $t('Save sponsorship') }}</Button
            >
        </div>
    </form>
</template>
