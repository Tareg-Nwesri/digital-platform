<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { ExternalLink, Lightbulb, Search } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import OfferSuggestionFields from '@/components/catalogue/OfferSuggestionFields.vue';
import type { OfferDraft } from '@/components/catalogue/OfferSuggestionFields.vue';
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
import { formatDate } from '@/lib/i18n';
import { index } from '@/routes/workspace/catalogue';
import { store as storeOffer } from '@/routes/workspace/catalogue/offers';
import { store as storePart } from '@/routes/workspace/catalogue/parts';
import { store as storeVendor } from '@/routes/workspace/catalogue/vendors';

type PartRow = {
    id: number;
    name: string;
    partNumber: string | null;
    category: string | null;
    description: string | null;
    status: string;
    offers: {
        id: number;
        vendor: string;
        price: string;
        inStock: boolean;
        url: string | null;
    }[];
};

const props = defineProps<{
    team: { slug: string; name: string };
    parts: {
        data: PartRow[];
        current_page: number;
        last_page: number;
        total: number;
        prev_page_url: string | null;
        next_page_url: string | null;
    };
    filters: { q: string; category_id: number | null; in_stock: boolean };
    categories: { id: number; name: string }[];
    vendors: { id: number; name: string }[];
    suggestions: {
        type: 'part' | 'vendor' | 'offer';
        id: number;
        name: string;
        status: string;
        note: string | null;
        createdAt: string | null;
    }[];
    currencies: string[];
    can: { suggest: boolean };
}>();

const q = ref(props.filters.q);
const categoryId = ref<number | null>(props.filters.category_id);
const inStock = ref(props.filters.in_stock);

function applyFilters(): void {
    router.get(
        index(props.team.slug).url,
        {
            q: q.value || undefined,
            category_id: categoryId.value ?? undefined,
            in_stock: inStock.value ? 1 : undefined,
        },
        { preserveState: true, preserveScroll: true, replace: true },
    );
}

let timer: ReturnType<typeof setTimeout> | undefined;
watch(q, () => {
    clearTimeout(timer);
    timer = setTimeout(applyFilters, 300);
});
watch([categoryId, inStock], applyFilters);

/* Suggestions */
type Kind = 'part' | 'vendor' | 'offer';
const suggesting = ref<Kind | null>(null);

const partForm = useForm({
    name: '',
    part_number: '',
    category_id: null as number | null,
    description_en: '',
});
const vendorForm = useForm({ name: '', website: '', phone: '', city: '' });
const blankOffer = () => ({
    part_id: null as number | null,
    vendor_id: null as number | null,
    price: '',
    currency: 'LYD',
    url: '',
    in_stock: true,
});
const offer = ref<OfferDraft>(blankOffer());
const offerErrors = ref<Record<string, string>>({});
const offerBusy = ref(false);

const busy = computed(
    () => partForm.processing || vendorForm.processing || offerBusy.value,
);

function openOffer(part: PartRow): void {
    offer.value = { ...blankOffer(), part_id: part.id };
    offerErrors.value = {};
    suggesting.value = 'offer';
}

const blankToNull = (data: Record<string, unknown>) =>
    Object.fromEntries(
        Object.entries(data).map(([key, value]) => [
            key,
            value === '' ? null : value,
        ]),
    );

function submit(): void {
    const options = {
        preserveScroll: true,
        onSuccess: () => {
            suggesting.value = null;
            partForm.reset();
            vendorForm.reset();
            offer.value = blankOffer();
        },
    };

    if (suggesting.value === 'part') {
        partForm
            .transform(blankToNull)
            .post(storePart(props.team.slug).url, options);
    } else if (suggesting.value === 'vendor') {
        vendorForm
            .transform(blankToNull)
            .post(storeVendor(props.team.slug).url, options);
    } else if (suggesting.value === 'offer') {
        router.post(
            storeOffer(props.team.slug).url,
            blankToNull({ ...offer.value }) as Record<
                string,
                string | number | boolean | null
            >,
            {
                ...options,
                onStart: () => (offerBusy.value = true),
                onFinish: () => (offerBusy.value = false),
                onError: (errors) => (offerErrors.value = errors),
            },
        );
    }
}

const statusVariant: Record<
    string,
    'default' | 'secondary' | 'destructive' | 'outline'
> = {
    approved: 'default',
    suggested: 'secondary',
    rejected: 'destructive',
    merged: 'outline',
    archived: 'outline',
};

const selectClass =
    'h-9 w-full rounded-md border border-input bg-transparent px-3 text-sm shadow-xs';
</script>

<template>
    <Head :title="$t('Catalogue')" />

    <div class="grid gap-6 p-4 md:p-6 xl:grid-cols-[minmax(0,1fr)_20rem]">
        <div class="grid content-start gap-5">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <Heading
                    :title="$t('Catalogue')"
                    :description="
                        $t(
                            'Parts and where to buy them. Missing something? Suggest it.',
                        )
                    "
                />
                <div v-if="can.suggest" class="flex flex-wrap gap-2">
                    <Button
                        variant="outline"
                        data-test="suggest-part"
                        @click="suggesting = 'part'"
                    >
                        <Lightbulb class="size-4" />
                        {{ $t('Suggest a part') }}
                    </Button>
                    <Button variant="outline" @click="suggesting = 'vendor'">
                        {{ $t('Suggest a vendor') }}
                    </Button>
                </div>
            </div>

            <div
                class="grid gap-3 rounded-xl border bg-card p-4 sm:grid-cols-[1fr_14rem_auto] sm:items-center"
            >
                <div class="relative">
                    <Search
                        class="pointer-events-none absolute start-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground"
                    />
                    <Input
                        v-model="q"
                        type="search"
                        class="ps-9"
                        :placeholder="$t('Search by name or part number')"
                        data-test="catalogue-search"
                    />
                </div>
                <select
                    v-model="categoryId"
                    :class="selectClass"
                    :aria-label="$t('Category')"
                >
                    <option :value="null">{{ $t('All categories') }}</option>
                    <option
                        v-for="category in categories"
                        :key="category.id"
                        :value="category.id"
                    >
                        {{ category.name }}
                    </option>
                </select>
                <Label class="flex items-center gap-2 font-normal">
                    <Checkbox v-model="inStock" />
                    {{ $t('In stock') }}
                </Label>
            </div>

            <p
                v-if="parts.data.length === 0"
                class="text-sm text-muted-foreground"
            >
                {{ $t('No parts match your search.') }}
            </p>

            <ul class="grid gap-3 lg:grid-cols-2">
                <li
                    v-for="part in parts.data"
                    :key="part.id"
                    class="grid content-start gap-3 rounded-xl border bg-card p-4"
                    :data-test="`part-${part.id}`"
                >
                    <div
                        class="flex flex-wrap items-start justify-between gap-2"
                    >
                        <div class="grid">
                            <span class="font-medium">{{ part.name }}</span>
                            <span class="text-xs text-muted-foreground">
                                <span v-if="part.partNumber" dir="ltr">{{
                                    part.partNumber
                                }}</span>
                                <template
                                    v-if="part.partNumber && part.category"
                                >
                                    ·
                                </template>
                                {{ part.category }}
                            </span>
                        </div>
                        <Badge
                            v-if="part.status !== 'approved'"
                            :variant="statusVariant[part.status] ?? 'outline'"
                            >{{ $t(`catalogue.status.${part.status}`) }}</Badge
                        >
                    </div>
                    <p
                        v-if="part.description"
                        class="line-clamp-2 text-sm text-muted-foreground"
                    >
                        {{ part.description }}
                    </p>
                    <ul
                        v-if="part.offers.length"
                        class="divide-y rounded-lg border text-sm"
                    >
                        <li
                            v-for="offer in part.offers"
                            :key="offer.id"
                            class="flex items-center gap-2 px-3 py-2"
                        >
                            <span class="flex-1 truncate">{{
                                offer.vendor
                            }}</span>
                            <span
                                v-if="!offer.inStock"
                                class="text-xs text-muted-foreground"
                                >{{ $t('Out of stock') }}</span
                            >
                            <span class="font-medium tabular-nums" dir="ltr">{{
                                offer.price
                            }}</span>
                            <a
                                v-if="offer.url"
                                :href="offer.url"
                                target="_blank"
                                rel="noopener noreferrer"
                                class="text-muted-foreground hover:text-foreground"
                                :aria-label="$t('Open the shop page')"
                            >
                                <ExternalLink class="size-4" />
                            </a>
                        </li>
                    </ul>
                    <p v-else class="text-sm text-muted-foreground">
                        {{ $t('No offers yet.') }}
                    </p>
                    <div v-if="can.suggest">
                        <Button
                            size="sm"
                            variant="ghost"
                            @click="openOffer(part)"
                            >{{ $t('Suggest a price') }}</Button
                        >
                    </div>
                </li>
            </ul>

            <nav
                v-if="parts.last_page > 1"
                class="flex items-center justify-between gap-2 text-sm"
            >
                <Button
                    as-child
                    variant="outline"
                    size="sm"
                    :disabled="!parts.prev_page_url"
                >
                    <Link
                        v-if="parts.prev_page_url"
                        :href="parts.prev_page_url"
                        preserve-scroll
                        >{{ $t('Previous') }}</Link
                    >
                    <span v-else>{{ $t('Previous') }}</span>
                </Button>
                <span class="text-muted-foreground tabular-nums"
                    >{{ parts.current_page }} / {{ parts.last_page }}</span
                >
                <Button
                    as-child
                    variant="outline"
                    size="sm"
                    :disabled="!parts.next_page_url"
                >
                    <Link
                        v-if="parts.next_page_url"
                        :href="parts.next_page_url"
                        preserve-scroll
                        >{{ $t('Next') }}</Link
                    >
                    <span v-else>{{ $t('Next') }}</span>
                </Button>
            </nav>
        </div>

        <aside class="grid content-start gap-3 rounded-xl border bg-card p-5">
            <h2 class="font-semibold">{{ $t('Your suggestions') }}</h2>
            <p
                v-if="suggestions.length === 0"
                class="text-sm text-muted-foreground"
            >
                {{ $t('Nothing suggested yet.') }}
            </p>
            <ul class="grid gap-2">
                <li
                    v-for="suggestion in suggestions"
                    :key="`${suggestion.type}-${suggestion.id}`"
                    class="grid gap-1 rounded-lg border p-3 text-sm"
                >
                    <div class="flex items-start justify-between gap-2">
                        <span class="font-medium">{{ suggestion.name }}</span>
                        <Badge
                            :variant="
                                statusVariant[suggestion.status] ?? 'outline'
                            "
                            >{{
                                $t(`catalogue.status.${suggestion.status}`)
                            }}</Badge
                        >
                    </div>
                    <span class="text-xs text-muted-foreground">
                        {{ $t(`catalogue.types.${suggestion.type}`) }} ·
                        {{ formatDate(suggestion.createdAt) }}
                    </span>
                    <p
                        v-if="suggestion.note"
                        class="text-xs text-muted-foreground"
                    >
                        {{ suggestion.note }}
                    </p>
                </li>
            </ul>
        </aside>

        <Dialog
            :open="suggesting !== null"
            @update:open="(open) => !open && (suggesting = null)"
        >
            <DialogContent class="sm:max-w-lg">
                <DialogHeader>
                    <DialogTitle>
                        {{
                            suggesting === 'part'
                                ? $t('Suggest a part')
                                : suggesting === 'vendor'
                                  ? $t('Suggest a vendor')
                                  : $t('Suggest a price')
                        }}
                    </DialogTitle>
                </DialogHeader>
                <form class="grid gap-4" @submit.prevent="submit">
                    <template v-if="suggesting === 'part'">
                        <div class="grid gap-2">
                            <Label for="part-name">{{ $t('Name') }}</Label>
                            <Input
                                id="part-name"
                                v-model="partForm.name"
                                maxlength="160"
                                required
                            />
                            <InputError :message="partForm.errors.name" />
                        </div>
                        <div class="grid gap-4 sm:grid-cols-2">
                            <div class="grid gap-2">
                                <Label for="part-number">{{
                                    $t('Part number')
                                }}</Label>
                                <Input
                                    id="part-number"
                                    v-model="partForm.part_number"
                                    maxlength="80"
                                    dir="ltr"
                                />
                            </div>
                            <div class="grid gap-2">
                                <Label for="part-category">{{
                                    $t('Category')
                                }}</Label>
                                <select
                                    id="part-category"
                                    v-model="partForm.category_id"
                                    :class="selectClass"
                                >
                                    <option :value="null">—</option>
                                    <option
                                        v-for="category in categories"
                                        :key="category.id"
                                        :value="category.id"
                                    >
                                        {{ category.name }}
                                    </option>
                                </select>
                            </div>
                        </div>
                        <div class="grid gap-2">
                            <Label for="part-description">{{
                                $t('Why is it useful?')
                            }}</Label>
                            <textarea
                                id="part-description"
                                v-model="partForm.description_en"
                                rows="3"
                                maxlength="5000"
                                class="rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs"
                            />
                        </div>
                    </template>

                    <template v-if="suggesting === 'vendor'">
                        <div class="grid gap-2">
                            <Label for="vendor-name">{{ $t('Name') }}</Label>
                            <Input
                                id="vendor-name"
                                v-model="vendorForm.name"
                                maxlength="160"
                                required
                            />
                            <InputError :message="vendorForm.errors.name" />
                        </div>
                        <div class="grid gap-4 sm:grid-cols-2">
                            <div class="grid gap-2">
                                <Label for="vendor-city">{{
                                    $t('City')
                                }}</Label>
                                <Input
                                    id="vendor-city"
                                    v-model="vendorForm.city"
                                    maxlength="120"
                                />
                            </div>
                            <div class="grid gap-2">
                                <Label for="vendor-phone">{{
                                    $t('Phone')
                                }}</Label>
                                <Input
                                    id="vendor-phone"
                                    v-model="vendorForm.phone"
                                    maxlength="32"
                                    dir="ltr"
                                />
                            </div>
                        </div>
                        <div class="grid gap-2">
                            <Label for="vendor-website">{{
                                $t('Website')
                            }}</Label>
                            <Input
                                id="vendor-website"
                                v-model="vendorForm.website"
                                type="url"
                                dir="ltr"
                                placeholder="https://"
                            />
                            <InputError :message="vendorForm.errors.website" />
                        </div>
                    </template>

                    <OfferSuggestionFields
                        v-if="suggesting === 'offer'"
                        v-model="offer"
                        :vendors="vendors"
                        :currencies="currencies"
                        :errors="offerErrors"
                    />

                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            @click="suggesting = null"
                            >{{ $t('Cancel') }}</Button
                        >
                        <Button type="submit" :disabled="busy">{{
                            $t('Send suggestion')
                        }}</Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    </div>
</template>
