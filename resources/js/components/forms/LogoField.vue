<script setup lang="ts">
import { router, useForm } from '@inertiajs/vue3';
import { ImagePlus, Upload } from '@lucide/vue';
import { trans } from 'laravel-vue-i18n';
import { ref } from 'vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';

/**
 * A logo with upload, replace and remove. The server keeps one file and
 * returns a small WebP thumbnail URL.
 */
const props = defineProps<{
    logo: string | null;
    uploadUrl: string;
    removeUrl: string;
    canEdit: boolean;
    hint?: string;
}>();

const input = ref<HTMLInputElement | null>(null);
const form = useForm<{ logo: File | null }>({ logo: null });

function upload(event: Event): void {
    const file = (event.target as HTMLInputElement).files?.[0];

    if (!file) {
        return;
    }

    form.logo = file;
    form.post(props.uploadUrl, {
        forceFormData: true,
        preserveScroll: true,
        onFinish: () => {
            form.reset();

            if (input.value) {
                input.value.value = '';
            }
        },
    });
}

function remove(): void {
    if (window.confirm(trans('Remove the logo?'))) {
        router.delete(props.removeUrl, { preserveScroll: true });
    }
}
</script>

<template>
    <div class="flex flex-wrap items-center gap-4">
        <img
            v-if="logo"
            :src="logo"
            alt=""
            class="h-16 w-32 rounded-lg border bg-white object-contain p-1.5"
            data-test="logo"
        />
        <div
            v-else
            class="grid h-16 w-32 place-items-center rounded-lg border border-dashed text-muted-foreground"
        >
            <ImagePlus class="size-5" />
        </div>
        <div v-if="canEdit" class="grid gap-1.5">
            <div class="flex flex-wrap items-center gap-2">
                <input
                    ref="input"
                    type="file"
                    accept="image/png,image/jpeg,image/webp"
                    class="sr-only"
                    @change="upload"
                />
                <Button
                    type="button"
                    size="sm"
                    variant="outline"
                    :disabled="form.processing"
                    @click="input?.click()"
                >
                    <Upload class="size-4" />
                    {{ logo ? $t('Replace logo') : $t('Upload logo') }}
                </Button>
                <Button
                    v-if="logo"
                    type="button"
                    size="sm"
                    variant="ghost"
                    class="text-destructive"
                    @click="remove"
                    >{{ $t('Remove') }}</Button
                >
            </div>
            <p class="text-xs text-muted-foreground">
                {{
                    hint ??
                    $t('PNG, JPG or WebP. A transparent PNG looks best.')
                }}
            </p>
            <InputError :message="form.errors.logo" />
        </div>
    </div>
</template>
