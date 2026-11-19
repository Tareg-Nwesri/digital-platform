<script setup lang="ts">
import DatePicker from '@/components/forms/DatePicker.vue';
import FormOption from '@/components/forms/FormOption.vue';
import FormSelect from '@/components/forms/FormSelect.vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import { MailPlus, UserPlus, X } from '@lucide/vue';
import { trans } from 'laravel-vue-i18n';
import { computed, ref } from 'vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
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
import { formatDate, formatNumber } from '@/lib/i18n';
import { leave } from '@/routes/workspace';
import {
    destroy as revokeInvitation,
    store as storeInvitation,
} from '@/routes/workspace/invitations';
import {
    captain as transferCaptain,
    destroy as removeMember,
    offline,
    role as changeRole,
} from '@/routes/workspace/members';

type Member = {
    id: number;
    name: string;
    role: string | null;
    canLogin: boolean;
    age: number | null;
    joinedAt: string | null;
    isMe: boolean;
};

const props = defineProps<{
    team: { slug: string; name: string };
    members: Member[];
    invitations: {
        id: number;
        email: string;
        role: string;
        expiresAt: string | null;
    }[];
    limit: number;
    can: { invite: boolean; manage: boolean; transfer: boolean };
}>();

const full = computed(() => props.members.length >= props.limit);
const me = computed(() => props.members.find((member) => member.isMe));

const inviting = ref(false);
const invite = useForm({ email: '', role: 'member' });

function sendInvite(): void {
    invite.post(storeInvitation(props.team.slug).url, {
        preserveScroll: true,
        onSuccess: () => {
            inviting.value = false;
            invite.reset();
        },
    });
}

const adding = ref(false);
const offlineForm = useForm({
    name_ar: '',
    name_en: '',
    birthdate: '',
    role: 'member',
});

function addOffline(): void {
    offlineForm
        .transform((data) => ({ ...data, name_ar: data.name_ar || null }))
        .post(offline(props.team.slug).url, {
            preserveScroll: true,
            onSuccess: () => {
                adding.value = false;
                offlineForm.reset();
            },
        });
}

const options = { preserveScroll: true };

function setRole(member: Member, role: string): void {
    router.patch(
        changeRole([props.team.slug, member.id]).url,
        { role },
        options,
    );
}

function makeCaptain(member: Member): void {
    if (
        window.confirm(
            trans('Hand the captaincy to :name? You become a coach.', {
                name: member.name,
            }),
        )
    ) {
        router.post(
            transferCaptain([props.team.slug, member.id]).url,
            {},
            options,
        );
    }
}

function remove(member: Member): void {
    if (
        window.confirm(
            trans('Remove :name from the team?', { name: member.name }),
        )
    ) {
        router.delete(removeMember([props.team.slug, member.id]).url, options);
    }
}

function leaveTeam(): void {
    if (
        window.confirm(
            trans('Leave :team? You will need a new invitation to come back.', {
                team: props.team.name,
            }),
        )
    ) {
        router.post(leave(props.team.slug).url);
    }
}

function withdraw(id: number): void {
    router.delete(revokeInvitation([props.team.slug, id]).url, options);
}

const editableRoles = ['coach', 'member', 'viewer'];
</script>

<template>
    <Head :title="$t('Members')" />

    <div class="flex flex-col gap-6 p-4 md:p-6">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <Heading
                :title="$t('Members')"
                :description="
                    $t(':count of :limit places used', {
                        count: formatNumber(members.length),
                        limit: formatNumber(limit),
                    })
                "
            />
            <div class="flex flex-wrap gap-2" data-tour="members-add">
                <Button
                    v-if="can.invite"
                    :disabled="full"
                    data-test="invite-member"
                    @click="inviting = true"
                >
                    <MailPlus class="size-4" />
                    {{ $t('Invite by email') }}
                </Button>
                <Button
                    v-if="can.manage"
                    variant="outline"
                    :disabled="full"
                    data-test="add-offline"
                    @click="adding = true"
                >
                    <UserPlus class="size-4" />
                    {{ $t('Add without a login') }}
                </Button>
            </div>
        </div>

        <p
            v-if="full && (can.invite || can.manage)"
            class="rounded-lg border border-amber-500/40 p-3 text-sm"
        >
            {{
                $t(
                    'The team is full. Ask your supervising department for a bigger tier to add more people.',
                )
            }}
        </p>

        <ul class="grid gap-2" data-tour="members-list">
            <li
                v-for="member in members"
                :key="member.id"
                class="flex flex-wrap items-center justify-between gap-3 rounded-xl border bg-card p-4"
                :data-test="`member-${member.id}`"
            >
                <div class="grid gap-0.5">
                    <p class="flex flex-wrap items-center gap-2 font-medium">
                        {{ member.name }}
                        <Badge v-if="member.isMe" variant="outline">{{
                            $t('You')
                        }}</Badge>
                        <Badge v-if="!member.canLogin" variant="secondary">{{
                            $t('No login')
                        }}</Badge>
                    </p>
                    <p class="text-xs text-muted-foreground">
                        <template v-if="member.age !== null"
                            >{{
                                $t(':age years', {
                                    age: formatNumber(member.age),
                                })
                            }}
                            ·
                        </template>
                        {{
                            $t('Joined :date', {
                                date: formatDate(member.joinedAt),
                            })
                        }}
                    </p>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <FormSelect
                        v-if="
                            can.manage &&
                            member.role !== 'captain' &&
                            !member.isMe
                        "
                        :model-value="member.role ?? ''"
                        class="w-32"
                        :aria-label="$t('Role')"
                        @change="(value) => setRole(member, String(value))"
                    >
                        <FormOption
                            v-for="role in editableRoles"
                            :key="role"
                            :value="role"
                            :disabled="!member.canLogin && role === 'coach'"
                        >
                            {{ $t(`team.role.${role}`) }}
                        </FormOption>
                    </FormSelect>
                    <Badge v-else variant="outline">{{
                        $t(`team.role.${member.role}`)
                    }}</Badge>
                    <Button
                        v-if="
                            can.transfer &&
                            member.role !== 'captain' &&
                            member.canLogin
                        "
                        size="sm"
                        variant="ghost"
                        @click="makeCaptain(member)"
                    >
                        {{ $t('Make captain') }}
                    </Button>
                    <Button
                        v-if="
                            can.manage &&
                            member.role !== 'captain' &&
                            !member.isMe
                        "
                        size="icon"
                        variant="ghost"
                        class="text-destructive"
                        :aria-label="$t('Remove :name', { name: member.name })"
                        @click="remove(member)"
                    >
                        <X class="size-4" />
                    </Button>
                </div>
            </li>
        </ul>

        <section v-if="invitations.length > 0" class="grid gap-2">
            <h3 class="font-semibold">{{ $t('Invitations waiting') }}</h3>
            <ul class="grid gap-2 text-sm">
                <li
                    v-for="invitation in invitations"
                    :key="invitation.id"
                    class="flex items-center justify-between gap-2 rounded-lg border p-3"
                >
                    <span>
                        <span dir="ltr">{{ invitation.email }}</span>
                        <span class="ms-2 text-muted-foreground">{{
                            $t(`team.role.${invitation.role}`)
                        }}</span>
                    </span>
                    <Button
                        size="sm"
                        variant="ghost"
                        @click="withdraw(invitation.id)"
                        >{{ $t('Withdraw') }}</Button
                    >
                </li>
            </ul>
        </section>

        <div
            v-if="me && me.role !== 'captain'"
            class="border-t pt-4"
            data-tour="members-leave"
        >
            <Button
                variant="ghost"
                class="text-destructive"
                data-test="leave-team"
                @click="leaveTeam"
                >{{ $t('Leave the team') }}</Button
            >
        </div>

        <Dialog v-model:open="inviting">
            <DialogContent class="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>{{ $t('Invite by email') }}</DialogTitle>
                    <DialogDescription>{{
                        $t('They get an email with a link to join the team.')
                    }}</DialogDescription>
                </DialogHeader>
                <form class="grid gap-4" @submit.prevent="sendInvite">
                    <div class="grid gap-2">
                        <Label for="invite-email">{{ $t('Email') }}</Label>
                        <Input
                            id="invite-email"
                            v-model="invite.email"
                            type="email"
                            dir="ltr"
                            required
                        />
                        <InputError :message="invite.errors.email" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="invite-role">{{ $t('Role') }}</Label>
                        <FormSelect id="invite-role" v-model="invite.role">
                            <FormOption
                                v-for="role in editableRoles"
                                :key="role"
                                :value="role"
                            >
                                {{ $t(`team.role.${role}`) }}
                            </FormOption>
                        </FormSelect>
                        <InputError :message="invite.errors.role" />
                    </div>
                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            @click="inviting = false"
                            >{{ $t('Cancel') }}</Button
                        >
                        <Button type="submit" :disabled="invite.processing">{{
                            $t('Send invitation')
                        }}</Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>

        <Dialog v-model:open="adding">
            <DialogContent class="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>{{ $t('Add without a login') }}</DialogTitle>
                    <DialogDescription>{{
                        $t(
                            'For members who will not sign in, such as young students. Their birthdate is used for event age rules.',
                        )
                    }}</DialogDescription>
                </DialogHeader>
                <form class="grid gap-4" @submit.prevent="addOffline">
                    <div class="grid gap-2">
                        <Label for="offline-name-ar">{{
                            $t('Name (Arabic)')
                        }}</Label>
                        <Input
                            id="offline-name-ar"
                            v-model="offlineForm.name_ar"
                            dir="rtl"
                            maxlength="120"
                        />
                    </div>
                    <div class="grid gap-2">
                        <Label for="offline-name-en">{{
                            $t('Name (English)')
                        }}</Label>
                        <Input
                            id="offline-name-en"
                            v-model="offlineForm.name_en"
                            dir="ltr"
                            maxlength="120"
                            required
                        />
                        <InputError :message="offlineForm.errors.name_en" />
                    </div>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div class="grid gap-2">
                            <Label for="offline-birthdate">{{
                                $t('Birthdate')
                            }}</Label>
                            <DatePicker
                                id="offline-birthdate"
                                v-model="offlineForm.birthdate"
                                required
                                no-future
                            />
                            <InputError
                                :message="offlineForm.errors.birthdate"
                            />
                        </div>
                        <div class="grid gap-2">
                            <Label for="offline-role">{{ $t('Role') }}</Label>
                            <FormSelect
                                id="offline-role"
                                v-model="offlineForm.role"
                            >
                                <FormOption value="member">
                                    {{ $t('team.role.member') }}
                                </FormOption>
                                <FormOption value="viewer">
                                    {{ $t('team.role.viewer') }}
                                </FormOption>
                            </FormSelect>
                            <InputError :message="offlineForm.errors.role" />
                        </div>
                    </div>
                    <InputError
                        :message="
                            (
                                offlineForm.errors as Record<
                                    string,
                                    string | undefined
                                >
                            ).members
                        "
                    />
                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            @click="adding = false"
                            >{{ $t('Cancel') }}</Button
                        >
                        <Button
                            type="submit"
                            :disabled="offlineForm.processing"
                            >{{ $t('Add member') }}</Button
                        >
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    </div>
</template>
