<script setup lang="ts" generic="T extends string | number">
import { Check, ChevronsUpDown } from '@lucide/vue';
import {
    ComboboxAnchor,
    ComboboxContent,
    ComboboxEmpty,
    ComboboxInput,
    ComboboxItem,
    ComboboxItemIndicator,
    ComboboxPortal,
    ComboboxRoot,
    ComboboxTrigger,
    ComboboxViewport,
} from 'reka-ui';
import { computed } from 'vue';

/**
 * A searchable picker for long lists (people, teams): type to filter,
 * arrow keys and Enter to choose. Replaces "search box + list box" pairs.
 */
const props = defineProps<{
    options: { value: T; label: string; hint?: string | null }[];
    placeholder?: string;
    emptyText?: string;
    id?: string;
    disabled?: boolean;
    invalid?: boolean;
}>();

const model = defineModel<T | null>({ default: null });

const selected = computed(
    () => props.options.find((option) => option.value === model.value) ?? null,
);
</script>

<template>
    <ComboboxRoot
        :model-value="model ?? undefined"
        :disabled="disabled"
        class="relative"
        @update:model-value="
            (value) => (model = (value as T | undefined) ?? null)
        "
    >
        <ComboboxAnchor
            class="flex h-9 w-full items-center gap-2 rounded-md border border-input bg-transparent px-3 text-sm shadow-xs focus-within:border-ring focus-within:ring-[3px] focus-within:ring-ring/50"
            :class="{ 'border-destructive': invalid, 'opacity-50': disabled }"
        >
            <ComboboxInput
                :id="id"
                class="h-full min-w-0 flex-1 bg-transparent outline-none placeholder:text-muted-foreground"
                :placeholder="placeholder"
                :display-value="() => selected?.label ?? ''"
                :aria-invalid="invalid ? 'true' : undefined"
            />
            <ComboboxTrigger
                class="text-muted-foreground"
                :aria-label="placeholder"
            >
                <ChevronsUpDown class="size-4" />
            </ComboboxTrigger>
        </ComboboxAnchor>
        <ComboboxPortal>
            <ComboboxContent
                position="popper"
                :side-offset="4"
                class="z-50 max-h-64 w-[var(--reka-combobox-trigger-width)] min-w-48 overflow-hidden rounded-md border bg-popover text-popover-foreground shadow-md"
            >
                <ComboboxViewport class="max-h-64 p-1">
                    <ComboboxEmpty
                        class="px-2 py-3 text-center text-sm text-muted-foreground"
                    >
                        {{ emptyText ?? $t('No matches.') }}
                    </ComboboxEmpty>
                    <ComboboxItem
                        v-for="option in options"
                        :key="option.value"
                        :value="option.value"
                        :text-value="option.label"
                        class="relative flex cursor-default items-center gap-2 rounded-sm py-1.5 ps-2 pe-8 text-sm outline-none select-none data-[disabled]:opacity-50 data-[highlighted]:bg-accent data-[highlighted]:text-accent-foreground"
                    >
                        <span class="flex-1 truncate">{{ option.label }}</span>
                        <span
                            v-if="option.hint"
                            class="text-xs text-muted-foreground"
                            >{{ option.hint }}</span
                        >
                        <ComboboxItemIndicator
                            class="absolute end-2 flex items-center"
                        >
                            <Check class="size-4" />
                        </ComboboxItemIndicator>
                    </ComboboxItem>
                </ComboboxViewport>
            </ComboboxContent>
        </ComboboxPortal>
    </ComboboxRoot>
</template>
