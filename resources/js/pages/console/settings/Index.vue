<script setup lang="ts">
import FormOption from '@/components/forms/FormOption.vue';
import FormSelect from '@/components/forms/FormSelect.vue';
import { Head, useForm } from '@inertiajs/vue3';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { dashboard } from '@/routes/console';
import { index, update } from '@/routes/console/settings';

const props = defineProps<{
    settings: {
        inbox_department_id: number | null;
        events_auto_promote_waitlist: boolean;
        public_contact_email_display: string | null;
        retention_months: number;
    };
    departments: { id: number; name: string }[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Organisation console', href: dashboard() },
            { title: 'Settings', href: index() },
        ],
    },
});

const form = useForm({
    inbox_department_id: props.settings.inbox_department_id,
    events_auto_promote_waitlist: props.settings.events_auto_promote_waitlist,
    public_contact_email_display:
        props.settings.public_contact_email_display ?? '',
    retention_months: props.settings.retention_months,
});

function save(): void {
    form.transform((data) => ({
        ...data,
        public_contact_email_display: data.public_contact_email_display || null,
    })).put(update().url, { preserveScroll: true });
}
</script>

<template>
    <Head :title="$t('Settings')" />

    <div class="flex max-w-2xl flex-col gap-6 p-4 md:p-6">
        <Heading
            :title="$t('Platform settings')"
            :description="
                $t(
                    'Settings that apply to the whole platform. Only the super admin can change them.',
                )
            "
        />

        <form class="grid gap-6" @submit.prevent="save">
            <section
                class="grid gap-4 rounded-xl border bg-card p-5"
                data-tour="settings-inbox"
            >
                <h3 class="font-semibold">{{ $t('Inbox') }}</h3>
                <div class="grid gap-2">
                    <Label for="inbox-department">{{
                        $t('Department that receives contact messages')
                    }}</Label>
                    <FormSelect
                        id="inbox-department"
                        v-model="form.inbox_department_id"
                    >
                        <FormOption :value="null">
                            {{ $t('The super admin') }}
                        </FormOption>
                        <FormOption
                            v-for="department in departments"
                            :key="department.id"
                            :value="department.id"
                        >
                            {{ department.name }}
                        </FormOption>
                    </FormSelect>
                    <InputError :message="form.errors.inbox_department_id" />
                </div>
                <div class="grid gap-2">
                    <Label for="contact-email">{{
                        $t('Contact email shown on the public site')
                    }}</Label>
                    <Input
                        id="contact-email"
                        v-model="form.public_contact_email_display"
                        type="email"
                        dir="ltr"
                    />
                    <InputError
                        :message="form.errors.public_contact_email_display"
                    />
                </div>
            </section>

            <section
                class="grid gap-4 rounded-xl border bg-card p-5"
                data-tour="settings-events"
            >
                <h3 class="font-semibold">{{ $t('Events') }}</h3>
                <label class="flex items-start gap-3 text-sm">
                    <Checkbox
                        :model-value="form.events_auto_promote_waitlist"
                        @update:model-value="
                            (value) =>
                                (form.events_auto_promote_waitlist =
                                    value === true)
                        "
                    />
                    <span>
                        {{
                            $t(
                                'Move the next waitlisted team up automatically when an approved team withdraws',
                            )
                        }}
                    </span>
                </label>
            </section>

            <section
                class="grid gap-4 rounded-xl border bg-card p-5"
                data-tour="settings-retention"
            >
                <h3 class="font-semibold">{{ $t('Data retention') }}</h3>
                <div class="grid gap-2 sm:max-w-48">
                    <Label for="retention-months">{{
                        $t('Keep records for (months)')
                    }}</Label>
                    <Input
                        id="retention-months"
                        v-model.number="form.retention_months"
                        type="number"
                        min="6"
                        max="120"
                        dir="ltr"
                    />
                    <InputError :message="form.errors.retention_months" />
                </div>
            </section>

            <div class="flex justify-end">
                <Button
                    type="submit"
                    :disabled="form.processing"
                    data-test="save-settings"
                    >{{ $t('Save settings') }}</Button
                >
            </div>
        </form>
    </div>
</template>
