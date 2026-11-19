<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { ImagePlus, Upload } from '@lucide/vue';
import { trans } from 'laravel-vue-i18n';
import { ref } from 'vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { logo as logoRoute, update } from '@/routes/workspace/settings';
import { destroy as destroyLogo } from '@/routes/workspace/settings/logo';

const props = defineProps<{
    team: {
        slug: string;
        name: string;
        logo: string | null;
        location: string | null;
        focusEn: string | null;
        focusAr: string | null;
        descriptionEn: string | null;
        descriptionAr: string | null;
        officialUrl: string | null;
        isPublic: boolean;
    };
    can: { publish: boolean };
}>();

const form = useForm({
    location: props.team.location ?? '',
    focus_ar: props.team.focusAr ?? '',
    focus_en: props.team.focusEn ?? '',
    description_ar: props.team.descriptionAr ?? '',
    description_en: props.team.descriptionEn ?? '',
    official_url: props.team.officialUrl ?? '',
    is_public: props.team.isPublic,
});

function save(): void {
    form.transform((data) =>
        Object.fromEntries(
            Object.entries(data).map(([key, value]) => [
                key,
                value === '' ? null : value,
            ]),
        ),
    ).patch(update(props.team.slug).url, { preserveScroll: true });
}

const logoInput = ref<HTMLInputElement | null>(null);
const logoForm = useForm<{ logo: File | null }>({ logo: null });

function uploadLogo(event: Event): void {
    const file = (event.target as HTMLInputElement).files?.[0];

    if (!file) {
        return;
    }

    logoForm.logo = file;
    logoForm.post(logoRoute(props.team.slug).url, {
        forceFormData: true,
        preserveScroll: true,
        onFinish: () => {
            logoForm.reset();

            if (logoInput.value) {
                logoInput.value.value = '';
            }
        },
    });
}

function removeLogo(): void {
    if (window.confirm(trans('Remove the logo?'))) {
        router.delete(destroyLogo(props.team.slug).url, {
            preserveScroll: true,
        });
    }
}

const urlPlaceholder = 'https:' + '//';

const textareaClass =
    'rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs';
</script>

<template>
    <Head :title="$t('Team settings')" />

    <div class="flex max-w-3xl flex-col gap-6 p-4 md:p-6">
        <Heading
            :title="$t('Team settings')"
            :description="
                $t(
                    'How your team appears on the website. The name and tier are set by the organisers.',
                )
            "
        />

        <section
            class="flex flex-wrap items-center gap-4 rounded-xl border bg-card p-5"
            data-tour="settings-logo"
        >
            <img
                v-if="team.logo"
                :src="team.logo"
                alt=""
                class="size-20 rounded-lg border bg-white object-contain p-1"
                data-test="team-logo"
            />
            <div
                v-else
                class="grid size-20 place-items-center rounded-lg border border-dashed text-muted-foreground"
            >
                <ImagePlus class="size-6" />
            </div>
            <div class="grid gap-2">
                <span class="font-semibold">{{ team.name }}</span>
                <div class="flex flex-wrap items-center gap-2">
                    <input
                        ref="logoInput"
                        type="file"
                        accept="image/jpeg,image/png,image/webp"
                        class="sr-only"
                        @change="uploadLogo"
                    />
                    <Button
                        type="button"
                        size="sm"
                        variant="outline"
                        :disabled="logoForm.processing"
                        @click="logoInput?.click()"
                    >
                        <Upload class="size-4" />
                        {{ team.logo ? $t('Replace logo') : $t('Upload logo') }}
                    </Button>
                    <Button
                        v-if="team.logo"
                        type="button"
                        size="sm"
                        variant="ghost"
                        class="text-destructive"
                        @click="removeLogo"
                        >{{ $t('Remove') }}</Button
                    >
                </div>
                <InputError :message="logoForm.errors.logo" />
            </div>
        </section>

        <form class="grid gap-5" @submit.prevent="save">
            <section
                class="grid gap-4 rounded-xl border bg-card p-5"
                data-tour="settings-basics"
            >
                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="grid gap-2">
                        <Label for="location">{{ $t('City') }}</Label>
                        <Input
                            id="location"
                            v-model="form.location"
                            maxlength="120"
                        />
                        <InputError :message="form.errors.location" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="official-url">{{ $t('Website') }}</Label>
                        <Input
                            id="official-url"
                            v-model="form.official_url"
                            type="url"
                            dir="ltr"
                            :placeholder="urlPlaceholder"
                        />
                        <InputError :message="form.errors.official_url" />
                    </div>
                </div>
            </section>

            <section
                class="grid gap-4 rounded-xl border bg-card p-5"
                lang="ar"
                dir="rtl"
                data-tour="settings-texts"
            >
                <h3 class="font-semibold">{{ $t('Arabic') }}</h3>
                <div class="grid gap-2">
                    <Label for="focus-ar">{{
                        $t('What the team works on')
                    }}</Label>
                    <Input
                        id="focus-ar"
                        v-model="form.focus_ar"
                        maxlength="255"
                    />
                </div>
                <div class="grid gap-2">
                    <Label for="description-ar">{{
                        $t('About the team')
                    }}</Label>
                    <textarea
                        id="description-ar"
                        v-model="form.description_ar"
                        rows="4"
                        maxlength="5000"
                        :class="textareaClass"
                    />
                </div>
            </section>

            <section
                class="grid gap-4 rounded-xl border bg-card p-5"
                lang="en"
                dir="ltr"
            >
                <h3 class="font-semibold">{{ $t('English') }}</h3>
                <div class="grid gap-2">
                    <Label for="focus-en">{{
                        $t('What the team works on')
                    }}</Label>
                    <Input
                        id="focus-en"
                        v-model="form.focus_en"
                        maxlength="255"
                    />
                </div>
                <div class="grid gap-2">
                    <Label for="description-en">{{
                        $t('About the team')
                    }}</Label>
                    <textarea
                        id="description-en"
                        v-model="form.description_en"
                        rows="4"
                        maxlength="5000"
                        :class="textareaClass"
                    />
                </div>
            </section>

            <section
                class="grid gap-2 rounded-xl border bg-card p-5"
                data-tour="settings-public"
            >
                <Label class="flex items-center gap-3 font-normal">
                    <Checkbox
                        :model-value="form.is_public"
                        :disabled="!can.publish"
                        @update:model-value="
                            (value) => (form.is_public = value === true)
                        "
                    />
                    {{ $t('Show the team profile on the public website') }}
                </Label>
                <p v-if="!can.publish" class="text-sm text-muted-foreground">
                    {{
                        $t(
                            'Your team’s tier does not include a public profile.',
                        )
                    }}
                </p>
                <InputError :message="form.errors.is_public" />
            </section>

            <div class="flex justify-end">
                <Button
                    type="submit"
                    :disabled="form.processing"
                    data-test="save-settings"
                    >{{ $t('Save') }}</Button
                >
            </div>
        </form>
    </div>
</template>
