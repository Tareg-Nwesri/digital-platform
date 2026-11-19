<script setup lang="ts">
import FormOption from '@/components/forms/FormOption.vue';
import FormSelect from '@/components/forms/FormSelect.vue';
import { Head, router, usePage } from '@inertiajs/vue3';
import { computed, reactive, ref } from 'vue';
import CatalogueNav from '@/components/catalogue/CatalogueNav.vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { formatDate } from '@/lib/i18n';
import { dashboard } from '@/routes/console';
import { index as partsIndex } from '@/routes/console/catalogue/parts';
import {
    approve,
    index,
    merge,
    reject,
} from '@/routes/console/catalogue/suggestions';

type Suggestion = {
    key: string;
    id: number;
    type: 'part' | 'vendor' | 'offer';
    name: string;
    details: string[];
    team: string | null;
    suggestedBy: string | null;
    createdAt: string | null;
    alsoApproves: { type: string; name: string }[];
    fields: Record<string, string | number | boolean | null>;
};

const props = defineProps<{
    suggestions: Suggestion[];
    type: string | null;
    mergeTargets: {
        part: { id: number; name: string }[];
        vendor: { id: number; name: string }[];
    };
    can: { review: boolean };
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Organisation console', href: dashboard() },
            { title: 'Catalogue', href: partsIndex() },
            { title: 'Suggestions' },
        ],
    },
});

const page = usePage();
const pageErrors = computed(
    () => page.props.errors as Record<string, string | undefined>,
);

function filterBy(type: string | null): void {
    router.get(index().url, type ? { type } : {}, {
        preserveScroll: true,
        replace: true,
    });
}

type Action = 'approve' | 'reject' | 'merge';
const active = ref<{ suggestion: Suggestion; action: Action } | null>(null);
const edits = reactive<Record<string, string | number | boolean | null>>({});
const note = ref('');
const mergeInto = ref<number | ''>('');
const processing = ref(false);

function open(suggestion: Suggestion, action: Action): void {
    active.value = { suggestion, action };
    Object.keys(edits).forEach((key) => delete edits[key]);
    Object.assign(edits, suggestion.fields);
    note.value = '';
    mergeInto.value = '';
}

const editableFields = computed(() =>
    Object.keys(active.value?.suggestion.fields ?? {}).filter(
        (field) => !['category_id', 'in_stock', 'currency'].includes(field),
    ),
);

const fieldLabels: Record<string, string> = {
    name: 'Name',
    part_number: 'Part number',
    description_en: 'Description (English)',
    city: 'City',
    website: 'Website',
    phone: 'Phone',
    price: 'Price',
    url: 'Product link',
};

function confirm(): void {
    if (!active.value) {
        return;
    }

    const { suggestion, action } = active.value;
    const args = { type: suggestion.type, id: suggestion.id };
    const options = {
        preserveScroll: true,
        onStart: () => (processing.value = true),
        onFinish: () => (processing.value = false),
        onSuccess: () => (active.value = null),
    };

    if (action === 'approve') {
        const changed = Object.fromEntries(
            Object.entries(edits).filter(
                ([key, value]) => value !== suggestion.fields[key],
            ),
        );
        router.post(
            approve(args).url,
            Object.keys(changed).length > 0 ? { edits: changed } : {},
            options,
        );
    } else if (action === 'reject') {
        router.post(reject(args).url, { note: note.value }, options);
    } else {
        router.post(merge(args).url, { into_id: mergeInto.value }, options);
    }
}

const mergeOptions = computed(() =>
    active.value?.suggestion.type === 'vendor'
        ? props.mergeTargets.vendor
        : props.mergeTargets.part,
);

const types = [null, 'part', 'vendor', 'offer'] as const;
</script>

<template>
    <Head :title="$t('Suggestions')" />

    <div class="flex flex-col gap-6 p-4 md:p-6">
        <Heading
            eyebrow="Catalogue"
            :title="$t('Suggestions')"
            :description="
                $t(
                    'Parts, vendors and prices teams have suggested. Only the suggesting team sees them until you decide.',
                )
            "
        />

        <CatalogueNav
            current="suggestions"
            :suggestions-count="suggestions.length"
        />

        <div class="flex flex-wrap gap-2" data-tour="suggestions-filters">
            <Button
                v-for="option in types"
                :key="option ?? 'all'"
                size="sm"
                :variant="type === option ? 'default' : 'outline'"
                @click="filterBy(option)"
            >
                {{ option ? $t(`catalogue.types.${option}`) : $t('All') }}
            </Button>
        </div>

        <p
            v-if="suggestions.length === 0"
            class="text-sm text-muted-foreground"
        >
            {{ $t('Nothing waiting for review.') }}
        </p>

        <ul class="grid gap-3" data-tour="suggestions-list">
            <li
                v-for="suggestion in suggestions"
                :key="suggestion.key"
                class="flex flex-wrap items-start justify-between gap-4 rounded-xl border bg-card p-4"
                :data-test="`suggestion-${suggestion.key}`"
            >
                <div class="grid gap-1">
                    <div class="flex flex-wrap items-center gap-2">
                        <Badge variant="outline">{{
                            $t(`catalogue.types.${suggestion.type}`)
                        }}</Badge>
                        <span class="font-medium">{{ suggestion.name }}</span>
                    </div>
                    <p
                        v-if="suggestion.details.length"
                        class="text-sm text-muted-foreground"
                        dir="auto"
                    >
                        {{ suggestion.details.join(' · ') }}
                    </p>
                    <p class="text-xs text-muted-foreground">
                        {{
                            $t(':name for :team, :date', {
                                name: suggestion.suggestedBy ?? '—',
                                team: suggestion.team ?? '—',
                                date: formatDate(suggestion.createdAt),
                            })
                        }}
                    </p>
                    <p v-if="suggestion.alsoApproves.length" class="text-xs">
                        {{
                            $t('Approving also approves: :items', {
                                items: suggestion.alsoApproves
                                    .map((item) => item.name)
                                    .join(', '),
                            })
                        }}
                    </p>
                </div>
                <div v-if="can.review" class="flex flex-wrap gap-2">
                    <Button size="sm" @click="open(suggestion, 'approve')">{{
                        $t('Review & approve')
                    }}</Button>
                    <Button
                        v-if="suggestion.type !== 'offer'"
                        size="sm"
                        variant="outline"
                        @click="open(suggestion, 'merge')"
                    >
                        {{ $t('Merge') }}
                    </Button>
                    <Button
                        size="sm"
                        variant="ghost"
                        class="text-destructive"
                        @click="open(suggestion, 'reject')"
                    >
                        {{ $t('Reject') }}
                    </Button>
                </div>
            </li>
        </ul>

        <Dialog
            :open="active !== null"
            @update:open="(value) => !value && (active = null)"
        >
            <DialogContent class="max-h-[90vh] overflow-y-auto sm:max-w-lg">
                <DialogHeader>
                    <DialogTitle>
                        {{
                            active?.action === 'approve'
                                ? $t('Approve :name', {
                                      name: active?.suggestion.name ?? '',
                                  })
                                : active?.action === 'merge'
                                  ? $t('Merge :name', {
                                        name: active?.suggestion.name ?? '',
                                    })
                                  : $t('Reject :name', {
                                        name: active?.suggestion.name ?? '',
                                    })
                        }}
                    </DialogTitle>
                    <DialogDescription>
                        {{
                            active?.action === 'approve'
                                ? $t(
                                      'Correct anything before it goes into the shared catalogue.',
                                  )
                                : active?.action === 'merge'
                                  ? $t(
                                        'Its offers move to the item you choose, and the suggestion closes.',
                                    )
                                  : $t('The team sees your note.')
                        }}
                    </DialogDescription>
                </DialogHeader>

                <div v-if="active?.action === 'approve'" class="grid gap-3">
                    <div
                        v-for="field in editableFields"
                        :key="field"
                        class="grid gap-1.5"
                    >
                        <Label :for="`edit-${field}`">{{
                            $t(fieldLabels[field] ?? field)
                        }}</Label>
                        <Input
                            :id="`edit-${field}`"
                            :model-value="
                                edits[field] === null
                                    ? ''
                                    : String(edits[field])
                            "
                            :dir="
                                [
                                    'website',
                                    'url',
                                    'price',
                                    'part_number',
                                    'phone',
                                ].includes(field)
                                    ? 'ltr'
                                    : undefined
                            "
                            @update:model-value="
                                (value) =>
                                    (edits[field] =
                                        value === '' ? null : String(value))
                            "
                        />
                        <InputError :message="pageErrors[`edits.${field}`]" />
                    </div>
                    <p
                        v-if="active.suggestion.alsoApproves.length"
                        class="rounded-md bg-muted p-3 text-sm"
                    >
                        {{
                            $t('Approving also approves: :items', {
                                items: active.suggestion.alsoApproves
                                    .map((item) => item.name)
                                    .join(', '),
                            })
                        }}
                    </p>
                </div>

                <div v-else-if="active?.action === 'merge'" class="grid gap-2">
                    <Label for="merge-into">{{ $t('Merge into') }}</Label>
                    <FormSelect id="merge-into" v-model="mergeInto">
                        <FormOption value="">
                            {{ $t('Choose an approved item') }}
                        </FormOption>
                        <FormOption
                            v-for="target in mergeOptions"
                            :key="target.id"
                            :value="target.id"
                        >
                            {{ target.name }}
                        </FormOption>
                    </FormSelect>
                    <InputError :message="pageErrors.into_id" />
                </div>

                <div v-else class="grid gap-2">
                    <Label for="reject-note">{{
                        $t('Note for the team')
                    }}</Label>
                    <Input id="reject-note" v-model="note" maxlength="500" />
                    <InputError :message="pageErrors.note" />
                </div>

                <InputError :message="pageErrors.status" />

                <DialogFooter>
                    <Button variant="outline" @click="active = null">{{
                        $t('Cancel')
                    }}</Button>
                    <Button
                        :variant="
                            active?.action === 'reject'
                                ? 'destructive'
                                : 'default'
                        "
                        :disabled="
                            processing ||
                            (active?.action === 'reject' &&
                                note.trim() === '') ||
                            (active?.action === 'merge' && mergeInto === '')
                        "
                        data-test="confirm-review"
                        @click="confirm"
                    >
                        {{
                            active?.action === 'approve'
                                ? $t('Approve')
                                : active?.action === 'merge'
                                  ? $t('Merge')
                                  : $t('Reject')
                        }}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </div>
</template>
