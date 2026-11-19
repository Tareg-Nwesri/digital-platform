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
    /** Public forms only; the server checks Turnstile on those routes alone. */
    withTurnstile?: boolean;
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

/** Must match the script tag the root template adds to protected pages. */
const SCRIPT_SRC =
    'https://challenges.cloudflare.com/turnstile/v0/api.js?render=explicit';

/** Turnstile retries a failed check after 8 s by default; 2 s keeps a hiccup short. */
const RETRY_INTERVAL_MS = 2000;

/** The longest a submit waits for the check; after that the server answers. */
const MAX_WAIT_MS = 10000;

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
const turnstile = computed(() =>
    props.withTurnstile
        ? (page.props.turnstile as
              | { enabled: boolean; siteKey: string | null }
              | undefined)
        : undefined,
);

const container = ref<HTMLElement | null>(null);
const waiting = ref(false);
let widgetId: string | undefined;
let form: HTMLFormElement | null = null;
let tokenReady = false;
let heldSubmitter: HTMLElement | null | undefined;
let letSubmitThrough = false;
let waitTimer: ReturnType<typeof setTimeout> | undefined;

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
        script.addEventListener('error', () => reject());

        if (!existing) {
            script.src = SCRIPT_SRC;
            script.async = true;
            document.head.appendChild(script);
        }
    });
}

/** Sends the submit that was held while the check was still running. */
function sendHeldSubmit(): void {
    if (heldSubmitter === undefined || !form) {
        return;
    }

    const submitter = heldSubmitter;
    heldSubmitter = undefined;
    waiting.value = false;
    clearTimeout(waitTimer);

    letSubmitThrough = true;
    form.requestSubmit(submitter);
    letSubmitThrough = false;
}

/**
 * A submit before the token exists would be rejected and restart the check,
 * so it waits (capture phase, ahead of Inertia's own listener) and goes out
 * the moment the token arrives.
 */
function holdSubmitUntilVerified(event: SubmitEvent): void {
    if (event.target !== form || tokenReady || letSubmitThrough) {
        return;
    }

    event.preventDefault();
    event.stopImmediatePropagation();

    if (heldSubmitter === undefined) {
        heldSubmitter = event.submitter;
        waiting.value = true;
        waitTimer = setTimeout(sendHeldSubmit, MAX_WAIT_MS);
    }
}

function stopHolding(): void {
    document.removeEventListener('submit', holdSubmitUntilVerified, true);
}

onMounted(async () => {
    if (!turnstile.value?.enabled || !turnstile.value.siteKey) {
        return;
    }

    form = container.value?.closest('form') ?? null;
    document.addEventListener('submit', holdSubmitUntilVerified, true);

    let api: TurnstileApi;

    try {
        api = await loadScript();
    } catch {
        stopHolding();
        sendHeldSubmit();

        return;
    }

    if (container.value) {
        widgetId = api.render(container.value, {
            sitekey: turnstile.value.siteKey,
            'response-field-name': 'cf-turnstile-response',
            language: document.documentElement.lang || 'auto',
            'retry-interval': RETRY_INTERVAL_MS,
            callback: () => {
                tokenReady = true;
                sendHeldSubmit();
            },
            'expired-callback': () => {
                tokenReady = false;
            },
            'error-callback': () => {
                tokenReady = false;
            },
        });
    }
});

watch(
    () => props.errors,
    (errors) => {
        if (errors && Object.keys(errors).length && widgetId) {
            tokenReady = false;
            window.turnstile?.reset(widgetId);
        }
    },
);

onBeforeUnmount(() => {
    stopHolding();
    clearTimeout(waitTimer);

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

        <p v-if="waiting" class="text-sm text-muted-foreground" role="status">
            {{ $t('Finishing the security check…') }}
        </p>

        <InputError :message="errors?.['cf-turnstile-response']" />
        <InputError :message="errors?.form" />
    </div>
</template>
