<script setup lang="ts">
import FormOption from '@/components/forms/FormOption.vue';
import FormSelect from '@/components/forms/FormSelect.vue';
import InputError from '@/components/InputError.vue';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

export type OfferDraft = {
    part_id: number | null;
    vendor_id: number | null;
    price: string;
    currency: string;
    url: string;
    in_stock: boolean;
};

defineProps<{
    vendors: { id: number; name: string }[];
    currencies: string[];
    errors: Record<string, string | undefined>;
}>();

const draft = defineModel<OfferDraft>({ required: true });

const urlPlaceholder = 'https:' + '//';
</script>

<template>
    <div class="grid gap-4">
        <div class="grid gap-2">
            <Label for="offer-vendor">{{ $t('Vendor') }}</Label>
            <FormSelect id="offer-vendor" v-model="draft.vendor_id" required>
                <FormOption :value="null" disabled>
                    {{ $t('Choose a vendor') }}
                </FormOption>
                <FormOption
                    v-for="vendor in vendors"
                    :key="vendor.id"
                    :value="vendor.id"
                >
                    {{ vendor.name }}
                </FormOption>
            </FormSelect>
            <InputError :message="errors.vendor_id ?? errors.part_id" />
        </div>
        <div class="grid grid-cols-[1fr_7rem] gap-4">
            <div class="grid gap-2">
                <Label for="offer-price">{{ $t('Price') }}</Label>
                <Input
                    id="offer-price"
                    v-model="draft.price"
                    inputmode="decimal"
                    required
                    dir="ltr"
                />
                <InputError :message="errors.price" />
            </div>
            <div class="grid gap-2">
                <Label for="offer-currency">{{ $t('Currency') }}</Label>
                <FormSelect id="offer-currency" v-model="draft.currency">
                    <FormOption
                        v-for="currency in currencies"
                        :key="currency"
                        :value="currency"
                    >
                        {{ currency }}
                    </FormOption>
                </FormSelect>
            </div>
        </div>
        <div class="grid gap-2">
            <Label for="offer-url">{{ $t('Link to the product') }}</Label>
            <Input
                id="offer-url"
                v-model="draft.url"
                type="url"
                dir="ltr"
                :placeholder="urlPlaceholder"
            />
            <InputError :message="errors.url" />
        </div>
        <Label class="flex items-center gap-2 font-normal">
            <Checkbox v-model="draft.in_stock" />
            {{ $t('In stock') }}
        </Label>
    </div>
</template>
