<script setup lang="ts">
import DatePicker from '@/components/forms/DatePicker.vue';
import FormOption from '@/components/forms/FormOption.vue';
import FormSelect from '@/components/forms/FormSelect.vue';
import { useForm } from '@inertiajs/vue3';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { toZonedInput } from '@/lib/zonedDate';
import { store, update } from '@/routes/console/events';

type Option = { id: number; slug: string; name: string };

export type EventDetails = {
    slug: string;
    titleEn: string;
    titleAr: string | null;
    descriptionEn: string | null;
    descriptionAr: string | null;
    venue: string | null;
    city: string | null;
    timezone: string;
    startsAt: string;
    endsAt: string;
    registrationOpensAt: string | null;
    registrationClosesAt: string | null;
    visibility: string;
    capacity: number | null;
    department: Option;
};

const props = defineProps<{
    event?: EventDetails | null;
    departments: Option[];
    visibilities: string[];
}>();

const emit = defineEmits<{ saved: [] }>();

const timezone = props.event?.timezone ?? 'Africa/Tripoli';

const form = useForm({
    title_en: props.event?.titleEn ?? '',
    title_ar: props.event?.titleAr ?? '',
    description_en: props.event?.descriptionEn ?? '',
    description_ar: props.event?.descriptionAr ?? '',
    venue: props.event?.venue ?? '',
    city: props.event?.city ?? '',
    starts_at: toZonedInput(props.event?.startsAt, timezone),
    ends_at: toZonedInput(props.event?.endsAt, timezone),
    registration_opens_at: toZonedInput(
        props.event?.registrationOpensAt,
        timezone,
    ),
    registration_closes_at: toZonedInput(
        props.event?.registrationClosesAt,
        timezone,
    ),
    visibility: props.event?.visibility ?? props.visibilities[0] ?? 'public',
    capacity: props.event?.capacity ?? ('' as number | ''),
    department_id:
        props.event?.department.id ?? props.departments[0]?.id ?? null,
});

function submit(): void {
    form.transform((data) => ({
        ...data,
        capacity: data.capacity === '' ? null : data.capacity,
        registration_opens_at: data.registration_opens_at || null,
        registration_closes_at: data.registration_closes_at || null,
    }));

    const options = { preserveScroll: true, onSuccess: () => emit('saved') };

    if (props.event) {
        form.patch(update(props.event.slug).url, options);
    } else {
        form.post(store().url, options);
    }
}
</script>

<template>
    <form class="grid gap-4" @submit.prevent="submit">
        <div class="grid gap-4 sm:grid-cols-2">
            <div class="grid gap-2">
                <Label for="event-title-en">{{ $t('Title (English)') }}</Label>
                <Input
                    id="event-title-en"
                    v-model="form.title_en"
                    dir="ltr"
                    required
                    maxlength="160"
                />
                <InputError :message="form.errors.title_en" />
            </div>
            <div class="grid gap-2">
                <Label for="event-title-ar">{{ $t('Title (Arabic)') }}</Label>
                <Input
                    id="event-title-ar"
                    v-model="form.title_ar"
                    dir="rtl"
                    maxlength="160"
                />
                <InputError :message="form.errors.title_ar" />
            </div>
        </div>

        <div class="grid gap-4 sm:grid-cols-2">
            <div class="grid gap-2">
                <Label for="event-department">{{ $t('Organised by') }}</Label>
                <FormSelect id="event-department" v-model="form.department_id">
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
                <Label for="event-visibility">{{ $t('Visibility') }}</Label>
                <FormSelect id="event-visibility" v-model="form.visibility">
                    <FormOption
                        v-for="visibility in visibilities"
                        :key="visibility"
                        :value="visibility"
                    >
                        {{ $t(`events.visibility.${visibility}`) }}
                    </FormOption>
                </FormSelect>
                <InputError :message="form.errors.visibility" />
            </div>
        </div>

        <div class="grid gap-4 sm:grid-cols-2">
            <div class="grid gap-2">
                <Label for="event-venue">{{ $t('Venue') }}</Label>
                <Input id="event-venue" v-model="form.venue" maxlength="200" />
                <InputError :message="form.errors.venue" />
            </div>
            <div class="grid gap-2">
                <Label for="event-city">{{ $t('City') }}</Label>
                <Input id="event-city" v-model="form.city" maxlength="120" />
                <InputError :message="form.errors.city" />
            </div>
        </div>

        <p class="text-xs text-muted-foreground">
            {{ $t('Times are in :timezone.', { timezone }) }}
        </p>
        <div class="grid gap-4 sm:grid-cols-2">
            <div class="grid gap-2">
                <Label for="event-starts">{{ $t('Starts') }}</Label>
                <DatePicker
                    id="event-starts"
                    v-model="form.starts_at"
                    required
                    with-time
                />
                <InputError :message="form.errors.starts_at" />
            </div>
            <div class="grid gap-2">
                <Label for="event-ends">{{ $t('Ends') }}</Label>
                <DatePicker
                    id="event-ends"
                    v-model="form.ends_at"
                    required
                    with-time
                />
                <InputError :message="form.errors.ends_at" />
            </div>
            <div class="grid gap-2">
                <Label for="event-opens">{{ $t('Registration opens') }}</Label>
                <DatePicker
                    id="event-opens"
                    v-model="form.registration_opens_at"
                    with-time
                />
                <InputError :message="form.errors.registration_opens_at" />
            </div>
            <div class="grid gap-2">
                <Label for="event-closes">{{
                    $t('Registration closes')
                }}</Label>
                <DatePicker
                    id="event-closes"
                    v-model="form.registration_closes_at"
                    with-time
                />
                <InputError :message="form.errors.registration_closes_at" />
            </div>
        </div>

        <div class="grid gap-2 sm:max-w-48">
            <Label for="event-capacity">{{ $t('Capacity (teams)') }}</Label>
            <Input
                id="event-capacity"
                v-model.number="form.capacity"
                type="number"
                min="1"
                max="1000"
                dir="ltr"
                :placeholder="$t('Unlimited')"
            />
            <InputError :message="form.errors.capacity" />
        </div>

        <div class="grid gap-2">
            <Label for="event-description-en">{{
                $t('Description (English)')
            }}</Label>
            <textarea
                id="event-description-en"
                v-model="form.description_en"
                rows="3"
                dir="ltr"
                class="rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs"
            />
            <InputError :message="form.errors.description_en" />
        </div>
        <div class="grid gap-2">
            <Label for="event-description-ar">{{
                $t('Description (Arabic)')
            }}</Label>
            <textarea
                id="event-description-ar"
                v-model="form.description_ar"
                rows="3"
                dir="rtl"
                class="rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs"
            />
            <InputError :message="form.errors.description_ar" />
        </div>

        <div class="flex justify-end">
            <Button
                type="submit"
                :disabled="form.processing"
                data-test="save-event"
            >
                {{ event ? $t('Save event') : $t('Create event') }}
            </Button>
        </div>
    </form>
</template>
