<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { Plus, Trash2 } from '@lucide/vue';
import { computed } from 'vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { formatNumber } from '@/lib/i18n';
import { categories as categoriesRoute } from '@/routes/console/events';

export type Category = {
    id: number;
    name: string;
    nameEn: string;
    nameAr: string | null;
    ageMin: number | null;
    ageMax: number | null;
    membersMin: number;
    membersMax: number;
    robotsPerTeam: number;
    capacity: number | null;
    approved: number;
};

type Row = {
    id: number | null;
    name_en: string;
    name_ar: string;
    age_min: number | '';
    age_max: number | '';
    members_min: number;
    members_max: number;
    robots_per_team: number;
    capacity: number | '';
};

const props = defineProps<{
    eventSlug: string;
    categories: Category[];
    editable: boolean;
}>();

const form = useForm<{ categories: Row[] }>({
    categories: props.categories.map((category) => ({
        id: category.id,
        name_en: category.nameEn,
        name_ar: category.nameAr ?? '',
        age_min: category.ageMin ?? '',
        age_max: category.ageMax ?? '',
        members_min: category.membersMin,
        members_max: category.membersMax,
        robots_per_team: category.robotsPerTeam,
        capacity: category.capacity ?? '',
    })),
});

const errors = computed(
    () => form.errors as Record<string, string | undefined>,
);

function add(): void {
    form.categories.push({
        id: null,
        name_en: '',
        name_ar: '',
        age_min: '',
        age_max: '',
        members_min: 1,
        members_max: 5,
        robots_per_team: 1,
        capacity: '',
    });
}

function remove(index: number): void {
    form.categories.splice(index, 1);
}

const blank = (value: number | '') => (value === '' ? null : value);

function save(): void {
    form.transform((data) => ({
        categories: data.categories.map((row) => ({
            ...row,
            name_ar: row.name_ar || null,
            age_min: blank(row.age_min),
            age_max: blank(row.age_max),
            capacity: blank(row.capacity),
        })),
    })).put(categoriesRoute(props.eventSlug).url, { preserveScroll: true });
}

const numberClass = 'w-20 tabular-nums';
</script>

<template>
    <div class="grid gap-4">
        <p
            v-if="!editable && categories.length === 0"
            class="text-sm text-muted-foreground"
        >
            {{ $t('No categories yet.') }}
        </p>

        <div
            v-for="(row, index) in form.categories"
            :key="row.id ?? `new-${index}`"
            class="grid gap-3 rounded-xl border bg-card p-4"
            :data-test="`category-${index}`"
        >
            <div class="flex flex-wrap items-end gap-3">
                <div class="grid flex-1 gap-1.5">
                    <Label :for="`category-name-en-${index}`">{{
                        $t('Name (English)')
                    }}</Label>
                    <Input
                        :id="`category-name-en-${index}`"
                        v-model="row.name_en"
                        :disabled="!editable"
                        dir="ltr"
                        maxlength="120"
                    />
                </div>
                <div class="grid flex-1 gap-1.5">
                    <Label :for="`category-name-ar-${index}`">{{
                        $t('Name (Arabic)')
                    }}</Label>
                    <Input
                        :id="`category-name-ar-${index}`"
                        v-model="row.name_ar"
                        :disabled="!editable"
                        dir="rtl"
                        maxlength="120"
                    />
                </div>
                <Button
                    v-if="editable"
                    type="button"
                    variant="ghost"
                    size="icon"
                    class="text-destructive"
                    :aria-label="$t('Remove category')"
                    @click="remove(index)"
                >
                    <Trash2 class="size-4" />
                </Button>
            </div>
            <InputError :message="errors[`categories.${index}.name_en`]" />

            <div class="flex flex-wrap gap-4 text-sm">
                <fieldset class="grid gap-1.5">
                    <legend class="text-xs text-muted-foreground">
                        {{ $t('Members per team') }}
                    </legend>
                    <div class="flex items-center gap-2">
                        <Input
                            v-model.number="row.members_min"
                            type="number"
                            min="1"
                            max="20"
                            :class="numberClass"
                            :disabled="!editable"
                            :aria-label="$t('Minimum')"
                            dir="ltr"
                        />
                        <span>–</span>
                        <Input
                            v-model.number="row.members_max"
                            type="number"
                            min="1"
                            max="20"
                            :class="numberClass"
                            :disabled="!editable"
                            :aria-label="$t('Maximum')"
                            dir="ltr"
                        />
                    </div>
                    <InputError
                        :message="
                            errors[`categories.${index}.members_max`] ??
                            errors[`categories.${index}.members_min`]
                        "
                    />
                </fieldset>
                <fieldset class="grid gap-1.5">
                    <legend class="text-xs text-muted-foreground">
                        {{ $t('Age on the event day') }}
                    </legend>
                    <div class="flex items-center gap-2">
                        <Input
                            v-model.number="row.age_min"
                            type="number"
                            min="3"
                            max="99"
                            :class="numberClass"
                            :disabled="!editable"
                            :aria-label="$t('Minimum')"
                            :placeholder="$t('Any')"
                            dir="ltr"
                        />
                        <span>–</span>
                        <Input
                            v-model.number="row.age_max"
                            type="number"
                            min="3"
                            max="99"
                            :class="numberClass"
                            :disabled="!editable"
                            :aria-label="$t('Maximum')"
                            :placeholder="$t('Any')"
                            dir="ltr"
                        />
                    </div>
                    <InputError
                        :message="
                            errors[`categories.${index}.age_max`] ??
                            errors[`categories.${index}.age_min`]
                        "
                    />
                </fieldset>
                <div class="grid gap-1.5">
                    <Label
                        :for="`category-robots-${index}`"
                        class="text-xs font-normal text-muted-foreground"
                        >{{ $t('Robots per team') }}</Label
                    >
                    <Input
                        :id="`category-robots-${index}`"
                        v-model.number="row.robots_per_team"
                        type="number"
                        min="0"
                        max="10"
                        :class="numberClass"
                        :disabled="!editable"
                        dir="ltr"
                    />
                </div>
                <div class="grid gap-1.5">
                    <Label
                        :for="`category-capacity-${index}`"
                        class="text-xs font-normal text-muted-foreground"
                        >{{ $t('Capacity (teams)') }}</Label
                    >
                    <Input
                        :id="`category-capacity-${index}`"
                        v-model.number="row.capacity"
                        type="number"
                        min="1"
                        :class="numberClass"
                        :disabled="!editable"
                        :placeholder="$t('Any')"
                        dir="ltr"
                    />
                </div>
                <div
                    v-if="row.id !== null"
                    class="grid content-end gap-1.5 text-xs text-muted-foreground"
                >
                    {{
                        $t(':count approved', {
                            count: formatNumber(
                                categories.find((c) => c.id === row.id)
                                    ?.approved ?? 0,
                            ),
                        })
                    }}
                </div>
            </div>
        </div>

        <InputError :message="errors.categories" />

        <div v-if="editable" class="flex flex-wrap justify-between gap-2">
            <Button type="button" variant="outline" @click="add">
                <Plus class="size-4" />
                {{ $t('Add category') }}
            </Button>
            <Button
                type="button"
                :disabled="form.processing || form.categories.length === 0"
                data-test="save-categories"
                @click="save"
            >
                {{ $t('Save categories') }}
            </Button>
        </div>
    </div>
</template>
