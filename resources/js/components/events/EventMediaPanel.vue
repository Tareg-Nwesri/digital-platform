<script setup lang="ts">
import { router, useForm } from '@inertiajs/vue3';
import { Download, FileText, ImagePlus, Trash2, Upload } from '@lucide/vue';
import { trans } from 'laravel-vue-i18n';
import { ref } from 'vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { formatNumber } from '@/lib/i18n';
import { cover as coverRoute } from '@/routes/console/events';
import { destroy as destroyCoverRoute } from '@/routes/console/events/cover';
import {
    destroy as destroyDocumentRoute,
    store as storeDocumentRoute,
} from '@/routes/console/events/documents';

export type EventDocument = {
    id: number;
    name: string;
    extension: string;
    size: number;
    url: string;
};

const props = defineProps<{
    slug: string;
    cover: string | null;
    documents: EventDocument[];
    canEdit: boolean;
}>();

const coverInput = ref<HTMLInputElement | null>(null);
const coverForm = useForm<{ cover: File | null }>({ cover: null });

function uploadCover(event: Event): void {
    const file = (event.target as HTMLInputElement).files?.[0];

    if (!file) {
        return;
    }

    coverForm.cover = file;
    coverForm.post(coverRoute(props.slug).url, {
        forceFormData: true,
        preserveScroll: true,
        onFinish: () => {
            coverForm.reset();

            if (coverInput.value) {
                coverInput.value.value = '';
            }
        },
    });
}

function removeCover(): void {
    if (window.confirm(trans('Remove the cover photo?'))) {
        router.delete(destroyCoverRoute(props.slug).url, {
            preserveScroll: true,
        });
    }
}

const documentInput = ref<HTMLInputElement | null>(null);
const documentForm = useForm<{ document: File | null; title: string }>({
    document: null,
    title: '',
});

function uploadDocument(): void {
    documentForm.post(storeDocumentRoute(props.slug).url, {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => {
            documentForm.reset();

            if (documentInput.value) {
                documentInput.value.value = '';
            }
        },
    });
}

function removeDocument(document: EventDocument): void {
    if (window.confirm(trans('Remove “:name”?', { name: document.name }))) {
        router.delete(destroyDocumentRoute([props.slug, document.id]).url, {
            preserveScroll: true,
        });
    }
}

function fileSize(bytes: number): string {
    return bytes >= 1024 * 1024
        ? `${formatNumber(bytes / 1024 / 1024, { maximumFractionDigits: 1 })} MB`
        : `${formatNumber(Math.max(1, Math.round(bytes / 1024)))} KB`;
}
</script>

<template>
    <div class="grid gap-6">
        <section class="grid gap-4 rounded-xl border bg-card p-5">
            <div>
                <h3 class="font-semibold">{{ $t('Cover photo') }}</h3>
                <p class="text-sm text-muted-foreground">
                    {{
                        $t(
                            'Shown at the top of the public event page. A wide photo works best (at least 1600 × 640).',
                        )
                    }}
                </p>
            </div>
            <img
                v-if="cover"
                :src="cover"
                alt=""
                class="aspect-[5/2] w-full rounded-lg border object-cover"
                data-test="event-cover"
            />
            <div
                v-else
                class="grid aspect-[5/2] w-full place-items-center rounded-lg border border-dashed text-muted-foreground"
            >
                <ImagePlus class="size-8" />
            </div>
            <div v-if="canEdit" class="flex flex-wrap items-center gap-2">
                <input
                    ref="coverInput"
                    type="file"
                    accept="image/jpeg,image/png,image/webp"
                    class="sr-only"
                    data-test="cover-input"
                    @change="uploadCover"
                />
                <Button
                    type="button"
                    :disabled="coverForm.processing"
                    @click="coverInput?.click()"
                >
                    <Upload class="size-4" />
                    {{ cover ? $t('Replace photo') : $t('Upload photo') }}
                </Button>
                <Button
                    v-if="cover"
                    type="button"
                    variant="ghost"
                    class="text-destructive"
                    @click="removeCover"
                    >{{ $t('Remove') }}</Button
                >
                <span
                    v-if="coverForm.progress"
                    class="text-sm text-muted-foreground tabular-nums"
                    >{{
                        formatNumber(coverForm.progress.percentage ?? 0)
                    }}%</span
                >
            </div>
            <InputError :message="coverForm.errors.cover" />
        </section>

        <section class="grid gap-4 rounded-xl border bg-card p-5">
            <div>
                <h3 class="font-semibold">{{ $t('Files to download') }}</h3>
                <p class="text-sm text-muted-foreground">
                    {{
                        $t(
                            'Requirements, rules and forms that visitors can download from the event page.',
                        )
                    }}
                </p>
            </div>

            <p
                v-if="documents.length === 0"
                class="text-sm text-muted-foreground"
            >
                {{ $t('No files yet.') }}
            </p>
            <ul v-else class="divide-y rounded-lg border">
                <li
                    v-for="document in documents"
                    :key="document.id"
                    class="flex items-center gap-3 p-3"
                    :data-test="`document-${document.id}`"
                >
                    <FileText class="size-5 shrink-0 text-muted-foreground" />
                    <div class="grid min-w-0 flex-1">
                        <span class="truncate font-medium">{{
                            document.name
                        }}</span>
                        <span class="text-xs text-muted-foreground uppercase"
                            >{{ document.extension }} ·
                            <span dir="ltr">{{
                                fileSize(document.size)
                            }}</span></span
                        >
                    </div>
                    <Button
                        as="a"
                        :href="document.url"
                        target="_blank"
                        variant="ghost"
                        size="icon"
                        :aria-label="$t('Download')"
                    >
                        <Download class="size-4" />
                    </Button>
                    <Button
                        v-if="canEdit"
                        type="button"
                        variant="ghost"
                        size="icon"
                        class="text-destructive"
                        :aria-label="$t('Remove')"
                        @click="removeDocument(document)"
                    >
                        <Trash2 class="size-4" />
                    </Button>
                </li>
            </ul>

            <form
                v-if="canEdit"
                class="grid gap-3 sm:grid-cols-[1fr_1fr_auto] sm:items-end"
                @submit.prevent="uploadDocument"
            >
                <div class="grid gap-2">
                    <Label for="document-title">{{
                        $t('Title (optional)')
                    }}</Label>
                    <Input
                        id="document-title"
                        v-model="documentForm.title"
                        maxlength="160"
                        :placeholder="$t('e.g. Competition requirements')"
                    />
                    <InputError :message="documentForm.errors.title" />
                </div>
                <div class="grid gap-2">
                    <Label for="document-file">{{ $t('File') }}</Label>
                    <input
                        id="document-file"
                        ref="documentInput"
                        type="file"
                        required
                        accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.jpg,.jpeg,.png,.zip"
                        class="text-sm file:me-3 file:rounded-md file:border file:bg-background file:px-3 file:py-1.5 file:text-sm"
                        data-test="document-input"
                        @change="
                            documentForm.document =
                                ($event.target as HTMLInputElement)
                                    .files?.[0] ?? null
                        "
                    />
                    <InputError :message="documentForm.errors.document" />
                </div>
                <Button
                    type="submit"
                    :disabled="
                        documentForm.processing || !documentForm.document
                    "
                >
                    <Upload class="size-4" />
                    {{ $t('Upload file') }}
                </Button>
            </form>
            <p v-if="canEdit" class="text-xs text-muted-foreground">
                {{
                    $t(
                        'PDF, Word, Excel, PowerPoint, images or ZIP, up to 20 MB.',
                    )
                }}
            </p>
        </section>
    </div>
</template>
