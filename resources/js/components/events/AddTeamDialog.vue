<script setup lang="ts">
import { router, useForm } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { store as storeRegistration } from '@/routes/console/events/registrations';

type TeamMember = {
    id: number;
    name: string;
    role: string | null;
    birthdate: string | null;
};

const props = defineProps<{
    eventSlug: string;
    categories: { id: number; name: string }[];
    teams: { id: number; name: string }[];
    teamMembers?: TeamMember[];
    canOverride: boolean;
}>();

const open = defineModel<boolean>('open', { required: true });

const form = useForm({
    team_id: null as number | null,
    category_id: props.categories[0]?.id ?? null,
    member_ids: [] as number[],
    as_invite: false,
    override_reason: '',
});

const loading = ref(false);
const teamSearch = ref('');

const filteredTeams = computed(() => {
    const query = teamSearch.value.trim().toLowerCase();

    return query === ''
        ? props.teams
        : props.teams.filter((team) => team.name.toLowerCase().includes(query));
});

watch(
    () => form.team_id,
    (teamId) => {
        form.member_ids = [];

        if (teamId === null) {
            return;
        }

        router.reload({
            only: ['teamMembers'],
            data: { team: teamId },
            onStart: () => (loading.value = true),
            onFinish: () => (loading.value = false),
        });
    },
);

watch(open, (isOpen) => {
    if (isOpen) {
        form.reset();
        form.clearErrors();
        form.category_id = props.categories[0]?.id ?? null;
    }
});

function toggleMember(id: number, checked: boolean): void {
    form.member_ids = checked
        ? [...form.member_ids, id]
        : form.member_ids.filter((memberId) => memberId !== id);
}

const errors = computed(
    () => form.errors as Record<string, string | undefined>,
);

function submit(): void {
    form.transform((data) => ({
        ...data,
        override_reason: data.override_reason || null,
    })).post(storeRegistration(props.eventSlug).url, {
        preserveScroll: true,
        onSuccess: () => (open.value = false),
    });
}

const selectClass =
    'h-9 w-full rounded-md border border-input bg-transparent px-3 text-sm shadow-xs';
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent class="max-h-[90vh] overflow-y-auto sm:max-w-lg">
            <DialogHeader>
                <DialogTitle>{{ $t('Add a team') }}</DialogTitle>
                <DialogDescription>
                    {{
                        $t(
                            'Add the team directly, or invite it to confirm its own members.',
                        )
                    }}
                </DialogDescription>
            </DialogHeader>

            <form class="grid gap-4" @submit.prevent="submit">
                <div class="grid gap-2">
                    <Label for="add-team-search">{{ $t('Team') }}</Label>
                    <Input
                        id="add-team-search"
                        v-model="teamSearch"
                        :placeholder="$t('Search teams')"
                    />
                    <select
                        v-model="form.team_id"
                        :class="selectClass"
                        size="5"
                        :aria-label="$t('Team')"
                        data-test="add-team-select"
                    >
                        <option
                            v-for="team in filteredTeams"
                            :key="team.id"
                            :value="team.id"
                        >
                            {{ team.name }}
                        </option>
                    </select>
                    <InputError :message="form.errors.team_id" />
                </div>

                <div class="grid gap-2">
                    <Label for="add-team-category">{{ $t('Category') }}</Label>
                    <select
                        id="add-team-category"
                        v-model="form.category_id"
                        :class="selectClass"
                    >
                        <option
                            v-for="category in categories"
                            :key="category.id"
                            :value="category.id"
                        >
                            {{ category.name }}
                        </option>
                    </select>
                    <InputError :message="form.errors.category_id" />
                </div>

                <fieldset v-if="form.team_id !== null" class="grid gap-2">
                    <legend class="text-sm font-medium">
                        {{ $t('Members taking part') }}
                    </legend>
                    <p v-if="loading" class="text-sm text-muted-foreground">
                        {{ $t('Loading…') }}
                    </p>
                    <p
                        v-else-if="(teamMembers ?? []).length === 0"
                        class="text-sm text-muted-foreground"
                    >
                        {{ $t('This team has no members yet.') }}
                    </p>
                    <label
                        v-for="member in teamMembers ?? []"
                        v-else
                        :key="member.id"
                        class="flex items-center gap-3 text-sm"
                    >
                        <Checkbox
                            :model-value="form.member_ids.includes(member.id)"
                            @update:model-value="
                                (value) =>
                                    toggleMember(member.id, value === true)
                            "
                        />
                        <span class="flex-1">{{ member.name }}</span>
                        <span
                            v-if="member.role"
                            class="text-xs text-muted-foreground"
                            >{{ $t(`team.role.${member.role}`) }}</span
                        >
                    </label>
                    <InputError
                        :message="errors.member_ids ?? errors['member_ids.0']"
                    />
                </fieldset>

                <label class="flex items-center gap-3 text-sm">
                    <Checkbox
                        :model-value="form.as_invite"
                        @update:model-value="
                            (value) => (form.as_invite = value === true)
                        "
                    />
                    {{ $t('Send as an invitation the team accepts') }}
                </label>

                <div v-if="canOverride" class="grid gap-2">
                    <Label for="add-team-override">{{
                        $t('Override reason (only if rules are broken)')
                    }}</Label>
                    <Input
                        id="add-team-override"
                        v-model="form.override_reason"
                        maxlength="1000"
                    />
                </div>

                <InputError :message="errors.registration" />

                <DialogFooter>
                    <Button
                        type="button"
                        variant="outline"
                        @click="open = false"
                    >
                        {{ $t('Cancel') }}
                    </Button>
                    <Button
                        type="submit"
                        :disabled="
                            form.processing ||
                            form.team_id === null ||
                            form.member_ids.length === 0
                        "
                        data-test="confirm-add-team"
                    >
                        {{
                            form.as_invite ? $t('Invite team') : $t('Add team')
                        }}
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
