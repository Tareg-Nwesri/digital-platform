<script setup lang="ts">
import type { AcceptableValue } from 'reka-ui';
import type { HTMLAttributes } from 'vue';
import { computed } from 'vue';
import {
    Select,
    SelectContent,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { cn } from '@/lib/utils';
import type { FormSelectValue } from './formSelectValue';
import { toInner, toOuter } from './formSelectValue';

/**
 * The app's select box: the UI kit's Select with the API of a native
 * <select> (v-model, id, name, required, disabled) and <FormOption>
 * children. Unlike the kit's Select it accepts undefined, null and '' as
 * values, so "All", "None" and "No project" options work.
 *
 * It sizes to its content in rows (filters) and stretches in form grids.
 * With `default-value` instead of v-model it is uncontrolled, for native
 * forms that read it through `name`.
 */
defineOptions({ inheritAttrs: false });

const props = defineProps<{
    modelValue?: FormSelectValue;
    defaultValue?: FormSelectValue;
    id?: string;
    name?: string;
    required?: boolean;
    disabled?: boolean;
    placeholder?: string;
    dir?: 'ltr' | 'rtl';
    class?: HTMLAttributes['class'];
}>();

const emit = defineEmits<{
    'update:modelValue': [value: FormSelectValue];
    change: [value: FormSelectValue];
}>();

const uncontrolled = computed(() => props.defaultValue !== undefined);

function update(value: AcceptableValue): void {
    const outer = toOuter(value);
    emit('update:modelValue', outer);
    emit('change', outer);
}
</script>

<template>
    <Select
        :model-value="uncontrolled ? undefined : toInner(modelValue)"
        :default-value="uncontrolled ? toInner(defaultValue) : undefined"
        :name="name"
        :required="required"
        :disabled="disabled"
        :dir="dir"
        @update:model-value="update"
    >
        <SelectTrigger
            :id="id"
            v-bind="$attrs"
            :class="cn('h-9 w-auto min-w-36 bg-transparent', props.class)"
        >
            <SelectValue :placeholder="placeholder" />
        </SelectTrigger>
        <SelectContent>
            <slot />
        </SelectContent>
    </Select>
</template>
