<script setup lang="ts">
import FormOption from '@/components/forms/FormOption.vue';
import FormSelect from '@/components/forms/FormSelect.vue';
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import { Pencil, Plus, Trash2 } from '@lucide/vue';
import { trans } from 'laravel-vue-i18n';
import { computed, ref } from 'vue';
import CatalogueNav from '@/components/catalogue/CatalogueNav.vue';
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
import { destroy, store, update } from '@/routes/console/catalogue/categories';
import { index as partsIndex } from '@/routes/console/catalogue/parts';

type CategoryRow = {
    id: number;
    slug: string;
    name: string;
    nameEn: string;
    nameAr: string | null;
    parentId: number | null;
    position: number;
    partsCount: number;
};

const props = defineProps<{
    categories: (CategoryRow & { children: CategoryRow[] })[];
    can: { manage: boolean };
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Organisation console', href: dashboard() },
            { title: 'Catalogue', href: partsIndex() },
        ],
    },
});

const page = usePage();
const categoryError = computed(
    () => (page.props.errors as Record<string, string | undefined>).category,
);

const open = ref(false);
const editing = ref<CategoryRow | null>(null);

const form = useForm({
    name_en: '',
    name_ar: '',
    parent_id: null as number | null,
    position: 0,
});

function openForm(
    category: CategoryRow | null,
    parentId: number | null = null,
): void {
    editing.value = category;
    form.defaults({
        name_en: category?.nameEn ?? '',
        name_ar: category?.nameAr ?? '',
        parent_id: category ? category.parentId : parentId,
        position: category?.position ?? 0,
    });
    form.reset();
    form.clearErrors();
    open.value = true;
}

function submit(): void {
    const options = {
        preserveScroll: true,
        onSuccess: () => (open.value = false),
    };

    form.transform((data) => ({ ...data, name_ar: data.name_ar || null }));

    if (editing.value) {
        form.patch(update(editing.value.id).url, options);
    } else {
        form.post(store().url, options);
    }
}

function remove(category: CategoryRow): void {
    if (window.confirm(trans('Delete :name?', { name: category.name }))) {
        router.delete(destroy(category.id).url, { preserveScroll: true });
    }
}

const parents = computed(() =>
    props.categories.filter((category) => category.id !== editing.value?.id),
);
</script>

<template>
    <Head :title="$t('Categories')" />

    <div class="flex flex-col gap-6 p-4 md:p-6">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <Heading
                :title="$t('Catalogue')"
                :description="$t('Part categories, two levels deep.')"
            />
            <Button
                v-if="can.manage"
                data-test="new-category"
                data-tour="categories-new"
                @click="openForm(null)"
            >
                <Plus class="size-4" />
                {{ $t('New category') }}
            </Button>
        </div>

        <CatalogueNav current="categories" />

        <InputError :message="categoryError" />

        <ul class="grid gap-3" data-tour="categories-list">
            <li
                v-if="categories.length === 0"
                class="text-sm text-muted-foreground"
            >
                {{ $t('No categories yet.') }}
            </li>
            <li
                v-for="category in categories"
                :key="category.id"
                class="rounded-xl border bg-card"
            >
                <div class="flex items-center justify-between gap-3 px-4 py-3">
                    <div>
                        <span class="font-medium">{{ category.name }}</span>
                        <span
                            class="ms-2 text-xs text-muted-foreground tabular-nums"
                        >
                            {{
                                $t(':count parts', {
                                    count: formatNumber(category.partsCount),
                                })
                            }}
                        </span>
                    </div>
                    <div v-if="can.manage" class="flex gap-1">
                        <Button
                            variant="ghost"
                            size="sm"
                            @click="openForm(null, category.id)"
                        >
                            <Plus class="size-4" />
                            {{ $t('Subcategory') }}
                        </Button>
                        <Button
                            variant="ghost"
                            size="icon"
                            :aria-label="$t('Edit')"
                            @click="openForm(category)"
                        >
                            <Pencil class="size-4" />
                        </Button>
                        <Button
                            variant="ghost"
                            size="icon"
                            class="text-destructive"
                            :aria-label="$t('Delete')"
                            @click="remove(category)"
                        >
                            <Trash2 class="size-4" />
                        </Button>
                    </div>
                </div>
                <ul v-if="category.children.length > 0" class="border-t">
                    <li
                        v-for="child in category.children"
                        :key="child.id"
                        class="flex items-center justify-between gap-3 border-b px-4 py-2 ps-10 last:border-0"
                    >
                        <div class="text-sm">
                            {{ child.name }}
                            <span
                                class="ms-2 text-xs text-muted-foreground tabular-nums"
                            >
                                {{
                                    $t(':count parts', {
                                        count: formatNumber(child.partsCount),
                                    })
                                }}
                            </span>
                        </div>
                        <div v-if="can.manage" class="flex gap-1">
                            <Button
                                variant="ghost"
                                size="icon"
                                :aria-label="$t('Edit')"
                                @click="openForm(child)"
                            >
                                <Pencil class="size-4" />
                            </Button>
                            <Button
                                variant="ghost"
                                size="icon"
                                class="text-destructive"
                                :aria-label="$t('Delete')"
                                @click="remove(child)"
                            >
                                <Trash2 class="size-4" />
                            </Button>
                        </div>
                    </li>
                </ul>
            </li>
        </ul>

        <Dialog v-model:open="open">
            <DialogContent class="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>
                        {{ editing ? $t('Edit category') : $t('New category') }}
                    </DialogTitle>
                </DialogHeader>
                <form class="grid gap-4" @submit.prevent="submit">
                    <div class="grid gap-2">
                        <Label for="category-name-en">{{
                            $t('Name (English)')
                        }}</Label>
                        <Input
                            id="category-name-en"
                            v-model="form.name_en"
                            dir="ltr"
                            required
                            maxlength="120"
                        />
                        <InputError :message="form.errors.name_en" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="category-name-ar">{{
                            $t('Name (Arabic)')
                        }}</Label>
                        <Input
                            id="category-name-ar"
                            v-model="form.name_ar"
                            dir="rtl"
                            maxlength="120"
                        />
                    </div>
                    <div class="grid gap-2">
                        <Label for="category-parent">{{ $t('Inside') }}</Label>
                        <FormSelect
                            id="category-parent"
                            v-model="form.parent_id"
                        >
                            <FormOption :value="null">{{
                                $t('Top level')
                            }}</FormOption>
                            <FormOption
                                v-for="parent in parents"
                                :key="parent.id"
                                :value="parent.id"
                            >
                                {{ parent.name }}
                            </FormOption>
                        </FormSelect>
                        <InputError :message="form.errors.parent_id" />
                    </div>
                    <DialogFooter>
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
                            data-test="save-category"
                        >
                            {{ $t('Save category') }}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    </div>
</template>
