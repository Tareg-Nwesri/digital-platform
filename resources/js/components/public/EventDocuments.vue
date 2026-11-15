<script setup lang="ts">
import { Download, FileText } from '@lucide/vue';
import { formatNumber } from '@/lib/i18n';

defineProps<{
    documents: {
        id: number;
        name: string;
        extension: string;
        size: number;
        url: string;
    }[];
}>();

function fileSize(bytes: number): string {
    return bytes >= 1024 * 1024
        ? `${formatNumber(bytes / 1024 / 1024, { maximumFractionDigits: 1 })} MB`
        : `${formatNumber(Math.max(1, Math.round(bytes / 1024)))} KB`;
}
</script>

<template>
    <section
        v-if="documents.length"
        class="grid gap-3 rounded-xl border bg-card p-5"
        data-test="event-documents"
    >
        <h2 class="text-lg font-semibold">{{ $t('Downloads') }}</h2>
        <ul class="grid gap-2">
            <li v-for="document in documents" :key="document.id">
                <a
                    :href="document.url"
                    class="flex items-center gap-3 rounded-lg border p-3 hover:bg-accent"
                    download
                >
                    <FileText class="size-5 shrink-0 text-muted-foreground" />
                    <span class="grid min-w-0 flex-1">
                        <span class="truncate font-medium">{{
                            document.name
                        }}</span>
                        <span class="text-xs text-muted-foreground uppercase"
                            >{{ document.extension }} ·
                            <span dir="ltr">{{
                                fileSize(document.size)
                            }}</span></span
                        >
                    </span>
                    <Download class="size-4 shrink-0 text-muted-foreground" />
                </a>
            </li>
        </ul>
    </section>
</template>
