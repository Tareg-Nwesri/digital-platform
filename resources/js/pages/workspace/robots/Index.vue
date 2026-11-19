<script setup lang="ts">
import FormOption from '@/components/forms/FormOption.vue';
import FormSelect from '@/components/forms/FormSelect.vue';
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
import { formatNumber } from '@/lib/i18n';
import { show, store } from '@/routes/workspace/robots';

const props = defineProps<{
    team: { slug: string; name: string };
    robots: {
        id: number;
        slug: string;
        name: string;
        status: string;
        project: string | null;
        parts: number;
        isPublic: boolean;
    }[];
    statuses: string[];
    projects: { id: number; title: string }[];
    can: { create: boolean };
}>();

const open = ref(false);
const form = useForm({
    name: '',
    status: 'design',
    project_id: null as number | null,
});

function create(): void {
    form.post(store(props.team.slug).url);
}
</script>

<template>
    <Head :title="$t('Robots')" />

    <div class="flex flex-col gap-6 p-4 md:p-6">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <Heading
                :title="$t('Robots')"
                :description="
                    $t('Each robot has a parts list priced from the catalogue.')
                "
            />
            <Button
                v-if="can.create"
                data-test="new-robot"
                data-tour="robots-new"
                @click="open = true"
            >
                <Plus class="size-4" />
                {{ $t('New robot') }}
            </Button>
        </div>

        <p v-if="robots.length === 0" class="text-sm text-muted-foreground">
            {{ $t('No robots yet.') }}
        </p>
        <ul
            class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3"
            data-tour="robots-list"
        >
            <li v-for="robot in robots" :key="robot.id">
                <Link
                    :href="show([team.slug, robot.slug])"
                    class="grid gap-2 rounded-xl border bg-card p-4 hover:bg-accent"
                >
                    <span class="font-medium">{{ robot.name }}</span>
                    <span
                        class="flex flex-wrap items-center gap-2 text-xs text-muted-foreground"
                    >
                        <Badge variant="secondary">{{
                            $t(`work.robot_status.${robot.status}`)
                        }}</Badge>
                        {{
                            $t(':count parts', {
                                count: formatNumber(robot.parts),
                            })
                        }}
                        <template v-if="robot.project"
                            >· {{ robot.project }}</template
                        >
                    </span>
                </Link>
            </li>
        </ul>

        <Dialog v-model:open="open">
            <DialogContent class="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>{{ $t('New robot') }}</DialogTitle>
                </DialogHeader>
                <form class="grid gap-4" @submit.prevent="create">
                    <div class="grid gap-2">
                        <Label for="robot-name">{{ $t('Name') }}</Label>
                        <Input
                            id="robot-name"
                            v-model="form.name"
                            maxlength="120"
                            required
                        />
                        <InputError :message="form.errors.name" />
                    </div>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div class="grid gap-2">
                            <Label for="robot-status">{{ $t('Status') }}</Label>
                            <FormSelect id="robot-status" v-model="form.status">
                                <FormOption
                                    v-for="status in statuses"
                                    :key="status"
                                    :value="status"
                                >
                                    {{ $t(`work.robot_status.${status}`) }}
                                </FormOption>
                            </FormSelect>
                        </div>
                        <div class="grid gap-2">
                            <Label for="robot-project">{{
                                $t('Project')
                            }}</Label>
                            <FormSelect
                                id="robot-project"
                                v-model="form.project_id"
                            >
                                <FormOption :value="null">
                                    {{ $t('No project') }}
                                </FormOption>
                                <FormOption
                                    v-for="project in projects"
                                    :key="project.id"
                                    :value="project.id"
                                >
                                    {{ project.title }}
                                </FormOption>
                            </FormSelect>
                            <InputError :message="form.errors.project_id" />
                        </div>
                    </div>
                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            @click="open = false"
                            >{{ $t('Cancel') }}</Button
                        >
                        <Button type="submit" :disabled="form.processing">{{
                            $t('Create robot')
                        }}</Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    </div>
</template>
