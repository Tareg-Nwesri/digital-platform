<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { Plus, Trash2 } from '@lucide/vue';
import { trans } from 'laravel-vue-i18n';
import { computed } from 'vue';
import CatalogueNav from '@/components/catalogue/CatalogueNav.vue';
import CatalogueStatusBadge from '@/components/catalogue/CatalogueStatusBadge.vue';
import PartForm from '@/components/catalogue/PartForm.vue';
import type { Spec } from '@/components/catalogue/PartForm.vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { dashboard } from '@/routes/console';
import {
    destroy,
    index,
    offers as offersRoute,
    restore,
} from '@/routes/console/catalogue/parts';

type Offer = {
    id: number;
    vendor: { id: number; name: string };
    price: string;
    currency: string;
    formatted: string;
    url: string | null;
    inStock: boolean;
    checkedAt: string | null;
    status: string;
};

const props = defineProps<{
    part: {
        id: number;
        name: string;
        partNumber: string | null;
        categoryId: number | null;
        category: string | null;
        descriptionEn: string | null;
        descriptionAr: string | null;
        specs: Spec[];
        status: string;
        team: string | null;
        suggestedBy: string | null;
    };
    offers: Offer[];
    vendors: { id: number; name: string }[];
    categories: { id: number; slug: string; name: string; depth: number }[];
    currencies: string[];
    can: { manage: boolean };
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Organisation console', href: dashboard() },
            { title: 'Catalogue', href: index() },
        ],
    },
});

const approvedOffers = computed(() =>
    props.offers.filter((offer) => offer.status === 'approved'),
);
const pendingOffers = computed(() =>
    props.offers.filter((offer) => offer.status === 'suggested'),
);

type OfferRow = {
    id: number | null;
    vendor_id: number | null;
    price: string;
    currency: string;
    url: string;
    in_stock: boolean;
    checked_at: string;
};

const form = useForm<{ offers: OfferRow[] }>({
    offers: approvedOffers.value.map((offer) => ({
        id: offer.id,
        vendor_id: offer.vendor.id,
        price: offer.price,
        currency: offer.currency,
        url: offer.url ?? '',
        in_stock: offer.inStock,
        checked_at: offer.checkedAt ?? '',
    })),
});

const errors = computed(
    () => form.errors as Record<string, string | undefined>,
);

function addOffer(): void {
    form.offers.push({
        id: null,
        vendor_id: props.vendors[0]?.id ?? null,
        price: '',
        currency: props.currencies[0] ?? 'LYD',
        url: '',
        in_stock: true,
        checked_at: new Date().toISOString().slice(0, 10),
    });
}

function saveOffers(): void {
    form.transform((data) => ({
        offers: data.offers.map((row) => ({
            ...row,
            url: row.url || null,
            checked_at: row.checked_at || null,
        })),
    })).put(offersRoute(props.part.id).url, { preserveScroll: true });
}

function archive(): void {
    if (
        window.confirm(
            trans('Archive this part? It stays in existing parts lists.'),
        )
    ) {
        router.delete(destroy(props.part.id).url, { preserveScroll: true });
    }
}

function restorePart(): void {
    router.patch(restore(props.part.id).url, {}, { preserveScroll: true });
}

const selectClass =
    'h-9 rounded-md border border-input bg-transparent px-2 text-sm shadow-xs';
</script>

<template>
    <Head :title="part.name" />

    <div class="flex flex-col gap-6 p-4 md:p-6">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div class="flex flex-wrap items-center gap-3">
                <Heading
                    :title="part.name"
                    :description="
                        part.team
                            ? $t('Suggested by :name for :team', {
                                  name: part.suggestedBy ?? '—',
                                  team: part.team,
                              })
                            : (part.category ?? $t('No category'))
                    "
                />
                <CatalogueStatusBadge :status="part.status" />
            </div>
            <div v-if="can.manage" class="flex gap-2">
                <Button
                    v-if="part.status === 'approved'"
                    variant="outline"
                    @click="archive"
                >
                    {{ $t('Archive') }}
                </Button>
                <Button
                    v-if="part.status === 'archived'"
                    variant="outline"
                    @click="restorePart"
                >
                    {{ $t('Restore') }}
                </Button>
            </div>
        </div>

        <CatalogueNav current="parts" />

        <div class="grid gap-6 xl:grid-cols-2">
            <section class="rounded-xl border bg-card p-5">
                <h3 class="mb-4 font-semibold">{{ $t('Details') }}</h3>
                <PartForm
                    :part="part"
                    :categories="categories"
                    :disabled="!can.manage"
                />
            </section>

            <section
                class="grid content-start gap-4 rounded-xl border bg-card p-5"
            >
                <div class="flex items-center justify-between gap-2">
                    <h3 class="font-semibold">{{ $t('Offers') }}</h3>
                    <Button
                        v-if="can.manage && vendors.length > 0"
                        type="button"
                        variant="outline"
                        size="sm"
                        @click="addOffer"
                    >
                        <Plus class="size-4" />
                        {{ $t('Add offer') }}
                    </Button>
                </div>
                <p
                    v-if="vendors.length === 0 && can.manage"
                    class="text-sm text-muted-foreground"
                >
                    {{ $t('Add a vendor first, then record its prices here.') }}
                </p>
                <p
                    v-if="form.offers.length === 0"
                    class="text-sm text-muted-foreground"
                >
                    {{ $t('No offers yet.') }}
                </p>

                <div
                    v-for="(row, index) in form.offers"
                    :key="row.id ?? `new-${index}`"
                    class="grid gap-2 rounded-lg border p-3"
                    :data-test="`offer-${index}`"
                >
                    <div class="flex flex-wrap items-center gap-2">
                        <select
                            v-model="row.vendor_id"
                            :class="selectClass"
                            class="min-w-40 flex-1"
                            :disabled="!can.manage"
                            :aria-label="$t('Vendor')"
                        >
                            <option
                                v-for="vendor in vendors"
                                :key="vendor.id"
                                :value="vendor.id"
                            >
                                {{ vendor.name }}
                            </option>
                        </select>
                        <Input
                            v-model="row.price"
                            inputmode="decimal"
                            class="w-28 tabular-nums"
                            dir="ltr"
                            :placeholder="$t('Price')"
                            :aria-label="$t('Price')"
                            :disabled="!can.manage"
                        />
                        <select
                            v-model="row.currency"
                            :class="selectClass"
                            :disabled="!can.manage"
                            :aria-label="$t('Currency')"
                        >
                            <option
                                v-for="currency in currencies"
                                :key="currency"
                                :value="currency"
                            >
                                {{ currency }}
                            </option>
                        </select>
                        <Button
                            v-if="can.manage"
                            type="button"
                            variant="ghost"
                            size="icon"
                            :aria-label="$t('Remove')"
                            @click="form.offers.splice(index, 1)"
                        >
                            <Trash2 class="size-4" />
                        </Button>
                    </div>
                    <div class="flex flex-wrap items-center gap-3">
                        <Input
                            v-model="row.url"
                            class="min-w-48 flex-1"
                            dir="ltr"
                            :placeholder="$t('Product link')"
                            :aria-label="$t('Product link')"
                            :disabled="!can.manage"
                        />
                        <Input
                            v-model="row.checked_at"
                            type="date"
                            class="w-40"
                            dir="ltr"
                            :aria-label="$t('Price checked on')"
                            :disabled="!can.manage"
                        />
                        <label class="flex items-center gap-2 text-sm">
                            <Checkbox
                                :model-value="row.in_stock"
                                :disabled="!can.manage"
                                @update:model-value="
                                    (value) => (row.in_stock = value === true)
                                "
                            />
                            {{ $t('In stock') }}
                        </label>
                    </div>
                    <InputError
                        :message="
                            errors[`offers.${index}.price`] ??
                            errors[`offers.${index}.currency`] ??
                            errors[`offers.${index}.url`] ??
                            errors[`offers.${index}.vendor_id`]
                        "
                    />
                </div>

                <div v-if="can.manage" class="flex justify-end">
                    <Button
                        :disabled="form.processing"
                        data-test="save-offers"
                        @click="saveOffers"
                    >
                        {{ $t('Save offers') }}
                    </Button>
                </div>

                <div
                    v-if="pendingOffers.length > 0"
                    class="grid gap-2 border-t pt-4"
                >
                    <h4 class="text-sm font-medium">
                        {{ $t('Suggested offers waiting for review') }}
                    </h4>
                    <p
                        v-for="offer in pendingOffers"
                        :key="offer.id"
                        class="flex justify-between gap-2 text-sm"
                    >
                        <span>{{ offer.vendor.name }}</span>
                        <span class="tabular-nums" dir="ltr">{{
                            offer.formatted
                        }}</span>
                    </p>
                </div>
            </section>
        </div>
    </div>
</template>
