<script setup lang="ts">
import { router, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { formatZoned } from '@/lib/zonedDate';
import { results as resultsRoute } from '@/routes/console/events';
import { publish } from '@/routes/console/events/results';
import type { Registration } from '@/components/events/RegistrationsPanel.vue';

type Result = {
    placement: number | null;
    score: string | null;
    awardEn: string | null;
    awardAr: string | null;
    notes: string | null;
} | null;

const props = defineProps<{
    eventSlug: string;
    registrations: (Registration & { result: Result })[];
    finished: boolean;
    publishedAt: string | null;
    timezone: string;
    can: { edit: boolean; review: boolean };
}>();

const approved = computed(() =>
    props.registrations.filter(
        (registration) => registration.status === 'approved',
    ),
);

const form = useForm({
    results: approved.value.map((registration) => ({
        registration_id: registration.id,
        placement: registration.result?.placement ?? ('' as number | ''),
        score: registration.result?.score ?? '',
        award_en: registration.result?.awardEn ?? '',
        award_ar: registration.result?.awardAr ?? '',
    })),
});

const errors = computed(
    () => form.errors as Record<string, string | undefined>,
);

function save(): void {
    form.transform((data) => ({
        results: data.results.map((row) => ({
            ...row,
            placement: row.placement === '' ? null : row.placement,
            score: row.score === '' ? null : row.score,
            award_en: row.award_en || null,
            award_ar: row.award_ar || null,
        })),
    })).put(resultsRoute(props.eventSlug).url, { preserveScroll: true });
}

function publishResults(): void {
    router.post(publish(props.eventSlug).url, {}, { preserveScroll: true });
}

const editable = computed(() => props.can.edit && props.finished);
</script>

<template>
    <div class="grid gap-4">
        <p v-if="!finished" class="text-sm text-muted-foreground">
            {{ $t('Results can be recorded once the event has finished.') }}
        </p>
        <p v-if="publishedAt" class="text-sm">
            {{
                $t('Published on :date.', {
                    date: formatZoned(publishedAt, timezone),
                })
            }}
        </p>

        <div class="overflow-x-auto rounded-xl border bg-card">
            <table class="w-full text-sm">
                <thead class="border-b text-xs text-muted-foreground">
                    <tr>
                        <th class="px-4 py-2 text-start font-medium">
                            {{ $t('Team') }}
                        </th>
                        <th class="px-4 py-2 text-start font-medium">
                            {{ $t('Category') }}
                        </th>
                        <th class="px-4 py-2 text-start font-medium">
                            {{ $t('Place') }}
                        </th>
                        <th class="px-4 py-2 text-start font-medium">
                            {{ $t('Score') }}
                        </th>
                        <th class="px-4 py-2 text-start font-medium">
                            {{ $t('Award (English)') }}
                        </th>
                        <th class="px-4 py-2 text-start font-medium">
                            {{ $t('Award (Arabic)') }}
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-if="approved.length === 0">
                        <td
                            colspan="6"
                            class="px-4 py-8 text-center text-muted-foreground"
                        >
                            {{ $t('No approved entries.') }}
                        </td>
                    </tr>
                    <tr
                        v-for="(registration, index) in approved"
                        :key="registration.id"
                        class="border-b last:border-0"
                    >
                        <td class="px-4 py-2 font-medium">
                            {{ registration.team.name }}
                        </td>
                        <td class="px-4 py-2">
                            {{ registration.category.name }}
                        </td>
                        <td class="px-4 py-2">
                            <Input
                                v-model.number="form.results[index].placement"
                                type="number"
                                min="1"
                                class="w-20 tabular-nums"
                                dir="ltr"
                                :disabled="!editable"
                                :aria-label="$t('Place')"
                            />
                            <InputError
                                :message="errors[`results.${index}.placement`]"
                            />
                        </td>
                        <td class="px-4 py-2">
                            <Input
                                v-model="form.results[index].score"
                                inputmode="decimal"
                                class="w-24 tabular-nums"
                                dir="ltr"
                                :disabled="!editable"
                                :aria-label="$t('Score')"
                            />
                            <InputError
                                :message="errors[`results.${index}.score`]"
                            />
                        </td>
                        <td class="px-4 py-2">
                            <Input
                                v-model="form.results[index].award_en"
                                maxlength="120"
                                dir="ltr"
                                :disabled="!editable"
                                :aria-label="$t('Award (English)')"
                            />
                        </td>
                        <td class="px-4 py-2">
                            <Input
                                v-model="form.results[index].award_ar"
                                maxlength="120"
                                dir="rtl"
                                :disabled="!editable"
                                :aria-label="$t('Award (Arabic)')"
                            />
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <InputError :message="errors.results" />

        <div v-if="finished" class="flex flex-wrap justify-end gap-2">
            <Button
                v-if="can.review && !publishedAt"
                variant="outline"
                data-test="publish-results"
                @click="publishResults"
            >
                {{ $t('Publish results') }}
            </Button>
            <Button
                v-if="editable && approved.length > 0"
                :disabled="form.processing"
                data-test="save-results"
                @click="save"
            >
                {{ $t('Save results') }}
            </Button>
        </div>
    </div>
</template>
