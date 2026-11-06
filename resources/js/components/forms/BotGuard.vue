<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import InputError from '@/components/InputError.vue';

/**
 * Honeypot fields + Cloudflare Turnstile widget for forms posted to routes
 * guarded by ProtectAgainstBots. Place it inside an Inertia <Form>.
 */
const props = defineProps<{
    errors?: Record<string, string | undefined>;
}>();

type TurnstileApi = {
    render: (el: HTMLElement, options: Record<string, unknown>) => string;
    reset: (id?: string) => void;
    remove: (id?: string) => void;
};

declare global {
    interface Window {
        turnstile?: TurnstileApi;
    }
}

const SCRIPT_SRC =
    'https://challenges.cloudflare.com/turnstile/v0/api.js?render=explicit';

const page = usePage();
const honeypot = computed(
    () =>
        page.props.honeypot as
            | {
                  enabled: boolean;
                  nameFieldName: string;
                  validFromFieldName: string;
                  encryptedValidFrom: string;
              }
            | undefined,
);
const turnstile = computed(
    () =>
        page.props.turnstile as
            | { enabled: boolean; siteKey: string | null }
            | undefined,
);

const container = ref<HTMLElement | null>(null);
let widgetId: string | undefined;

function loadScript(): Promise<TurnstileApi> {
    if (window.turnstile) {
        return Promise.resolve(window.turnstile);
    }

    return new Promise((resolve, reject) => {
        const existing = document.querySelector<HTMLScriptElement>(
            `script[src="${SCRIPT_SRC}"]`,
        );
        const script = existing ?? document.createElement('script');

        script.addEventListener('load', () =>
            window.turnstile ? resolve(window.turnstile) : reject(),
        );

        if (!existing) {
            script.src = SCRIPT_SRC;
            script.async = true;
            document.head.appendChild(script);
        }
    });
}

onMounted(async () => {
    if (!turnstile.value?.enabled || !turnstile.value.siteKey) {
        return;
    }

    const api = await loadScript();

    if (container.value) {
        widgetId = api.render(container.value, {
            sitekey: turnstile.value.siteKey,
            'response-field-name': 'cf-turnstile-response',
            language: document.documentElement.lang || 'auto',
        });
    }
});

watch(
    () => props.errors,
    (errors) => {
        if (errors && Object.keys(errors).length && widgetId) {
            window.turnstile?.reset(widgetId);
        }
    },
);

onBeforeUnmount(() => {
    if (widgetId) {
        window.turnstile?.remove(widgetId);
    }
});
</script>

<template>
    <div class="grid gap-2">
        <div
            v-if="honeypot?.enabled"
            class="sr-only"
            aria-hidden="true"
            data-test="honeypot"
        >
            <input
                type="text"
                :name="honeypot.nameFieldName"
                :id="honeypot.nameFieldName"
                value=""
                tabindex="-1"
                autocomplete="off"
            />
            <input
                type="hidden"
                :name="honeypot.validFromFieldName"
                :value="honeypot.encryptedValidFrom"
            />
        </div>

        <div v-if="turnstile?.enabled" ref="container" class="min-h-[65px]" />

        <InputError :message="errors?.['cf-turnstile-response']" />
        <InputError :message="errors?.form" />
    </div>
</template>
