<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { Plus, Trash2 } from '@lucide/vue';
import { computed } from 'vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { store, update } from '@/routes/console/catalogue/parts';

export type Spec = { key: string; value: string; unit?: string | null };

export type PartFormValue = {
    id: number;
    name: string;
    partNumber: string | null;
    categoryId: number | null;
    descriptionEn: string | null;
    descriptionAr: string | null;
    specs: Spec[];
};

const props = defineProps<{
    part?: PartFormValue | null;
    categories: { id: number; name: string; depth: number }[];
    disabled?: boolean;
}>();

const emit = defineEmits<{ saved: [] }>();

const form = useForm({
    name: props.part?.name ?? '',
    part_number: props.part?.partNumber ?? '',
    category_id: props.part?.categoryId ?? (null as number | null),
    description_en: props.part?.descriptionEn ?? '',
    description_ar: props.part?.descriptionAr ?? '',
    specs: (props.part?.specs ?? []).map((spec) => ({
        key: spec.key,
        value: spec.value,
        unit: spec.unit ?? '',
    })),
});

const errors = computed(
    () => form.errors as Record<string, string | undefined>,
);

function submit(): void {
    const options = { preserveScroll: true, onSuccess: () => emit('saved') };

    form.transform((data) => ({
        ...data,
        part_number: data.part_number || null,
        specs: data.specs.map((spec) => ({ ...spec, unit: spec.unit || null })),
    }));

    if (props.part) {
        form.patch(update(props.part.id).url, options);
    } else {
        form.post(store().url, options);
    }
}

const selectClass =
    'h-9 w-full rounded-md border border-input bg-transparent px-3 text-sm shadow-xs';
const textareaClass =
    'rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs';
</script>

<template>
    <form class="grid gap-4" @submit.prevent="submit">
        <div class="grid gap-4 sm:grid-cols-2">
            <div class="grid gap-2">
                <Label for="part-name">{{ $t('Name') }}</Label>
                <Input
                    id="part-name"
                    v-model="form.name"
                    required
                    maxlength="160"
                    :disabled="disabled"
                />
                <InputError :message="form.errors.name" />
            </div>
            <div class="grid gap-2">
                <Label for="part-number">{{ $t('Part number') }}</Label>
                <Input
                    id="part-number"
                    v-model="form.part_number"
                    maxlength="80"
                    dir="ltr"
                    :disabled="disabled"
                />
                <InputError :message="form.errors.part_number" />
            </div>
        </div>
        <div class="grid gap-2">
            <Label for="part-category">{{ $t('Category') }}</Label>
            <select
                id="part-category"
                v-model="form.category_id"
                :class="selectClass"
                :disabled="disabled"
            >
                <option :value="null">{{ $t('No category') }}</option>
                <option
                    v-for="category in categories"
                    :key="category.id"
                    :value="category.id"
                >
                    {{ category.depth > 0 ? '— ' : '' }}{{ category.name }}
                </option>
            </select>
            <InputError :message="form.errors.category_id" />
        </div>
        <div class="grid gap-2">
            <Label for="part-description-en">{{
                $t('Description (English)')
            }}</Label>
            <textarea
                id="part-description-en"
                v-model="form.description_en"
                rows="3"
                dir="ltr"
                :class="textareaClass"
                :disabled="disabled"
            />
            <InputError :message="form.errors.description_en" />
        </div>
        <div class="grid gap-2">
            <Label for="part-description-ar">{{
                $t('Description (Arabic)')
            }}</Label>
            <textarea
                id="part-description-ar"
                v-model="form.description_ar"
                rows="3"
                dir="rtl"
                :class="textareaClass"
                :disabled="disabled"
            />
        </div>

        <fieldset class="grid gap-2">
            <legend class="mb-1 text-sm font-medium">
                {{ $t('Specifications') }}
            </legend>
            <div
                v-for="(spec, index) in form.specs"
                :key="index"
                class="grid gap-1"
            >
                <div class="flex flex-wrap items-center gap-2">
                    <Input
                        v-model="spec.key"
                        class="flex-1"
                        maxlength="60"
                        :placeholder="$t('Property')"
                        :aria-label="$t('Property')"
                        :disabled="disabled"
                    />
                    <Input
                        v-model="spec.value"
                        class="flex-1"
                        maxlength="120"
                        :placeholder="$t('Value')"
                        :aria-label="$t('Value')"
                        :disabled="disabled"
                    />
                    <Input
                        v-model="spec.unit"
                        class="w-20"
                        maxlength="20"
                        :placeholder="$t('Unit')"
                        :aria-label="$t('Unit')"
                        :disabled="disabled"
                    />
                    <Button
                        v-if="!disabled"
                        type="button"
                        variant="ghost"
                        size="icon"
                        :aria-label="$t('Remove')"
                        @click="form.specs.splice(index, 1)"
                    >
                        <Trash2 class="size-4" />
                    </Button>
                </div>
                <InputError
                    :message="
                        errors[`specs.${index}.key`] ??
                        errors[`specs.${index}.value`]
                    "
                />
            </div>
            <Button
                v-if="!disabled"
                type="button"
                variant="outline"
                size="sm"
                class="justify-self-start"
                @click="form.specs.push({ key: '', value: '', unit: '' })"
            >
                <Plus class="size-4" />
                {{ $t('Add specification') }}
            </Button>
        </fieldset>

        <div v-if="!disabled" class="flex justify-end">
            <Button
                type="submit"
                :disabled="form.processing"
                data-test="save-part"
            >
                {{ part ? $t('Save part') : $t('Create part') }}
            </Button>
        </div>
    </form>
</template>
