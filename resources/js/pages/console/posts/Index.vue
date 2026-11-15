<script setup lang="ts">
import FormOption from '@/components/forms/FormOption.vue';
import FormSelect from '@/components/forms/FormSelect.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { Plus } from '@lucide/vue';
import { trans } from 'laravel-vue-i18n';
import { h, ref } from 'vue';
import PostStatusBadge from '@/components/content/PostStatusBadge.vue';
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
import { formatDate } from '@/lib/i18n';
import { dashboard } from '@/routes/console';
import { edit, index, store } from '@/routes/console/posts';
import type { ListColumn, TableResult } from '@/types/table';

type PostRow = {
    id: number;
    title: string;
    department: string;
    author: string | null;
    status: string;
    publishedAt: string | null;
    updatedAt: string | null;
};

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Organisation console', href: dashboard() },
            { title: 'News', href: index() },
        ],
    },
});

const props = defineProps<{
    posts: TableResult<PostRow>;
    statuses: string[];
    departments: { id: number; name: string }[];
    can: { create: boolean };
}>();

const creating = ref(false);
const form = useForm({
    department_id: props.departments[0]?.id ?? null,
    title_ar: '',
    title_en: '',
});

function openCreate(): void {
    form.reset();
    form.clearErrors();
    creating.value = true;
}

function create(): void {
    form.transform((data) => ({
        ...data,
        title_ar: data.title_ar || null,
    })).post(store().url, {
        onSuccess: () => (creating.value = false),
    });
}

const columns: ListColumn<PostRow>[] = [
    {
        id: 'title_en',
        header: () => trans('Title'),
        meta: { sortable: true },
        cell: ({ row }) =>
            h('div', { class: 'grid' }, [
                h(
                    Link,
                    {
                        href: edit(row.original.id).url,
                        class: 'font-medium text-foreground hover:underline',
                    },
                    () => row.original.title,
                ),
                h(
                    'span',
                    { class: 'text-xs text-muted-foreground' },
                    [row.original.department, row.original.author]
                        .filter(Boolean)
                        .join(' · '),
                ),
            ]),
    },
    {
        id: 'status',
        header: () => trans('Status'),
        cell: ({ row }) => h(PostStatusBadge, { status: row.original.status }),
    },
    {
        id: 'published_at',
        header: () => trans('Publication date'),
        meta: { sortable: true },
        cell: ({ row }) =>
            h(
                'span',
                { class: 'text-muted-foreground tabular-nums' },
                row.original.publishedAt
                    ? formatDate(row.original.publishedAt)
                    : '—',
            ),
    },
    {
        id: 'updated_at',
        header: () => trans('Last edited'),
        meta: { sortable: true },
        cell: ({ row }) =>
            h(
                'span',
                { class: 'text-muted-foreground tabular-nums' },
                formatDate(row.original.updatedAt),
            ),
    },
];
</script>

<template>
    <Head :title="$t('News')" />

    <div class="flex flex-col gap-6 p-4 md:p-6">
        <Heading
            :title="$t('News')"
            :description="
                $t(
                    'News posts for the public site. Editors write; department admins publish.',
                )
            "
        />

        <DataTable
            :columns="columns"
            :rows="posts"
            :search-placeholder="$t('Search news')"
            :empty-text="$t('No posts match.')"
            :only="['posts']"
        >
            <template #filters="{ filter }">
                <FormSelect
                    v-model="filter.status"
                    class="h-9 rounded-md border border-input bg-transparent px-3 text-sm shadow-xs"
                    :aria-label="$t('Status')"
                >
                    <FormOption :value="undefined">{{
                        $t('All statuses')
                    }}</FormOption>
                    <FormOption
                        v-for="status in statuses"
                        :key="status"
                        :value="status"
                    >
                        {{ $t(`content.post_status.${status}`) }}
                    </FormOption>
                </FormSelect>
            </template>
            <template v-if="can.create" #actions>
                <Button data-test="new-post" @click="openCreate">
                    <Plus class="size-4" />
                    {{ $t('New post') }}
                </Button>
            </template>
        </DataTable>

        <Dialog v-model:open="creating">
            <DialogContent class="sm:max-w-lg">
                <DialogHeader>
                    <DialogTitle>{{ $t('New post') }}</DialogTitle>
                </DialogHeader>
                <form class="grid gap-4" @submit.prevent="create">
                    <div class="grid gap-2">
                        <Label for="post-department">{{
                            $t('Department')
                        }}</Label>
                        <FormSelect
                            id="post-department"
                            v-model="form.department_id"
                        >
                            <FormOption
                                v-for="department in departments"
                                :key="department.id"
                                :value="department.id"
                            >
                                {{ department.name }}
                            </FormOption>
                        </FormSelect>
                        <InputError :message="form.errors.department_id" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="post-title-ar">{{
                            $t('Title (Arabic)')
                        }}</Label>
                        <Input
                            id="post-title-ar"
                            v-model="form.title_ar"
                            dir="rtl"
                            maxlength="160"
                        />
                        <InputError :message="form.errors.title_ar" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="post-title-en">{{
                            $t('Title (English)')
                        }}</Label>
                        <Input
                            id="post-title-en"
                            v-model="form.title_en"
                            dir="ltr"
                            required
                            maxlength="160"
                        />
                        <InputError :message="form.errors.title_en" />
                    </div>
                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            @click="creating = false"
                            >{{ $t('Cancel') }}</Button
                        >
                        <Button type="submit" :disabled="form.processing">{{
                            $t('Create draft')
                        }}</Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    </div>
</template>
