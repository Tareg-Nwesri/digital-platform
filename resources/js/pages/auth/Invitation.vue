<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import InvitationController from '@/actions/App/Http/Controllers/Auth/InvitationController';
import InputError from '@/components/InputError.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';

defineOptions({
    layout: {
        title: 'Accept your invitation',
        description: 'Join your team or department on the Digital Robotics platform',
    },
});

defineProps<{
    token: string;
    invitation: {
        email: string;
        role: string;
        roleLabel: string;
        workspaceType: 'team' | 'department';
        workspaceName: string | null;
        expiresAt: string;
    };
    existingAccount: boolean;
    signedInAsInvitee: boolean;
}>();
</script>

<template>
    <Head title="Accept invitation" />

    <div
        class="mb-6 rounded-lg border bg-muted/40 p-4 text-sm"
        data-test="invitation-summary"
    >
        <p class="font-medium text-foreground">
            {{ invitation.workspaceName }}
        </p>
        <p class="text-muted-foreground">
            {{ invitation.roleLabel }} · {{ invitation.email }}
        </p>
        <p class="mt-1 text-xs text-muted-foreground">
            Expires {{ invitation.expiresAt }}
        </p>
    </div>

    <Form
        v-bind="InvitationController.accept.form(token)"
        :reset-on-success="['password', 'password_confirmation', 'current_password']"
        v-slot="{ errors, processing }"
    >
        <div class="grid gap-6">
            <InputError :message="errors.invitation" />

            <template v-if="!existingAccount">
                <div class="grid gap-2">
                    <Label for="name_en">Full name (English)</Label>
                    <Input
                        id="name_en"
                        name="name_en"
                        autocomplete="name"
                        required
                        autofocus
                    />
                    <InputError :message="errors.name_en" />
                </div>

                <div class="grid gap-2">
                    <Label for="name_ar">Full name (Arabic, optional)</Label>
                    <Input id="name_ar" name="name_ar" dir="rtl" />
                    <InputError :message="errors.name_ar" />
                </div>

                <div class="grid gap-2">
                    <Label for="password">Password</Label>
                    <PasswordInput
                        id="password"
                        name="password"
                        autocomplete="new-password"
                        required
                    />
                    <InputError :message="errors.password" />
                </div>

                <div class="grid gap-2">
                    <Label for="password_confirmation">Confirm password</Label>
                    <PasswordInput
                        id="password_confirmation"
                        name="password_confirmation"
                        autocomplete="new-password"
                        required
                    />
                </div>
            </template>

            <div v-else-if="!signedInAsInvitee" class="grid gap-2">
                <Label for="current_password"
                    >Your current password for {{ invitation.email }}</Label
                >
                <PasswordInput
                    id="current_password"
                    name="current_password"
                    autocomplete="current-password"
                    required
                    autofocus
                />
                <InputError :message="errors.current_password" />
            </div>

            <InputError :message="errors.members ?? errors.user ?? errors.role" />

            <Button
                type="submit"
                class="w-full"
                :disabled="processing"
                data-test="accept-invitation-button"
            >
                <Spinner v-if="processing" />
                Accept invitation
            </Button>
        </div>
    </Form>
</template>
