<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { computed, watch } from 'vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { store, update } from '@/routes/console/tiers';

export type FeatureDefinition = {
    name: string;
    type: 'number' | 'boolean';
    fallback: boolean | number;
};

export type TierFormValue = {
    slug: string;
    nameEn: string;
    nameAr: string | null;
    features: { name: string; value: boolean | number }[];
};

const props = defineProps<{
    definitions: FeatureDefinition[];
    tier?: TierFormValue | null;
}>();

const open = defineModel<boolean>('open', { required: true });

type FormShape = {
    name_en: string;
    name_ar: string;
    slug: string;
    features: Record<string, boolean | number>;
};

function initialFeatures(): Record<string, boolean | number> {
    return Object.fromEntries(
        props.definitions.map((definition) => [
            definition.name,
            props.tier?.features.find((f) => f.name === definition.name)
                ?.value ?? definition.fallback,
        ]),
    );
}

const form = useForm<FormShape>({
    name_en: props.tier?.nameEn ?? '',
    name_ar: props.tier?.nameAr ?? '',
    slug: props.tier?.slug ?? '',
    features: initialFeatures(),
});

watch(open, (isOpen) => {
    if (isOpen) {
        form.defaults({
            name_en: props.tier?.nameEn ?? '',
            name_ar: props.tier?.nameAr ?? '',
            slug: props.tier?.slug ?? '',
            features: initialFeatures(),
        });
        form.reset();
        form.clearErrors();
    }
});

const errors = computed(
    () => form.errors as Record<string, string | undefined>,
);

function featureError(index: number): string | undefined {
    return (
        errors.value[`features.${index}.value`] ??
        errors.value[`features.${index}.name`]
    );
}

function submit(): void {
    const options = {
        preserveScroll: true,
        onSuccess: () => (open.value = false),
    };

    form.transform((data) => ({
        ...data,
        name_ar: data.name_ar || null,
        slug: data.slug || null,
        features: props.definitions.map((definition) => ({
            name: definition.name,
            value: data.features[definition.name],
        })),
    }));

    if (props.tier) {
        form.patch(update(props.tier.slug).url, options);
    } else {
        form.post(store().url, options);
    }
}
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent class="max-h-[90vh] overflow-y-auto sm:max-w-xl">
            <DialogHeader>
                <DialogTitle>
                    {{ tier ? $t('Edit tier') : $t('New tier') }}
                </DialogTitle>
                <DialogDescription>
                    {{
                        $t(
                            'Teams on this tier get these features unless a team has its own override.',
                        )
                    }}
                </DialogDescription>
            </DialogHeader>

            <form class="grid gap-5" @submit.prevent="submit">
                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="grid gap-2">
                        <Label for="tier-name-en">{{
                            $t('Name (English)')
                        }}</Label>
                        <Input
                            id="tier-name-en"
                            v-model="form.name_en"
                            dir="ltr"
                            required
                            maxlength="80"
                        />
                        <InputError :message="form.errors.name_en" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="tier-name-ar">{{
                            $t('Name (Arabic)')
                        }}</Label>
                        <Input
                            id="tier-name-ar"
                            v-model="form.name_ar"
                            dir="rtl"
                            maxlength="80"
                        />
                        <InputError :message="form.errors.name_ar" />
                    </div>
                </div>
                <div class="grid gap-2">
                    <Label for="tier-slug">{{ $t('Web address') }}</Label>
                    <Input
                        id="tier-slug"
                        v-model="form.slug"
                        dir="ltr"
                        maxlength="80"
                        :placeholder="$t('Made from the name if left empty')"
                    />
                    <InputError :message="form.errors.slug" />
                </div>

                <fieldset class="grid gap-3">
                    <legend class="mb-1 text-sm font-medium">
                        {{ $t('Features') }}
                    </legend>
                    <div
                        v-for="(definition, index) in definitions"
                        :key="definition.name"
                        class="grid gap-1"
                    >
                        <div
                            class="flex items-center justify-between gap-4 rounded-md border px-3 py-2"
                        >
                            <Label
                                :for="`tier-feature-${index}`"
                                class="font-normal"
                            >
                                {{ $t(`feature.${definition.name}`) }}
                            </Label>
                            <Input
                                v-if="definition.type === 'number'"
                                :id="`tier-feature-${index}`"
                                :model-value="
                                    Number(form.features[definition.name])
                                "
                                type="number"
                                min="1"
                                max="500"
                                class="w-24 tabular-nums"
                                dir="ltr"
                                @update:model-value="
                                    (value) =>
                                        (form.features[definition.name] =
                                            Number(value))
                                "
                            />
                            <Checkbox
                                v-else
                                :id="`tier-feature-${index}`"
                                :model-value="
                                    Boolean(form.features[definition.name])
                                "
                                @update:model-value="
                                    (value) =>
                                        (form.features[definition.name] =
                                            value === true)
                                "
                            />
                        </div>
                        <InputError :message="featureError(index)" />
                    </div>
                </fieldset>

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
                        data-test="save-tier"
                    >
                        {{ tier ? $t('Save tier') : $t('Create tier') }}
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
