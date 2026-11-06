<script setup lang="ts">
import type { HTMLAttributes } from 'vue';

defineOptions({
    inheritAttrs: false,
});

type Props = {
    className?: HTMLAttributes['class'];
};

defineProps<Props>();
</script>

<template>
    <img
        src="/assets/img/brand/logo.png"
        alt=""
        :class="[className, $attrs.class, 'object-contain']"
        width="64"
        height="64"
        decoding="async"
    />
</template>
