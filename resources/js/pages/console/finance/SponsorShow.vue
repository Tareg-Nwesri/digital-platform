<script setup lang="ts">
import FormOption from '@/components/forms/FormOption.vue';
import FormSelect from '@/components/forms/FormSelect.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { Plus, Trash2 } from '@lucide/vue';
import { trans } from 'laravel-vue-i18n';
import { ref } from 'vue';
import FinanceNav from '@/components/finance/FinanceNav.vue';
import LogoField from '@/components/forms/LogoField.vue';
import { logo as logoRoute } from '@/routes/console/finance/sponsors';
import { destroy as destroyLogo } from '@/routes/console/finance/sponsors/logo';
import Money from '@/components/finance/Money.vue';
import SponsorshipForm from '@/components/finance/SponsorshipForm.vue';
import type { SponsorshipOptions } from '@/components/finance/SponsorshipForm.vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { dashboard as consoleDashboard } from '@/routes/console';
import { dashboard } from '@/routes/console/finance';
import { update } from '@/routes/console/finance/sponsors';
import {
    destroy as destroyContact,
    store as storeContact,
} from '@/routes/console/finance/sponsors/contacts';
import { show as showSponsorship } from '@/routes/console/finance/sponsorships';

type MoneyValue = { amountMinor: number; currency: string; formatted: string };

const props = defineProps<{
    sponsor: {
        id: number;
        name: string;
        nameEn: string;
        nameAr: string | null;
        logo: string | null;
        type: string;
        website: string | null;
        notes: string | null;
        publicConsent: boolean;
        isActive: boolean;
    };
    contacts: {
        id: number;
        name: string;
        role: string | null;
        phone: string | null;
        email: string | null;
    }[];
    sponsorships: {
        id: number;
        title: string;
        status: string;
        pledged: MoneyValue;
        received: MoneyValue;
    }[];
    options: SponsorshipOptions;
    can: { edit: boolean };
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Organisation console', href: consoleDashboard() },
            { title: 'Finance', href: dashboard() },
        ],
    },
});

const details = useForm({
    name_ar: props.sponsor.nameAr ?? '',
    name_en: props.sponsor.nameEn,
    type: props.sponsor.type,
    website: props.sponsor.website ?? '',
    notes: props.sponsor.notes ?? '',
    public_consent: props.sponsor.publicConsent,
    is_active: props.sponsor.isActive,
});

function saveDetails(): void {
    details
        .transform((data) => ({
            ...data,
            name_ar: data.name_ar || null,
            website: data.website || null,
            notes: data.notes || null,
        }))
        .patch(update(props.sponsor.id).url, { preserveScroll: true });
}

const contact = useForm({ name: '', role: '', phone: '', email: '' });

function addContact(): void {
    contact
        .transform((data) =>
            Object.fromEntries(
                Object.entries(data).map(([key, value]) => [
                    key,
                    value || null,
                ]),
            ),
        )
        .post(storeContact(props.sponsor.id).url, {
            preserveScroll: true,
            onSuccess: () => contact.reset(),
        });
}

function removeContact(id: number): void {
    if (window.confirm(trans('Remove this contact?'))) {
        router.delete(destroyContact([props.sponsor.id, id]).url, {
            preserveScroll: true,
        });
    }
}

const creating = ref(false);
</script>

<template>
    <Head :title="sponsor.name" />

    <div class="flex flex-col gap-6 p-4 md:p-6">
        <div class="flex flex-wrap items-center gap-3">
            <Heading
                :title="sponsor.name"
                :description="$t(`finance.sponsor_type.${sponsor.type}`)"
            />
            <Badge v-if="!sponsor.isActive" variant="outline">{{
                $t('Inactive')
            }}</Badge>
        </div>

        <FinanceNav current="sponsors" />

        <section class="rounded-xl border bg-card p-5">
            <h3 class="mb-3 font-semibold">{{ $t('Logo') }}</h3>
            <LogoField
                :logo="sponsor.logo"
                :upload-url="logoRoute(sponsor.id).url"
                :remove-url="destroyLogo(sponsor.id).url"
                :can-edit="can.edit"
                :hint="
                    sponsor.publicConsent
                        ? $t(
                              'Shown on the public home page while the sponsor is active.',
                          )
                        : $t(
                              'Not shown publicly: the sponsor has not agreed to be named.',
                          )
                "
            />
        </section>

        <div class="grid gap-6 xl:grid-cols-2">
            <section
                class="grid content-start gap-4 rounded-xl border bg-card p-5"
            >
                <div class="flex items-center justify-between gap-2">
                    <h3 class="font-semibold">{{ $t('Sponsorships') }}</h3>
                    <Button
                        v-if="can.edit"
                        size="sm"
                        data-test="new-sponsorship"
                        @click="creating = true"
                    >
                        <Plus class="size-4" />
                        {{ $t('New sponsorship') }}
                    </Button>
                </div>
                <p
                    v-if="sponsorships.length === 0"
                    class="text-sm text-muted-foreground"
                >
                    {{ $t('No sponsorships yet.') }}
                </p>
                <ul class="grid gap-2">
                    <li v-for="item in sponsorships" :key="item.id">
                        <Link
                            :href="showSponsorship(item.id)"
                            class="flex flex-wrap items-center justify-between gap-2 rounded-lg border p-3 hover:bg-accent"
                        >
                            <span class="font-medium">{{ item.title }}</span>
                            <span class="text-sm">
                                <Money :value="item.received" />
                                <span class="text-muted-foreground">/</span>
                                <Money :value="item.pledged" />
                            </span>
                        </Link>
                    </li>
                </ul>

                <h3 class="mt-2 font-semibold">{{ $t('Contacts') }}</h3>
                <ul class="grid gap-2 text-sm">
                    <li
                        v-for="person in contacts"
                        :key="person.id"
                        class="flex items-start justify-between gap-2 rounded-lg border p-3"
                    >
                        <div>
                            <p class="font-medium">
                                {{ person.name
                                }}<span
                                    v-if="person.role"
                                    class="text-muted-foreground"
                                >
                                    · {{ person.role }}</span
                                >
                            </p>
                            <p class="text-muted-foreground" dir="ltr">
                                {{
                                    [person.phone, person.email]
                                        .filter(Boolean)
                                        .join(' · ')
                                }}
                            </p>
                        </div>
                        <Button
                            v-if="can.edit"
                            variant="ghost"
                            size="icon"
                            :aria-label="$t('Remove')"
                            @click="removeContact(person.id)"
                        >
                            <Trash2 class="size-4" />
                        </Button>
                    </li>
                </ul>
                <form
                    v-if="can.edit"
                    class="grid gap-2 sm:grid-cols-2"
                    @submit.prevent="addContact"
                >
                    <Input
                        v-model="contact.name"
                        :placeholder="$t('Name')"
                        :aria-label="$t('Name')"
                        required
                    />
                    <Input
                        v-model="contact.role"
                        :placeholder="$t('Role')"
                        :aria-label="$t('Role')"
                    />
                    <Input
                        v-model="contact.phone"
                        :placeholder="$t('Phone')"
                        :aria-label="$t('Phone')"
                        dir="ltr"
                    />
                    <Input
                        v-model="contact.email"
                        type="email"
                        :placeholder="$t('Email')"
                        :aria-label="$t('Email')"
                        dir="ltr"
                    />
                    <InputError
                        :message="contact.errors.name ?? contact.errors.email"
                        class="sm:col-span-2"
                    />
                    <Button
                        type="submit"
                        variant="outline"
                        size="sm"
                        class="justify-self-start"
                        :disabled="contact.processing"
                        >{{ $t('Add contact') }}</Button
                    >
                </form>
            </section>

            <section class="rounded-xl border bg-card p-5">
                <h3 class="mb-4 font-semibold">{{ $t('Details') }}</h3>
                <form class="grid gap-4" @submit.prevent="saveDetails">
                    <div class="grid gap-2">
                        <Label for="sponsor-name-ar">{{
                            $t('Name (Arabic)')
                        }}</Label>
                        <Input
                            id="sponsor-name-ar"
                            v-model="details.name_ar"
                            dir="rtl"
                            :disabled="!can.edit"
                        />
                    </div>
                    <div class="grid gap-2">
                        <Label for="sponsor-name-en">{{
                            $t('Name (English)')
                        }}</Label>
                        <Input
                            id="sponsor-name-en"
                            v-model="details.name_en"
                            dir="ltr"
                            required
                            :disabled="!can.edit"
                        />
                        <InputError :message="details.errors.name_en" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="sponsor-type">{{ $t('Type') }}</Label>
                        <FormSelect
                            id="sponsor-type"
                            v-model="details.type"
                            :disabled="!can.edit"
                        >
                            <FormOption
                                v-for="type in [
                                    'company',
                                    'organisation',
                                    'individual',
                                    'government',
                                ]"
                                :key="type"
                                :value="type"
                            >
                                {{ $t(`finance.sponsor_type.${type}`) }}
                            </FormOption>
                        </FormSelect>
                    </div>
                    <div class="grid gap-2">
                        <Label for="sponsor-website">{{ $t('Website') }}</Label>
                        <Input
                            id="sponsor-website"
                            v-model="details.website"
                            type="url"
                            dir="ltr"
                            :disabled="!can.edit"
                        />
                        <InputError :message="details.errors.website" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="sponsor-notes">{{ $t('Notes') }}</Label>
                        <textarea
                            id="sponsor-notes"
                            v-model="details.notes"
                            rows="3"
                            class="rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs"
                            :disabled="!can.edit"
                        />
                    </div>
                    <label class="flex items-center gap-3 text-sm">
                        <Checkbox
                            :model-value="details.public_consent"
                            :disabled="!can.edit"
                            @update:model-value="
                                (value) =>
                                    (details.public_consent = value === true)
                            "
                        />
                        {{
                            $t('The sponsor agrees to be named on the website')
                        }}
                    </label>
                    <label class="flex items-center gap-3 text-sm">
                        <Checkbox
                            :model-value="details.is_active"
                            :disabled="!can.edit"
                            @update:model-value="
                                (value) => (details.is_active = value === true)
                            "
                        />
                        {{ $t('Active sponsor') }}
                    </label>
                    <div v-if="can.edit" class="flex justify-end">
                        <Button type="submit" :disabled="details.processing">{{
                            $t('Save sponsor')
                        }}</Button>
                    </div>
                </form>
            </section>
        </div>

        <Dialog v-model:open="creating">
            <DialogContent class="max-h-[90vh] overflow-y-auto sm:max-w-2xl">
                <DialogHeader>
                    <DialogTitle>{{ $t('New sponsorship') }}</DialogTitle>
                </DialogHeader>
                <SponsorshipForm
                    :sponsor-id="sponsor.id"
                    :options="options"
                    @saved="creating = false"
                />
            </DialogContent>
        </Dialog>
    </div>
</template>
