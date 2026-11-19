<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import { Plus } from '@lucide/vue';
import { trans } from 'laravel-vue-i18n';
import { h, ref } from 'vue';
import DataTable from '@/components/data-table/DataTable.vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { formatNumber } from '@/lib/i18n';
import { dashboard } from '@/routes/console';
import { index, show, store } from '@/routes/console/departments';
import type { ListColumn, TableResult } from '@/types/table';

type DepartmentRow = {
    id: number;
    slug: string;
    name: string;
    membersCount: number;
    adminsCount: number;
    isPublic: boolean;
};

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Organisation console', href: dashboard() },
            { title: 'Departments', href: index() },
        ],
    },
});

defineProps<{ departments: TableResult<DepartmentRow> }>();

const creating = ref(false);
const isPublic = ref(false);

const columns: ListColumn<DepartmentRow>[] = [
    {
        id: 'name_en',
        header: () => trans('Name'),
        meta: { sortable: true },
        cell: ({ row }) =>
            h(
                Link,
                {
                    href: show(row.original.slug).url,
                    class: 'font-medium text-foreground hover:underline',
                },
                () => row.original.name,
            ),
    },
    {
        id: 'members_count',
        header: () => trans('Members'),
        meta: { sortable: true },
        cell: ({ row }) =>
            h(
                'span',
                { class: 'tabular-nums' },
                formatNumber(row.original.membersCount),
            ),
    },
    {
        id: 'admins',
        header: () => trans('Admins'),
        cell: ({ row }) =>
            row.original.adminsCount > 0
                ? h(
                      'span',
                      { class: 'tabular-nums' },
                      formatNumber(row.original.adminsCount),
                  )
                : h(Badge, { variant: 'destructive' }, () => trans('No admin')),
    },
    {
        id: 'visibility',
        header: () => trans('Visibility'),
        cell: ({ row }) =>
            h(
                Badge,
                { variant: row.original.isPublic ? 'secondary' : 'outline' },
                () =>
                    row.original.isPublic ? trans('Public') : trans('Internal'),
            ),
    },
];
</script>

<template>
    <Head :title="$t('Departments')" />

    <div class="flex flex-col gap-6 p-4 md:p-6">
        <Heading
            :title="$t('Departments')"
            :description="
                $t(
                    'Each department has an admin, members and a permission ceiling.',
                )
            "
        />

        <DataTable
            tour="departments"
            :columns="columns"
            :rows="departments"
            :search-placeholder="$t('Search departments')"
            :empty-text="$t('No departments yet.')"
            :only="['departments']"
        >
            <template #actions>
                <Dialog v-model:open="creating">
                    <DialogTrigger as-child>
                        <Button data-test="new-department">
                            <Plus class="size-4" />
                            {{ $t('New department') }}
                        </Button>
                    </DialogTrigger>
                    <DialogContent class="sm:max-w-lg">
                        <DialogHeader>
                            <DialogTitle>{{
                                $t('New department')
                            }}</DialogTitle>
                            <DialogDescription>
                                {{
                                    $t(
                                        'You can set its admin and permission ceiling on the next page.',
                                    )
                                }}
                            </DialogDescription>
                        </DialogHeader>

                        <Form
                            v-bind="store.form()"
                            v-slot="{ errors, processing }"
                            class="grid gap-4"
                            :transform="
                                (data) => ({ ...data, is_public: isPublic })
                            "
                            @success="creating = false"
                        >
                            <div class="grid gap-2">
                                <Label for="name_en">{{
                                    $t('Name (English)')
                                }}</Label>
                                <Input
                                    id="name_en"
                                    name="name_en"
                                    required
                                    maxlength="120"
                                />
                                <InputError :message="errors.name_en" />
                            </div>
                            <div class="grid gap-2">
                                <Label for="name_ar">{{
                                    $t('Name (Arabic)')
                                }}</Label>
                                <Input
                                    id="name_ar"
                                    name="name_ar"
                                    dir="rtl"
                                    maxlength="120"
                                />
                                <InputError :message="errors.name_ar" />
                            </div>
                            <div class="grid gap-2">
                                <Label for="slug">{{
                                    $t('Web address')
                                }}</Label>
                                <Input
                                    id="slug"
                                    name="slug"
                                    dir="ltr"
                                    :placeholder="
                                        $t(
                                            'Leave empty to use the English name',
                                        )
                                    "
                                />
                                <InputError :message="errors.slug" />
                            </div>
                            <Label class="flex items-center gap-2 font-normal">
                                <Checkbox v-model="isPublic" />
                                {{ $t('Show on the public About page') }}
                            </Label>

                            <DialogFooter>
                                <Button type="submit" :disabled="processing">
                                    {{ $t('Create department') }}
                                </Button>
                            </DialogFooter>
                        </Form>
                    </DialogContent>
                </Dialog>
            </template>
        </DataTable>
    </div>
</template>
