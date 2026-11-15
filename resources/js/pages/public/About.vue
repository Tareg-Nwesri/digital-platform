<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import MediaFigure from '@/components/public/MediaFigure.vue';
import PublicHead from '@/components/public/PublicHead.vue';
import PublicIcon from '@/components/public/PublicIcon.vue';
import type { PublicMetaTags } from '@/composables/usePublicSite';
import { usePublicSite } from '@/composables/usePublicSite';

defineProps<{
    meta: PublicMetaTags;
    departments: { name: string; description: string | null }[];
}>();

const { url } = usePublicSite();
</script>

<template>
    <PublicHead :meta="meta" />

    <section class="page-hero page-hero--split about-hero">
        <div class="public-shell">
            <div>
                <p class="eyebrow">{{ $t('public_site.about_heading') }}</p>
                <h1>{{ $t('public_about.heading') }}</h1>
                <p class="page-hero__lead">{{ $t('public_about.intro') }}</p>
                <div class="actions page-hero__actions">
                    <Link
                        class="button button--arrow"
                        :href="url('/contact')"
                        >{{ $t('public_about.cta') }}</Link
                    >
                    <a class="button button--ghost" href="#purpose">{{
                        $t('public_about.explore')
                    }}</a>
                </div>
                <nav
                    class="about-navigation"
                    :aria-label="$t('public_about.page_navigation')"
                >
                    <a href="#purpose">{{ $t('public_about.purpose_nav') }}</a>
                    <a href="#approach">{{
                        $t('public_about.approach_nav')
                    }}</a>
                    <a href="#sustainability">{{
                        $t('public_about.sustainability_nav')
                    }}</a>
                </nav>
            </div>
            <div class="about-hero__media">
                <MediaFigure
                    eager
                    src="/assets/img/robots/general/student-robotics-team.webp"
                    :alt="$t('public_about.image_alt')"
                />
                <p class="about-image-note">
                    {{ $t('public_about.image_note') }}
                </p>
            </div>
        </div>
    </section>

    <section
        id="purpose"
        class="section"
        :aria-label="$t('public_about.explore')"
    >
        <div class="public-shell">
            <div class="about-purpose">
                <article
                    v-for="purpose in ['vision', 'mission']"
                    :key="purpose"
                >
                    <h2>
                        <span class="icon-tile"
                            ><PublicIcon
                                class="about-icon"
                                :name="purpose"
                                :size="32"
                                :stroke-width="1.6"
                        /></span>
                        <span>{{ $t(`public_about.${purpose}`) }}</span>
                    </h2>
                    <h3>{{ $t(`public_about.${purpose}_heading`) }}</h3>
                    <p>{{ $t(`public_about.${purpose}_copy`) }}</p>
                </article>
            </div>
            <blockquote class="about-statement">
                <p>{{ $t('public_about.purpose') }}</p>
            </blockquote>
        </div>
    </section>

    <section
        id="approach"
        class="section section--surface"
        aria-labelledby="approach-heading"
    >
        <div class="public-shell about-approach">
            <header class="section-heading">
                <h2 id="approach-heading">
                    {{ $t('public_about.approach_heading') }}
                </h2>
                <p class="section-lead">
                    {{ $t('public_about.approach_intro') }}
                </p>
            </header>
            <div class="about-principles">
                <article
                    v-for="principle in ['learning', 'care', 'practice']"
                    :key="principle"
                >
                    <span class="icon-tile"
                        ><PublicIcon
                            class="about-icon"
                            :name="principle"
                            :size="32"
                            :stroke-width="1.6"
                    /></span>
                    <h3>{{ $t(`public_about.${principle}_heading`) }}</h3>
                    <p>{{ $t(`public_about.${principle}_copy`) }}</p>
                </article>
            </div>
        </div>
    </section>

    <section
        v-if="departments.length"
        class="section"
        aria-labelledby="departments-heading"
    >
        <div class="public-shell">
            <header class="section-heading">
                <h2 id="departments-heading">{{ $t('public.departments') }}</h2>
            </header>
            <div class="about-principles">
                <article
                    v-for="department in departments"
                    :key="department.name"
                >
                    <h3>{{ department.name }}</h3>
                    <p v-if="department.description">
                        {{ department.description }}
                    </p>
                </article>
            </div>
        </div>
    </section>

    <section
        id="sustainability"
        class="section"
        aria-labelledby="sustainability-heading"
    >
        <div class="public-shell">
            <div class="about-sustainability">
                <figure class="about-engineering">
                    <img
                        src="/assets/img/robots/general/six-wheel-rover-exploded.webp"
                        :alt="$t('public_about.sustainability_alt')"
                        width="1448"
                        height="1086"
                        loading="lazy"
                        decoding="async"
                    />
                    <figcaption>
                        {{ $t('public_about.sustainability_caption') }}
                    </figcaption>
                </figure>
                <div>
                    <span class="icon-tile"
                        ><PublicIcon
                            class="about-icon"
                            name="sustainability"
                            :size="32"
                            :stroke-width="1.6"
                    /></span>
                    <h2 id="sustainability-heading">
                        {{ $t('public_about.sustainability_heading') }}
                    </h2>
                    <p>{{ $t('public_about.sustainability_copy') }}</p>
                    <ul class="about-habits">
                        <li
                            v-for="habit in ['reuse', 'energy', 'lifecycle']"
                            :key="habit"
                        >
                            {{ $t(`public_about.${habit}`) }}
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </section>

    <section
        class="section section--flush-top"
        aria-labelledby="about-closing-heading"
    >
        <div class="public-shell">
            <div class="cta-panel">
                <h2 id="about-closing-heading">
                    {{ $t('public_about.closing_heading') }}
                </h2>
                <div>
                    <p>{{ $t('public_about.closing_copy') }}</p>
                    <Link
                        class="button button--arrow"
                        :href="url('/contact')"
                        >{{ $t('public_about.closing_cta') }}</Link
                    >
                </div>
            </div>
        </div>
    </section>
</template>
