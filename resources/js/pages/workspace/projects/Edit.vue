<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { trans } from 'laravel-vue-i18n';
import RichTextEditor from '@/components/forms/RichTextEditor.vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    destroy,
    publish,
    unpublish,
    update,
} from '@/routes/workspace/projects';
import { show as showRobot } from '@/routes/workspace/robots';

const props = defineProps<{
    team: { slug: string; name: string };
    project: {
        id: number;
        slug: string;
        titleEn: string;
        titleAr: string | null;
        excerptEn: string | null;
        excerptAr: string | null;
        bodyEn: string | null;
        bodyAr: string | null;
        status: string;
        publishedAt: string | null;
        robots: { id: number; name: string; slug: string }[];
    };
    can: { edit: boolean; publish: boolean };
}>();

const slug = () => [props.team.slug, props.project.slug] as [string, string];

const form = useForm({
    title_ar: props.project.titleAr ?? '',
    title_en: props.project.titleEn,
    excerpt_ar: props.project.excerptAr ?? '',
    excerpt_en: props.project.excerptEn ?? '',
    body_ar: props.project.bodyAr ?? '',
    body_en: props.project.bodyEn ?? '',
});

function save(): void {
    form.transform((data) =>
        Object.fromEntries(
            Object.entries(data).map(([key, value]) => [
                key,
                value === '' ? null : value,
            ]),
        ),
    ).patch(update(slug()).url, { preserveScroll: true });
}

function togglePublish(): void {
    const route = props.project.status === 'published' ? unpublish : publish;
    router.visit(route(slug()).url, {
        method: props.project.status === 'published' ? 'delete' : 'post',
        preserveScroll: true,
    });
}

function remove(): void {
    if (window.confirm(trans('Delete this project? This cannot be undone.'))) {
        router.delete(destroy(slug()).url);
    }
}

const textareaClass =
    'rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs';
</script>

<template>
    <Head :title="project.titleAr || project.titleEn" />

    <div class="grid gap-6 p-4 md:p-6 xl:grid-cols-[minmax(0,1fr)_18rem]">
        <form class="grid content-start gap-5" @submit.prevent="save">
            <div class="flex flex-wrap items-center gap-3">
                <Heading
                    :title="project.titleAr || project.titleEn"
                    :description="team.name"
                />
                <Badge
                    :variant="
                        project.status === 'published' ? 'default' : 'secondary'
                    "
                    >{{ $t(`work.project_status.${project.status}`) }}</Badge
                >
            </div>

            <section
                class="grid gap-4 rounded-xl border bg-card p-5"
                lang="ar"
                dir="rtl"
            >
                <h3 class="font-semibold">{{ $t('Arabic') }}</h3>
                <div class="grid gap-2">
                    <Label for="title-ar">{{ $t('Title (Arabic)') }}</Label>
                    <Input
                        id="title-ar"
                        v-model="form.title_ar"
                        maxlength="160"
                        :disabled="!can.edit"
                    />
                </div>
                <div class="grid gap-2">
                    <Label for="excerpt-ar">{{ $t('Summary (Arabic)') }}</Label>
                    <textarea
                        id="excerpt-ar"
                        v-model="form.excerpt_ar"
                        rows="2"
                        maxlength="400"
                        :class="textareaClass"
                        :disabled="!can.edit"
                    />
                </div>
                <RichTextEditor
                    v-model="form.body_ar"
                    dir="rtl"
                    :label="$t('Text (Arabic)')"
                    :disabled="!can.edit"
                />
            </section>

            <section
                class="grid gap-4 rounded-xl border bg-card p-5"
                lang="en"
                dir="ltr"
            >
                <h3 class="font-semibold">{{ $t('English') }}</h3>
                <div class="grid gap-2">
                    <Label for="title-en">{{ $t('Title (English)') }}</Label>
                    <Input
                        id="title-en"
                        v-model="form.title_en"
                        maxlength="160"
                        required
                        :disabled="!can.edit"
                    />
                    <InputError :message="form.errors.title_en" />
                </div>
                <div class="grid gap-2">
                    <Label for="excerpt-en">{{
                        $t('Summary (English)')
                    }}</Label>
                    <textarea
                        id="excerpt-en"
                        v-model="form.excerpt_en"
                        rows="2"
                        maxlength="400"
                        :class="textareaClass"
                        :disabled="!can.edit"
                    />
                </div>
                <RichTextEditor
                    v-model="form.body_en"
                    dir="ltr"
                    :label="$t('Text (English)')"
                    :disabled="!can.edit"
                />
            </section>

            <div v-if="can.edit" class="flex justify-end">
                <Button
                    type="submit"
                    :disabled="form.processing"
                    data-test="save-project"
                    >{{ $t('Save project') }}</Button
                >
            </div>
        </form>

        <aside class="grid content-start gap-4 rounded-xl border bg-card p-5">
            <Button
                v-if="can.publish"
                :variant="
                    project.status === 'published' ? 'outline' : 'default'
                "
                @click="togglePublish"
            >
                {{
                    project.status === 'published'
                        ? $t('Move back to draft')
                        : $t('Publish')
                }}
            </Button>
            <p
                v-else-if="project.status !== 'published'"
                class="text-sm text-muted-foreground"
            >
                {{ $t('The captain or a coach publishes projects.') }}
            </p>

            <div class="grid gap-2">
                <h3 class="text-sm font-semibold">{{ $t('Robots') }}</h3>
                <p
                    v-if="project.robots.length === 0"
                    class="text-sm text-muted-foreground"
                >
                    {{ $t('No robots linked yet.') }}
                </p>
                <Link
                    v-for="robot in project.robots"
                    :key="robot.id"
                    :href="showRobot([team.slug, robot.slug])"
                    class="text-sm hover:underline"
                    >{{ robot.name }}</Link
                >
            </div>

            <Button
                v-if="can.edit"
                variant="ghost"
                class="text-destructive"
                @click="remove"
                >{{ $t('Delete project') }}</Button
            >
        </aside>
    </div>
</template>
