<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import PageHero from '@/components/public/PageHero.vue';
import PublicIcon from '@/components/public/PublicIcon.vue';
import { usePublicSite } from '@/composables/usePublicSite';

/**
 * The FAQ and privacy pages share this layout (old static.blade.php).
 */
const props = defineProps<{ kind: 'faq' | 'privacy' }>();

const { url } = usePublicSite();

const items =
    props.kind === 'faq'
        ? [
              ['faq_join_q', 'faq_join_a'],
              ['faq_event_q', 'faq_event_a'],
              ['faq_contact_q', 'faq_contact_a'],
          ]
        : [
              ['privacy_contact_title', 'privacy_contact_copy'],
              ['privacy_event_title', 'privacy_event_copy'],
              ['privacy_review_title', 'privacy_review_copy'],
          ];
</script>

<template>
    <PageHero
        :eyebrow="$t('public_site.brand')"
        :title="$t(`public_site.${kind}`)"
        :lead="
            $t(
                kind === 'faq'
                    ? 'public_site.faq_intro'
                    : 'public_site.privacy_intro',
            )
        "
    />

    <section class="section">
        <div class="public-shell qa-layout">
            <div class="qa-list">
                <article
                    v-for="[question, answer] in items"
                    :key="question"
                    class="qa-item"
                >
                    <h2>{{ $t(`public_site.${question}`) }}</h2>
                    <p>{{ $t(`public_site.${answer}`) }}</p>
                </article>
            </div>
            <aside class="card detail-aside">
                <span class="icon-tile" aria-hidden="true"
                    ><PublicIcon name="question" :size="26"
                /></span>
                <h2>{{ $t('public_site.faq_more') }}</h2>
                <p>{{ $t('public_site.faq_more_copy') }}</p>
                <Link
                    class="button button--dark button--arrow"
                    :href="url('/contact')"
                    >{{ $t('public_site.contact_cta') }}</Link
                >
                <Link
                    class="text-link"
                    :href="url(kind === 'faq' ? '/privacy' : '/faq')"
                    >{{
                        kind === 'faq'
                            ? $t('public_site.privacy_read')
                            : $t('public_site.faq')
                    }}</Link
                >
            </aside>
        </div>
    </section>
</template>
