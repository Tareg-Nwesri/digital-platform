<script setup lang="ts">
import DatePicker from '@/components/forms/DatePicker.vue';
import FormOption from '@/components/forms/FormOption.vue';
import FormSelect from '@/components/forms/FormSelect.vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import { trans } from 'laravel-vue-i18n';
import { ref } from 'vue';
import PostStatusBadge from '@/components/content/PostStatusBadge.vue';
import RichTextEditor from '@/components/forms/RichTextEditor.vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { toZonedInput, formatZoned } from '@/lib/zonedDate';
import { dashboard } from '@/routes/console';
import {
    destroy,
    index,
    publish,
    submit,
    unpublish,
    update,
} from '@/routes/console/posts';

const TIMEZONE = 'Africa/Tripoli';

const props = defineProps<{
    post: {
        id: number;
        slug: string;
        departmentId: number;
        titleEn: string;
        titleAr: string | null;
        excerptEn: string | null;
        excerptAr: string | null;
        bodyEn: string | null;
        bodyAr: string | null;
        status: string;
        publishedAt: string | null;
    };
    departments: { id: number; name: string }[];
    can: { edit: boolean; publish: boolean };
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Organisation console', href: dashboard() },
            { title: 'News', href: index() },
        ],
    },
});

const form = useForm({
    department_id: props.post.departmentId,
    title_ar: props.post.titleAr ?? '',
    title_en: props.post.titleEn,
    excerpt_ar: props.post.excerptAr ?? '',
    excerpt_en: props.post.excerptEn ?? '',
    body_ar: props.post.bodyAr ?? '',
    body_en: props.post.bodyEn ?? '',
});

function save(): void {
    form.transform((data) =>
        Object.fromEntries(
            Object.entries(data).map(([key, value]) => [
                key,
                value === '' ? null : value,
            ]),
        ),
    ).patch(update(props.post.id).url, { preserveScroll: true });
}

const scheduleAt = ref(toZonedInput(props.post.publishedAt, TIMEZONE));
const working = ref(false);
const options = {
    preserveScroll: true,
    onStart: () => (working.value = true),
    onFinish: () => (working.value = false),
};

function sendForReview(): void {
    router.post(submit(props.post.id).url, {}, options);
}

function publishNow(): void {
    router.post(publish(props.post.id).url, {}, options);
}

function schedule(): void {
    router.post(
        publish(props.post.id).url,
        { published_at: scheduleAt.value },
        options,
    );
}

function moveToDraft(): void {
    router.post(unpublish(props.post.id).url, {}, options);
}

function remove(): void {
    const question = props.post.publishedAt
        ? trans('Archive this post? It leaves the public site.')
        : trans('Delete this draft? This cannot be undone.');

    if (window.confirm(question)) {
        router.delete(destroy(props.post.id).url);
    }
}

const textareaClass =
    'rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs';
</script>

<template>
    <Head :title="post.titleAr || post.titleEn" />

    <div class="grid gap-6 p-4 md:p-6 xl:grid-cols-[minmax(0,1fr)_20rem]">
        <div class="grid content-start gap-6">
            <div class="flex flex-wrap items-center gap-3">
                <Heading
                    :title="post.titleAr || post.titleEn"
                    :description="$t('Edit the post in both languages.')"
                />
                <PostStatusBadge :status="post.status" />
            </div>

            <form class="grid gap-5" @submit.prevent="save">
                <div class="grid gap-2 sm:max-w-sm">
                    <Label for="post-department">{{ $t('Department') }}</Label>
                    <FormSelect
                        id="post-department"
                        v-model="form.department_id"
                        :disabled="!can.edit"
                    >
                        <FormOption
                            v-for="department in departments"
                            :key="department.id"
                            :value="department.id"
                        >
                            {{ department.name }}
                        </FormOption>
                    </FormSelect>
                    <InputError :message="form.errors.department_id" />
                </div>

                <section
                    class="grid gap-4 rounded-xl border bg-card p-5"
                    lang="ar"
                    dir="rtl"
                >
                    <h3 class="font-semibold">{{ $t('Arabic') }}</h3>
                    <div class="grid gap-2">
                        <Label for="post-title-ar">{{
                            $t('Title (Arabic)')
                        }}</Label>
                        <Input
                            id="post-title-ar"
                            v-model="form.title_ar"
                            maxlength="160"
                            :disabled="!can.edit"
                        />
                        <InputError :message="form.errors.title_ar" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="post-excerpt-ar">{{
                            $t('Summary (Arabic)')
                        }}</Label>
                        <textarea
                            id="post-excerpt-ar"
                            v-model="form.excerpt_ar"
                            rows="2"
                            maxlength="400"
                            :class="textareaClass"
                            :disabled="!can.edit"
                        />
                        <InputError :message="form.errors.excerpt_ar" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="post-body-ar">{{
                            $t('Text (Arabic)')
                        }}</Label>
                        <RichTextEditor
                            id="post-body-ar"
                            v-model="form.body_ar"
                            dir="rtl"
                            :label="$t('Text (Arabic)')"
                            :disabled="!can.edit"
                        />
                        <InputError :message="form.errors.body_ar" />
                    </div>
                </section>

                <section
                    class="grid gap-4 rounded-xl border bg-card p-5"
                    lang="en"
                    dir="ltr"
                >
                    <h3 class="font-semibold">{{ $t('English') }}</h3>
                    <div class="grid gap-2">
                        <Label for="post-title-en">{{
                            $t('Title (English)')
                        }}</Label>
                        <Input
                            id="post-title-en"
                            v-model="form.title_en"
                            required
                            maxlength="160"
                            :disabled="!can.edit"
                        />
                        <InputError :message="form.errors.title_en" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="post-excerpt-en">{{
                            $t('Summary (English)')
                        }}</Label>
                        <textarea
                            id="post-excerpt-en"
                            v-model="form.excerpt_en"
                            rows="2"
                            maxlength="400"
                            :class="textareaClass"
                            :disabled="!can.edit"
                        />
                        <InputError :message="form.errors.excerpt_en" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="post-body-en">{{
                            $t('Text (English)')
                        }}</Label>
                        <RichTextEditor
                            id="post-body-en"
                            v-model="form.body_en"
                            dir="ltr"
                            :label="$t('Text (English)')"
                            :disabled="!can.edit"
                        />
                        <InputError :message="form.errors.body_en" />
                    </div>
                </section>

                <div v-if="can.edit" class="flex justify-end">
                    <Button
                        type="submit"
                        :disabled="form.processing"
                        data-test="save-post"
                        >{{ $t('Save post') }}</Button
                    >
                </div>
            </form>
        </div>

        <aside
            class="grid content-start gap-4 rounded-xl border bg-card p-5 xl:sticky xl:top-4"
        >
            <h3 class="font-semibold">{{ $t('Publishing') }}</h3>
            <p class="text-sm text-muted-foreground">
                <template v-if="post.status === 'published'">{{
                    $t('Published on :date.', {
                        date: formatZoned(post.publishedAt, TIMEZONE),
                    })
                }}</template>
                <template v-else-if="post.status === 'scheduled'">{{
                    $t('Goes live on :date.', {
                        date: formatZoned(post.publishedAt, TIMEZONE),
                    })
                }}</template>
                <template v-else-if="post.status === 'in_review'">{{
                    $t('Waiting for a department admin to publish it.')
                }}</template>
                <template v-else-if="post.status === 'archived'">{{
                    $t('Archived posts are not shown anywhere.')
                }}</template>
                <template v-else>{{
                    $t('Only staff can see drafts.')
                }}</template>
            </p>

            <Button
                v-if="can.edit && post.status === 'draft'"
                variant="outline"
                :disabled="working"
                data-test="submit-post"
                @click="sendForReview"
            >
                {{ $t('Send for review') }}
            </Button>

            <template
                v-if="
                    can.publish &&
                    ['draft', 'in_review', 'scheduled'].includes(post.status)
                "
            >
                <Button
                    :disabled="working"
                    data-test="publish-now"
                    @click="publishNow"
                    >{{ $t('Publish now') }}</Button
                >
                <div class="grid gap-2">
                    <Label for="post-schedule">{{
                        $t('Or publish on (Libya time)')
                    }}</Label>
                    <DatePicker
                        id="post-schedule"
                        v-model="scheduleAt"
                        with-time
                    />
                    <Button
                        variant="outline"
                        :disabled="working || scheduleAt === ''"
                        @click="schedule"
                        >{{ $t('Schedule') }}</Button
                    >
                </div>
            </template>

            <Button
                v-if="
                    can.publish &&
                    ['published', 'scheduled'].includes(post.status)
                "
                variant="outline"
                :disabled="working"
                @click="moveToDraft"
            >
                {{ $t('Move back to draft') }}
            </Button>

            <Button
                v-if="can.edit && post.status !== 'archived'"
                variant="ghost"
                class="text-destructive"
                :disabled="working"
                @click="remove"
            >
                {{ post.publishedAt ? $t('Archive') : $t('Delete draft') }}
            </Button>
        </aside>
    </div>
</template>
