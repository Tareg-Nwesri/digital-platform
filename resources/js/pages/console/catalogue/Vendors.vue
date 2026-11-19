<script setup lang="ts">
import FormOption from '@/components/forms/FormOption.vue';
import FormSelect from '@/components/forms/FormSelect.vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import { Plus } from '@lucide/vue';
import { trans } from 'laravel-vue-i18n';
import { h, ref } from 'vue';
import CatalogueNav from '@/components/catalogue/CatalogueNav.vue';
import CatalogueStatusBadge from '@/components/catalogue/CatalogueStatusBadge.vue';
import DataTable from '@/components/data-table/DataTable.vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
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
import { formatNumber } from '@/lib/i18n';
import { dashboard } from '@/routes/console';
import { index as partsIndex } from '@/routes/console/catalogue/parts';
import {
    destroy,
    restore,
    store,
    update,
} from '@/routes/console/catalogue/vendors';
import type { ListColumn, TableResult } from '@/types/table';

type VendorRow = {
    id: number;
    name: string;
    website: string | null;
    email: string | null;
    phone: string | null;
    city: string | null;
    address: string | null;
    latitude: string | null;
    longitude: string | null;
    status: string;
    team: string | null;
    offers: number;
};

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Organisation console', href: dashboard() },
            { title: 'Catalogue', href: partsIndex() },
        ],
    },
});

const props = defineProps<{
    vendors: TableResult<VendorRow>;
    statuses: string[];
    can: { manage: boolean };
}>();

const editing = ref<VendorRow | null>(null);
const open = ref(false);

const fields = [
    'name',
    'website',
    'email',
    'phone',
    'city',
    'address',
    'latitude',
    'longitude',
] as const;
type Field = (typeof fields)[number];

const form = useForm<Record<Field, string>>(
    Object.fromEntries(fields.map((field) => [field, ''])) as Record<
        Field,
        string
    >,
);

function openForm(vendor: VendorRow | null): void {
    editing.value = vendor;
    form.defaults(
        Object.fromEntries(
            fields.map((field) => [field, vendor?.[field] ?? '']),
        ) as Record<Field, string>,
    );
    form.reset();
    form.clearErrors();
    open.value = true;
}

function submit(): void {
    const options = {
        preserveScroll: true,
        onSuccess: () => (open.value = false),
    };

    form.transform((data) =>
        Object.fromEntries(
            Object.entries(data).map(([key, value]) => [
                key,
                value === '' ? null : value,
            ]),
        ),
    );

    if (editing.value) {
        form.patch(update(editing.value.id).url, options);
    } else {
        form.post(store().url, options);
    }
}

function archive(vendor: VendorRow): void {
    if (window.confirm(trans('Archive :name?', { name: vendor.name }))) {
        router.delete(destroy(vendor.id).url, { preserveScroll: true });
    }
}

const labels: Record<Field, string> = {
    name: 'Name',
    website: 'Website',
    email: 'Email',
    phone: 'Phone',
    city: 'City',
    address: 'Address',
    latitude: 'Latitude',
    longitude: 'Longitude',
};

const columns: ListColumn<VendorRow>[] = [
    {
        id: 'name',
        header: () => trans('Vendor'),
        meta: { sortable: true },
        cell: ({ row }) =>
            h('div', { class: 'grid' }, [
                h('span', { class: 'font-medium' }, row.original.name),
                row.original.website &&
                    h(
                        'a',
                        {
                            href: row.original.website,
                            target: '_blank',
                            rel: 'noopener noreferrer',
                            class: 'text-xs text-muted-foreground hover:underline',
                            dir: 'ltr',
                        },
                        row.original.website,
                    ),
            ]),
    },
    {
        id: 'city',
        header: () => trans('City'),
        meta: { sortable: true },
        cell: ({ row }) => row.original.city ?? '—',
    },
    {
        id: 'contact',
        header: () => trans('Contact'),
        cell: ({ row }) =>
            h('div', { class: 'grid text-sm', dir: 'ltr' }, [
                row.original.phone && h('span', row.original.phone),
                row.original.email &&
                    h(
                        'span',
                        { class: 'text-xs text-muted-foreground' },
                        row.original.email,
                    ),
            ]),
    },
    {
        id: 'status',
        header: () => trans('Status'),
        cell: ({ row }) =>
            h(CatalogueStatusBadge, { status: row.original.status }),
    },
    {
        id: 'offers',
        header: () => trans('Offers'),
        cell: ({ row }) =>
            h(
                'span',
                { class: 'tabular-nums' },
                formatNumber(row.original.offers),
            ),
    },
    {
        id: 'actions',
        header: () => h('span', { class: 'sr-only' }, trans('Actions')),
        cell: ({ row }) =>
            props.can.manage
                ? h('div', { class: 'flex justify-end gap-1' }, [
                      h(
                          Button,
                          {
                              size: 'sm',
                              variant: 'outline',
                              onClick: () => openForm(row.original),
                          },
                          () => trans('Edit'),
                      ),
                      row.original.status === 'approved' &&
                          h(
                              Button,
                              {
                                  size: 'sm',
                                  variant: 'ghost',
                                  onClick: () => archive(row.original),
                              },
                              () => trans('Archive'),
                          ),
                      row.original.status === 'archived' &&
                          h(
                              Button,
                              {
                                  size: 'sm',
                                  variant: 'ghost',
                                  onClick: () =>
                                      router.patch(
                                          restore(row.original.id).url,
                                          {},
                                          { preserveScroll: true },
                                      ),
                              },
                              () => trans('Restore'),
                          ),
                  ])
                : null,
    },
];
</script>

<template>
    <Head :title="$t('Vendors')" />

    <div class="flex flex-col gap-6 p-4 md:p-6">
        <Heading
            :title="$t('Catalogue')"
            :description="$t('Shops and suppliers teams buy parts from.')"
        />

        <CatalogueNav current="vendors" />

        <DataTable
            tour="vendors"
            :columns="columns"
            :rows="vendors"
            :search-placeholder="$t('Search vendors')"
            :empty-text="$t('No vendors match.')"
            :only="['vendors']"
        >
            <template #filters="{ filter }">
                <FormSelect v-model="filter.status" :aria-label="$t('Status')">
                    <FormOption :value="undefined">{{
                        $t('All statuses')
                    }}</FormOption>
                    <FormOption
                        v-for="status in statuses"
                        :key="status"
                        :value="status"
                    >
                        {{ $t(`catalogue.status.${status}`) }}
                    </FormOption>
                </FormSelect>
            </template>
            <template v-if="can.manage" #actions>
                <Button data-test="new-vendor" @click="openForm(null)">
                    <Plus class="size-4" />
                    {{ $t('New vendor') }}
                </Button>
            </template>
        </DataTable>

        <Dialog v-model:open="open">
            <DialogContent class="max-h-[90vh] overflow-y-auto sm:max-w-lg">
                <DialogHeader>
                    <DialogTitle>
                        {{ editing ? $t('Edit vendor') : $t('New vendor') }}
                    </DialogTitle>
                </DialogHeader>
                <form
                    class="grid gap-4 sm:grid-cols-2"
                    @submit.prevent="submit"
                >
                    <div
                        v-for="field in fields"
                        :key="field"
                        class="grid gap-2"
                        :class="
                            ['name', 'website', 'address'].includes(field) &&
                            'sm:col-span-2'
                        "
                    >
                        <Label :for="`vendor-${field}`">{{
                            $t(labels[field])
                        }}</Label>
                        <Input
                            :id="`vendor-${field}`"
                            v-model="form[field]"
                            :required="field === 'name'"
                            :type="
                                field === 'email'
                                    ? 'email'
                                    : field === 'website'
                                      ? 'url'
                                      : 'text'
                            "
                            :inputmode="
                                ['latitude', 'longitude'].includes(field)
                                    ? 'decimal'
                                    : undefined
                            "
                            :dir="
                                [
                                    'website',
                                    'email',
                                    'phone',
                                    'latitude',
                                    'longitude',
                                ].includes(field)
                                    ? 'ltr'
                                    : undefined
                            "
                        />
                        <InputError :message="form.errors[field]" />
                    </div>
                    <DialogFooter class="sm:col-span-2">
                        <Button
                            type="button"
                            variant="outline"
                            @click="open = false"
                        >
                            {{ $t('Cancel') }}
                        </Button>
                        <Button
                            type="submit"
                            :disabled="form.processing"
                            data-test="save-vendor"
                        >
                            {{ $t('Save vendor') }}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    </div>
</template>
