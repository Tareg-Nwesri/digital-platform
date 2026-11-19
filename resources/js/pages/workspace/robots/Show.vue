<script setup lang="ts">
import FormOption from '@/components/forms/FormOption.vue';
import FormSelect from '@/components/forms/FormSelect.vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import { Plus, Trash2 } from '@lucide/vue';
import { trans } from 'laravel-vue-i18n';
import { computed, ref } from 'vue';
import Money from '@/components/finance/Money.vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { search as searchParts } from '@/routes/workspace/catalogue';
import { bom as bomRoute, destroy, update } from '@/routes/workspace/robots';

type MoneyValue = { amountMinor: number; currency: string; formatted: string };
type Offer = {
    id: number;
    vendor: string;
    formatted: string;
    inStock: boolean;
};
type Line = {
    partId: number;
    part: string;
    partNumber: string | null;
    offerId: number | null;
    vendor: string | null;
    quantity: number;
    notes: string | null;
    unit: MoneyValue | null;
    total: MoneyValue | null;
    availability: string;
    offers: Offer[];
};

const props = defineProps<{
    team: { slug: string; name: string };
    robot: {
        id: number;
        name: string;
        slug: string;
        description_en: string | null;
        description_ar: string | null;
        weight_g: number | null;
        length_mm: number | null;
        width_mm: number | null;
        height_mm: number | null;
        is_public: boolean;
        project_id: number | null;
        status: string;
    };
    bom: {
        lines: Line[];
        totals: MoneyValue[];
        byVendor: {
            vendor: string;
            phone: string | null;
            total: MoneyValue;
            lines: { part: string; quantity: number }[];
        }[];
        unpriced: number;
    };
    statuses: string[];
    projects: { id: number; title: string }[];
    can: { edit: boolean };
}>();

const key = () => [props.team.slug, props.robot.slug] as [string, string];

/* Details */
const details = useForm({
    name: props.robot.name,
    status: props.robot.status,
    project_id: props.robot.project_id,
    description_ar: props.robot.description_ar ?? '',
    description_en: props.robot.description_en ?? '',
    weight_g: props.robot.weight_g ?? ('' as number | ''),
    length_mm: props.robot.length_mm ?? ('' as number | ''),
    width_mm: props.robot.width_mm ?? ('' as number | ''),
    height_mm: props.robot.height_mm ?? ('' as number | ''),
    is_public: props.robot.is_public,
});

function saveDetails(): void {
    details
        .transform((data) =>
            Object.fromEntries(
                Object.entries(data).map(([field, value]) => [
                    field,
                    value === '' ? null : value,
                ]),
            ),
        )
        .patch(update(key()).url, { preserveScroll: true });
}

/* Parts list */
const lines = ref(props.bom.lines.map((line) => ({ ...line })));
const bomForm = useForm({
    lines: [] as {
        part_id: number;
        vendor_offer_id: number | null;
        quantity: number;
        notes: string | null;
    }[],
});
const bomErrors = computed(
    () => bomForm.errors as Record<string, string | undefined>,
);

const query = ref('');
const results = ref<
    { id: number; name: string; partNumber: string | null; offers: Offer[] }[]
>([]);
let timer: ReturnType<typeof setTimeout> | undefined;

function onSearch(): void {
    clearTimeout(timer);
    timer = setTimeout(async () => {
        if (query.value.trim().length < 2) {
            results.value = [];

            return;
        }

        const response = await fetch(
            searchParts(props.team.slug, { query: { q: query.value } }).url,
            { headers: { Accept: 'application/json' } },
        );
        results.value = (await response.json()).data;
    }, 300);
}

function addPart(part: {
    id: number;
    name: string;
    partNumber: string | null;
    offers: Offer[];
}): void {
    lines.value.push({
        partId: part.id,
        part: part.name,
        partNumber: part.partNumber,
        offerId: part.offers[0]?.id ?? null,
        vendor: null,
        quantity: 1,
        notes: null,
        unit: null,
        total: null,
        availability: 'available',
        offers: part.offers,
    });
    query.value = '';
    results.value = [];
}

function saveBom(): void {
    bomForm.lines = lines.value.map((line) => ({
        part_id: line.partId,
        vendor_offer_id: line.offerId,
        quantity: line.quantity,
        notes: line.notes || null,
    }));
    bomForm.put(bomRoute(key()).url, {
        preserveScroll: true,
        onSuccess: () =>
            (lines.value = props.bom.lines.map((line) => ({ ...line }))),
    });
}

function remove(): void {
    if (window.confirm(trans('Delete this robot? This cannot be undone.'))) {
        router.delete(destroy(key()).url);
    }
}

const sizeFields = [
    ['weight_g', 'Weight (g)'],
    ['length_mm', 'Length (mm)'],
    ['width_mm', 'Width (mm)'],
    ['height_mm', 'Height (mm)'],
] as const;
</script>

<template>
    <Head :title="robot.name" />

    <div class="flex flex-col gap-6 p-4 md:p-6">
        <div class="flex flex-wrap items-center gap-3">
            <Heading :title="robot.name" :description="team.name" />
            <Badge variant="secondary">{{
                $t(`work.robot_status.${robot.status}`)
            }}</Badge>
        </div>

        <section
            class="grid gap-4 rounded-xl border bg-card p-5"
            data-tour="robot-parts"
        >
            <div class="flex flex-wrap items-end justify-between gap-3">
                <h3 class="font-semibold">{{ $t('Parts list') }}</h3>
                <div class="flex flex-wrap gap-3 text-sm">
                    <span
                        v-for="total in bom.totals"
                        :key="total.currency"
                        class="font-semibold"
                        ><Money :value="total"
                    /></span>
                    <span
                        v-if="bom.unpriced > 0"
                        class="text-muted-foreground"
                        >{{
                            $t(':count without a price', {
                                count: String(bom.unpriced),
                            })
                        }}</span
                    >
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="border-b text-xs text-muted-foreground">
                        <tr>
                            <th class="py-2 text-start font-medium">
                                {{ $t('Part') }}
                            </th>
                            <th class="py-2 text-start font-medium">
                                {{ $t('Price') }}
                            </th>
                            <th class="py-2 text-start font-medium">
                                {{ $t('Quantity') }}
                            </th>
                            <th class="py-2 text-end font-medium">
                                {{ $t('Total') }}
                            </th>
                            <th class="py-2">
                                <span class="sr-only">{{ $t('Actions') }}</span>
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="lines.length === 0">
                            <td
                                colspan="5"
                                class="py-6 text-center text-muted-foreground"
                            >
                                {{ $t('No parts yet.') }}
                            </td>
                        </tr>
                        <tr
                            v-for="(line, index) in lines"
                            :key="`${line.partId}-${index}`"
                            class="border-b align-top last:border-0"
                        >
                            <td class="py-2 pe-3">
                                <div class="font-medium">{{ line.part }}</div>
                                <div
                                    class="text-xs"
                                    :class="
                                        line.availability === 'available'
                                            ? 'text-muted-foreground'
                                            : 'text-amber-700 dark:text-amber-400'
                                    "
                                >
                                    <span v-if="line.partNumber" dir="ltr"
                                        >{{ line.partNumber }} · </span
                                    >{{
                                        $t(
                                            `work.availability.${line.availability}`,
                                        )
                                    }}
                                </div>
                                <InputError
                                    :message="
                                        bomErrors[`lines.${index}.part_id`]
                                    "
                                />
                            </td>
                            <td class="py-2 pe-3">
                                <FormSelect
                                    v-model="line.offerId"
                                    class="min-w-44"
                                    :disabled="!can.edit"
                                    :aria-label="$t('Price')"
                                >
                                    <FormOption :value="null">
                                        {{ $t('No price') }}
                                    </FormOption>
                                    <FormOption
                                        v-for="offer in line.offers"
                                        :key="offer.id"
                                        :value="offer.id"
                                    >
                                        {{ offer.vendor }} ·
                                        {{ offer.formatted }}
                                    </FormOption>
                                </FormSelect>
                            </td>
                            <td class="py-2 pe-3">
                                <Input
                                    v-model.number="line.quantity"
                                    type="number"
                                    min="1"
                                    max="10000"
                                    class="w-20"
                                    dir="ltr"
                                    :disabled="!can.edit"
                                    :aria-label="$t('Quantity')"
                                />
                                <InputError
                                    :message="
                                        bomErrors[`lines.${index}.quantity`]
                                    "
                                />
                            </td>
                            <td class="py-2 text-end">
                                <Money :value="line.total" />
                            </td>
                            <td class="py-2 text-end">
                                <Button
                                    v-if="can.edit"
                                    variant="ghost"
                                    size="icon"
                                    :aria-label="$t('Remove')"
                                    @click="lines.splice(index, 1)"
                                >
                                    <Trash2 class="size-4" />
                                </Button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div v-if="can.edit" class="grid gap-2">
                <Label for="part-search">{{
                    $t('Add a part from the catalogue')
                }}</Label>
                <Input
                    id="part-search"
                    v-model="query"
                    :placeholder="$t('Search by name or part number')"
                    @input="onSearch"
                />
                <ul
                    v-if="results.length"
                    class="grid gap-1 rounded-lg border p-1"
                >
                    <li v-for="part in results" :key="part.id">
                        <button
                            type="button"
                            class="flex w-full items-center justify-between gap-2 rounded-md px-3 py-2 text-start text-sm hover:bg-accent"
                            @click="addPart(part)"
                        >
                            <span
                                >{{ part.name }}
                                <span
                                    v-if="part.partNumber"
                                    class="text-xs text-muted-foreground"
                                    dir="ltr"
                                    >{{ part.partNumber }}</span
                                ></span
                            >
                            <span
                                class="flex items-center gap-1 text-xs text-muted-foreground"
                                ><Plus class="size-3" />{{
                                    part.offers[0]?.formatted ?? $t('No price')
                                }}</span
                            >
                        </button>
                    </li>
                </ul>
                <InputError :message="bomErrors.lines" />
                <div class="flex justify-end">
                    <Button
                        :disabled="bomForm.processing"
                        data-test="save-bom"
                        data-tour="robot-save-parts"
                        @click="saveBom"
                        >{{ $t('Save parts list') }}</Button
                    >
                </div>
            </div>
        </section>

        <section
            v-if="bom.byVendor.length"
            class="grid gap-3 rounded-xl border bg-card p-5 print:border-0"
            data-tour="robot-vendors"
        >
            <h3 class="font-semibold">{{ $t('Shopping list by vendor') }}</h3>
            <div
                v-for="group in bom.byVendor"
                :key="`${group.vendor}-${group.total.currency}`"
                class="grid gap-1 text-sm"
            >
                <p class="flex justify-between gap-2 font-medium">
                    <span
                        >{{ group.vendor }}
                        <span
                            v-if="group.phone"
                            class="text-xs text-muted-foreground"
                            dir="ltr"
                            >{{ group.phone }}</span
                        ></span
                    >
                    <Money :value="group.total" />
                </p>
                <p
                    v-for="(item, index) in group.lines"
                    :key="index"
                    class="text-muted-foreground"
                >
                    {{ item.part }} × <span dir="ltr">{{ item.quantity }}</span>
                </p>
            </div>
        </section>

        <section
            class="grid gap-4 rounded-xl border bg-card p-5"
            data-tour="robot-details"
        >
            <h3 class="font-semibold">{{ $t('Details') }}</h3>
            <form class="grid gap-4" @submit.prevent="saveDetails">
                <div class="grid gap-4 sm:grid-cols-3">
                    <div class="grid gap-2">
                        <Label for="robot-name">{{ $t('Name') }}</Label>
                        <Input
                            id="robot-name"
                            v-model="details.name"
                            maxlength="120"
                            required
                            :disabled="!can.edit"
                        />
                        <InputError :message="details.errors.name" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="robot-status">{{ $t('Status') }}</Label>
                        <FormSelect
                            id="robot-status"
                            v-model="details.status"
                            :disabled="!can.edit"
                        >
                            <FormOption
                                v-for="status in statuses"
                                :key="status"
                                :value="status"
                            >
                                {{ $t(`work.robot_status.${status}`) }}
                            </FormOption>
                        </FormSelect>
                    </div>
                    <div class="grid gap-2">
                        <Label for="robot-project">{{ $t('Project') }}</Label>
                        <FormSelect
                            id="robot-project"
                            v-model="details.project_id"
                            :disabled="!can.edit"
                        >
                            <FormOption :value="null">
                                {{ $t('No project') }}
                            </FormOption>
                            <FormOption
                                v-for="project in projects"
                                :key="project.id"
                                :value="project.id"
                            >
                                {{ project.title }}
                            </FormOption>
                        </FormSelect>
                        <InputError :message="details.errors.project_id" />
                    </div>
                </div>
                <div class="grid gap-4 sm:grid-cols-4">
                    <div
                        v-for="[field, label] in sizeFields"
                        :key="field"
                        class="grid gap-2"
                    >
                        <Label :for="`robot-${field}`">{{ $t(label) }}</Label>
                        <Input
                            :id="`robot-${field}`"
                            v-model.number="details[field]"
                            type="number"
                            min="1"
                            dir="ltr"
                            :disabled="!can.edit"
                        />
                    </div>
                </div>
                <div class="grid gap-2">
                    <Label for="robot-description-ar">{{
                        $t('Description (Arabic)')
                    }}</Label>
                    <textarea
                        id="robot-description-ar"
                        v-model="details.description_ar"
                        rows="2"
                        dir="rtl"
                        class="rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs"
                        :disabled="!can.edit"
                    />
                </div>
                <div class="grid gap-2">
                    <Label for="robot-description-en">{{
                        $t('Description (English)')
                    }}</Label>
                    <textarea
                        id="robot-description-en"
                        v-model="details.description_en"
                        rows="2"
                        dir="ltr"
                        class="rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs"
                        :disabled="!can.edit"
                    />
                </div>
                <label class="flex items-center gap-3 text-sm">
                    <Checkbox
                        :model-value="details.is_public"
                        :disabled="!can.edit"
                        @update:model-value="
                            (value) => (details.is_public = value === true)
                        "
                    />
                    {{ $t('Show this robot on the public website') }}
                </label>
                <div
                    v-if="can.edit"
                    class="flex flex-wrap justify-between gap-2"
                >
                    <Button
                        type="button"
                        variant="ghost"
                        class="text-destructive"
                        @click="remove"
                        >{{ $t('Delete robot') }}</Button
                    >
                    <Button type="submit" :disabled="details.processing">{{
                        $t('Save robot')
                    }}</Button>
                </div>
            </form>
        </section>
    </div>
</template>
