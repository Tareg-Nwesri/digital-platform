<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { Plus } from '@lucide/vue';
import { ref } from 'vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { formatDate, formatNumber } from '@/lib/i18n';
import { edit, store } from '@/routes/workspace/projects';

const props = defineProps<{
    team: { slug: string; name: string };
    projects: {
        id: number;
        slug: string;
        title: string;
        status: string;
        robots: number;
        publishedAt: string | null;
    }[];
    can: { create: boolean };
}>();

const open = ref(false);
const form = useForm({ title_ar: '', title_en: '' });

function create(): void {
    form.transform((data) => ({
        ...data,
        title_ar: data.title_ar || null,
    })).post(store(props.team.slug).url);
}
</script>

<template>
    <Head :title="$t('Projects')" />

    <div class="flex flex-col gap-6 p-4 md:p-6">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <Heading
                :title="$t('Projects')"
                :description="
                    $t('What the team is building and writing about.')
                "
            />
            <Button
                v-if="can.create"
                data-test="new-project"
                @click="open = true"
            >
                <Plus class="size-4" />
                {{ $t('New project') }}
            </Button>
        </div>

        <p v-if="projects.length === 0" class="text-sm text-muted-foreground">
            {{ $t('No projects yet.') }}
        </p>
        <ul class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
            <li v-for="project in projects" :key="project.id">
                <Link
                    :href="edit([team.slug, project.slug])"
                    class="grid gap-2 rounded-xl border bg-card p-4 hover:bg-accent"
                >
                    <span class="font-medium">{{ project.title }}</span>
                    <span
                        class="flex flex-wrap items-center gap-2 text-xs text-muted-foreground"
                    >
                        <Badge
                            :variant="
                                project.status === 'published'
                                    ? 'default'
                                    : 'secondary'
                            "
                            >{{
                                $t(`work.project_status.${project.status}`)
                            }}</Badge
                        >
                        {{
                            $t(':count robots', {
                                count: formatNumber(project.robots),
                            })
                        }}
                        <template v-if="project.publishedAt"
                            >· {{ formatDate(project.publishedAt) }}</template
                        >
                    </span>
                </Link>
            </li>
        </ul>

        <Dialog v-model:open="open">
            <DialogContent class="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>{{ $t('New project') }}</DialogTitle>
                </DialogHeader>
                <form class="grid gap-4" @submit.prevent="create">
                    <div class="grid gap-2">
                        <Label for="project-title-ar">{{
                            $t('Title (Arabic)')
                        }}</Label>
                        <Input
                            id="project-title-ar"
                            v-model="form.title_ar"
                            dir="rtl"
                            maxlength="160"
                        />
                    </div>
                    <div class="grid gap-2">
                        <Label for="project-title-en">{{
                            $t('Title (English)')
                        }}</Label>
                        <Input
                            id="project-title-en"
                            v-model="form.title_en"
                            dir="ltr"
                            maxlength="160"
                            required
                        />
                        <InputError :message="form.errors.title_en" />
                    </div>
                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            @click="open = false"
                            >{{ $t('Cancel') }}</Button
                        >
                        <Button type="submit" :disabled="form.processing">{{
                            $t('Create draft')
                        }}</Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    </div>
</template>
