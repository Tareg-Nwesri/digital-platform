<script setup lang="ts">
import { computed } from 'vue';
import { SelectItem } from '@/components/ui/select';
import type { FormSelectValue } from './formSelectValue';
import { toInner } from './formSelectValue';

/**
 * One choice of a <FormSelect>, used like a native <option>.
 */
const props = defineProps<{
    value?: FormSelectValue;
    disabled?: boolean;
}>();

const inner = computed(() => toInner(props.value));
</script>

<template>
    <SelectItem :value="inner" :disabled="disabled">
        <slot />
    </SelectItem>
</template>
