<script setup lang="ts">
/**
 * A photo, or the drawn placeholder when there is none (ported from the
 * old x-public.media-placeholder component).
 */
withDefaults(
    defineProps<{
        src?: string | null;
        alt?: string;
        ratio?: 'landscape' | 'portrait' | 'wide';
        variant?: 'light' | 'dark';
        eager?: boolean;
    }>(),
    { src: null, alt: '', ratio: 'landscape', variant: 'light', eager: false },
);
</script>

<template>
    <figure
        v-if="src"
        class="media-placeholder"
        :class="`media-placeholder--${ratio}`"
    >
        <img
            :src="src"
            :alt="alt"
            :loading="eager ? 'eager' : 'lazy'"
            :fetchpriority="eager ? 'high' : undefined"
            decoding="async"
        />
    </figure>
    <figure
        v-else
        class="media-placeholder media-placeholder--empty"
        :class="[
            `media-placeholder--${variant}`,
            `media-placeholder--${ratio}`,
        ]"
        aria-hidden="true"
    >
        <svg
            class="media-placeholder__drawing"
            viewBox="0 0 600 360"
            fill="none"
            focusable="false"
        >
            <g stroke="currentColor" stroke-width="1.4">
                <path
                    d="M65 270 295 145 535 270 305 340Z M65 240 295 115 535 240 305 310Z M65 240v30m470-30v30M305 310v30"
                    opacity=".55"
                />
                <path
                    d="M125 235 295 143 475 237M185 269 355 177M245 302 415 210"
                    opacity=".3"
                />
                <path
                    d="M238 222V113l61-34 66 35v109l-62 35Z M238 113l65 35 62-34M303 148v110"
                />
                <path
                    d="M265 128V74l34-19 38 20v53M265 74l36 20 36-19M301 94v51"
                />
            </g>
            <g class="signal" stroke-width="2" stroke-linecap="round">
                <path d="M157 189v-41a142 142 0 0 1 284 0v41" />
                <path d="M145 189h24m260 0h24M299 0v20" />
            </g>
        </svg>
    </figure>
</template>
