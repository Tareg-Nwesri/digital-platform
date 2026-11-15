<script setup lang="ts">
import { Form, Link } from '@inertiajs/vue3';
import BotGuard from '@/components/forms/BotGuard.vue';
import PageHero from '@/components/public/PageHero.vue';
import PublicHead from '@/components/public/PublicHead.vue';
import type { PublicMetaTags } from '@/composables/usePublicSite';
import { usePublicSite } from '@/composables/usePublicSite';

defineProps<{ meta: PublicMetaTags }>();

const { url } = usePublicSite();

const fields = [
    ['name', 'text', 'name'],
    ['email', 'email', 'email'],
    ['subject', 'text', undefined],
] as const;
</script>

<template>
    <PublicHead :meta="meta" />

    <PageHero
        :eyebrow="$t('public_site.brand')"
        :title="$t('public_site.contact')"
        :lead="$t('public_site.contact_copy')"
    >
        <a class="button button--arrow" href="#contact-form">{{
            $t('public_site.contact_cta')
        }}</a>
    </PageHero>

    <section class="section">
        <div class="public-shell contact-layout">
            <div class="form-card">
                <h2 id="contact-form">
                    {{ $t('public_site.contact_form_heading') }}
                </h2>
                <Form
                    :action="url('/contact')"
                    method="post"
                    class="public-form form-grid"
                    reset-on-success
                    v-slot="{ errors, processing, wasSuccessful }"
                    data-test="contact-form"
                >
                    <p
                        v-if="wasSuccessful"
                        class="wide inline-flash"
                        role="status"
                    >
                        {{ $t('public.contact_sent') }}
                    </p>
                    <label
                        v-for="[field, type, autocomplete] in fields"
                        :key="field"
                        :for="`contact-${field}`"
                        :class="{ wide: field === 'subject' }"
                    >
                        {{ $t(`public_site.${field}`) }}
                        <input
                            :id="`contact-${field}`"
                            :name="field"
                            :type="type"
                            :autocomplete="autocomplete"
                            required
                            :aria-invalid="errors[field] ? 'true' : 'false'"
                            :aria-describedby="
                                errors[field] ? `${field}-error` : undefined
                            "
                        />
                        <span
                            v-if="errors[field]"
                            :id="`${field}-error`"
                            class="error"
                            >{{ errors[field] }}</span
                        >
                    </label>
                    <label for="contact-message" class="wide">
                        {{ $t('public_site.message') }}
                        <textarea
                            id="contact-message"
                            name="message"
                            rows="7"
                            required
                            :aria-invalid="errors.message ? 'true' : 'false'"
                        />
                        <span v-if="errors.message" class="error">{{
                            errors.message
                        }}</span>
                    </label>
                    <div class="wide">
                        <label class="privacy-choice" for="contact-privacy">
                            <input
                                id="contact-privacy"
                                type="checkbox"
                                name="privacy"
                                value="1"
                                required
                            />
                            {{ $t('public_site.privacy_accept') }}
                        </label>
                        <span v-if="errors.privacy" class="error">{{
                            errors.privacy
                        }}</span>
                    </div>
                    <div class="wide">
                        <BotGuard :errors="errors" />
                    </div>
                    <div class="wide form-actions">
                        <button
                            class="button button--arrow"
                            type="submit"
                            :disabled="processing"
                        >
                            {{ $t('public_site.send') }}
                        </button>
                        <Link class="text-link" :href="url('/privacy')">{{
                            $t('public_site.privacy_read')
                        }}</Link>
                    </div>
                </Form>
            </div>
            <aside
                class="contact-details"
                aria-labelledby="contact-details-heading"
            >
                <h2 id="contact-details-heading">
                    {{ $t('public_site.contact_details_heading') }}
                </h2>
                <p>{{ $t('public_site.contact_details_copy') }}</p>
                <div class="contact-details__item">
                    <h3>{{ $t('public_site.contact_location_heading') }}</h3>
                    <p>{{ $t('public_site.contact_location_copy') }}</p>
                </div>
                <div class="contact-details__item">
                    <h3>{{ $t('public_site.contact_more_heading') }}</h3>
                    <nav :aria-label="$t('public_site.contact_more_heading')">
                        <Link class="text-link" :href="url('/faq')">{{
                            $t('public_site.faq')
                        }}</Link>
                        <Link class="text-link" :href="url('/get-involved')">{{
                            $t('public_site.get_involved')
                        }}</Link>
                        <Link class="text-link" :href="url('/privacy')">{{
                            $t('public_site.privacy_read')
                        }}</Link>
                    </nav>
                </div>
            </aside>
        </div>
    </section>
</template>
