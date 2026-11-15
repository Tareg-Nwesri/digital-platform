<script setup lang="ts">
import FormOption from '@/components/forms/FormOption.vue';
import FormSelect from '@/components/forms/FormSelect.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { Plus } from '@lucide/vue';
import { trans } from 'laravel-vue-i18n';
import { h, ref } from 'vue';
import DataTable from '@/components/data-table/DataTable.vue';
import FinanceNav from '@/components/finance/FinanceNav.vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogContent,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { formatNumber } from '@/lib/i18n';
import { dashboard as consoleDashboard } from '@/routes/console';
import { dashboard } from '@/routes/console/finance';
import { show, store } from '@/routes/console/finance/sponsors';
import type { ListColumn, TableResult } from '@/types/table';

type SponsorRow = {
    id: number;
    name: string;
    type: string;
    isActive: boolean;
    publicConsent: boolean;
    sponsorships: number;
};

const props = defineProps<{
    sponsors: TableResult<SponsorRow>;
    types: string[];
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

const open = ref(false);
const form = useForm({
    name_ar: '',
    name_en: '',
    type: props.types[0] ?? 'company',
    website: '',
    public_consent: false,
});

function create(): void {
    form.transform((data) => ({
        ...data,
        name_ar: data.name_ar || null,
        website: data.website || null,
    })).post(store().url, {
        onSuccess: () => (open.value = false),
    });
}

const columns: ListColumn<SponsorRow>[] = [
    {
        id: 'name_en',
        header: () => trans('Sponsor'),
        meta: { sortable: true },
        cell: ({ row }) =>
            h('div', { class: 'flex flex-wrap items-center gap-2' }, [
                h(
                    Link,
                    {
                        href: show(row.original.id).url,
                        class: 'font-medium hover:underline',
                    },
                    () => row.original.name,
                ),
                !row.original.isActive &&
                    h(Badge, { variant: 'outline' }, () => trans('Inactive')),
            ]),
    },
    {
        id: 'type',
        header: () => trans('Type'),
        cell: ({ row }) => trans(`finance.sponsor_type.${row.original.type}`),
    },
    {
        id: 'sponsorships',
        header: () => trans('Sponsorships'),
        cell: ({ row }) =>
            h(
                'span',
                { class: 'tabular-nums' },
                formatNumber(row.original.sponsorships),
            ),
    },
    {
        id: 'public',
        header: () => trans('Shown on the website'),
        cell: ({ row }) =>
            row.original.publicConsent ? trans('Yes') : trans('No'),
    },
];
</script>

<template>
    <Head :title="$t('Sponsors')" />

    <div class="flex flex-col gap-6 p-4 md:p-6">
        <Heading
            :title="$t('Finance')"
            :description="
                $t('Sponsors, their contacts and what they have pledged.')
            "
        />

        <FinanceNav current="sponsors" />

        <DataTable
            :columns="columns"
            :rows="sponsors"
            :search-placeholder="$t('Search sponsors')"
            :empty-text="$t('No sponsors yet.')"
            :only="['sponsors']"
        >
            <template #filters="{ filter }">
                <FormSelect v-model="filter.type" :aria-label="$t('Type')">
                    <FormOption :value="undefined">{{
                        $t('All types')
                    }}</FormOption>
                    <FormOption v-for="type in types" :key="type" :value="type">
                        {{ $t(`finance.sponsor_type.${type}`) }}
                    </FormOption>
                </FormSelect>
                <FormSelect v-model="filter.active" :aria-label="$t('Status')">
                    <FormOption :value="undefined">{{
                        $t('All sponsors')
                    }}</FormOption>
                    <FormOption value="1">{{ $t('Active') }}</FormOption>
                    <FormOption value="0">{{ $t('Inactive') }}</FormOption>
                </FormSelect>
            </template>
            <template v-if="can.edit" #actions>
                <Button data-test="new-sponsor" @click="open = true">
                    <Plus class="size-4" />
                    {{ $t('New sponsor') }}
                </Button>
            </template>
        </DataTable>

        <Dialog v-model:open="open">
            <DialogContent class="sm:max-w-lg">
                <DialogHeader>
                    <DialogTitle>{{ $t('New sponsor') }}</DialogTitle>
                </DialogHeader>
                <form class="grid gap-4" @submit.prevent="create">
                    <div class="grid gap-2">
                        <Label for="sponsor-name-ar">{{
                            $t('Name (Arabic)')
                        }}</Label>
                        <Input
                            id="sponsor-name-ar"
                            v-model="form.name_ar"
                            dir="rtl"
                            maxlength="160"
                        />
                    </div>
                    <div class="grid gap-2">
                        <Label for="sponsor-name-en">{{
                            $t('Name (English)')
                        }}</Label>
                        <Input
                            id="sponsor-name-en"
                            v-model="form.name_en"
                            dir="ltr"
                            required
                            maxlength="160"
                        />
                        <InputError :message="form.errors.name_en" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="sponsor-type">{{ $t('Type') }}</Label>
                        <FormSelect
                            id="sponsor-type"
                            v-model="form.type"
                            class="h-9 w-full rounded-md border border-input bg-transparent px-3 text-sm shadow-xs"
                        >
                            <FormOption
                                v-for="type in types"
                                :key="type"
                                :value="type"
                            >
                                {{ $t(`finance.sponsor_type.${type}`) }}
                            </FormOption>
                        </FormSelect>
                    </div>
                    <div class="grid gap-2">
                        <Label for="sponsor-website">{{ $t('Website') }}</Label>
                        <Input
                            id="sponsor-website"
                            v-model="form.website"
                            type="url"
                            dir="ltr"
                        />
                        <InputError :message="form.errors.website" />
                    </div>
                    <label class="flex items-center gap-3 text-sm">
                        <Checkbox
                            :model-value="form.public_consent"
                            @update:model-value="
                                (value) =>
                                    (form.public_consent = value === true)
                            "
                        />
                        {{
                            $t('The sponsor agrees to be named on the website')
                        }}
                    </label>
                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            @click="open = false"
                            >{{ $t('Cancel') }}</Button
                        >
                        <Button type="submit" :disabled="form.processing">{{
                            $t('Save sponsor')
                        }}</Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    </div>
</template>
