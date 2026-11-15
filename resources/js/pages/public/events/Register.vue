<script setup lang="ts">
import { Form, Link } from '@inertiajs/vue3';
import { Plus, Trash2 } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import BotGuard from '@/components/forms/BotGuard.vue';
import InputError from '@/components/InputError.vue';
import EventDocuments from '@/components/public/EventDocuments.vue';
import EventFacts from '@/components/public/EventFacts.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import PublicHead from '@/components/public/PublicHead.vue';
import type { PublicMetaTags } from '@/composables/usePublicSite';
import type { PublicEvent } from './Show.vue';

const props = defineProps<{ meta: PublicMetaTags; event: PublicEvent }>();

const categoryId = ref<number | null>(
    props.event.categories.length === 1 ? props.event.categories[0].id : null,
);
const category = computed(
    () => props.event.categories.find((c) => c.id === categoryId.value) ?? null,
);
const minMembers = computed(() => category.value?.membersMin ?? 1);
const maxMembers = computed(() => category.value?.membersMax ?? 10);

let nextKey = 0;
const members = ref<number[]>([]);

function fillTo(count: number): void {
    while (members.value.length < count) {
        members.value.push(nextKey++);
    }
}

fillTo(Math.max(1, minMembers.value));
watch(minMembers, (min) => fillTo(min));

function addMember(): void {
    if (members.value.length < maxMembers.value) {
        members.value.push(nextKey++);
    }
}

function removeMember(key: number): void {
    if (members.value.length > minMembers.value) {
        members.value = members.value.filter((k) => k !== key);
    }
}

const selectClass =
    'h-10 w-full rounded-md border border-input bg-background px-3 text-sm shadow-xs';
</script>

<template>
    <PublicHead :meta="meta" />

    <section class="section">
        <div class="public-shell mx-auto grid max-w-3xl gap-6">
            <img
                v-if="event.cover"
                :src="event.cover"
                alt=""
                class="aspect-[5/2] w-full rounded-xl border object-cover"
            />
            <header class="grid gap-3">
                <Link
                    :href="`/${$page.props.locale}/events/${event.slug}`"
                    class="text-sm text-muted-foreground hover:underline"
                    >{{ event.title }}</Link
                >
                <h1 class="text-2xl font-bold">
                    {{ $t('Register your team') }}
                </h1>
                <EventFacts :event="event" />
            </header>

            <EventDocuments :documents="event.documents" />

            <div
                v-if="!event.isOpen"
                class="rounded-xl border bg-card p-6 text-center"
                data-test="registration-closed"
            >
                <p class="font-medium">{{ $t('Registration is closed') }}</p>
                <p class="mt-1 text-sm text-muted-foreground">
                    {{
                        $t('Registration for this event is not open right now.')
                    }}
                </p>
            </div>

            <Form
                v-else
                :action="`/${$page.props.locale}/events/${event.slug}/register`"
                method="post"
                v-slot="{ errors, processing }"
                class="grid gap-6"
                data-test="registration-form"
            >
                <section class="grid gap-4 rounded-xl border bg-card p-5">
                    <h2 class="font-semibold">{{ $t('Team') }}</h2>
                    <div class="grid gap-2">
                        <Label for="category">{{ $t('Category') }}</Label>
                        <select
                            id="category"
                            v-model="categoryId"
                            name="category_id"
                            required
                            :class="selectClass"
                        >
                            <option :value="null" disabled>
                                {{ $t('Choose a category') }}
                            </option>
                            <option
                                v-for="option in event.categories"
                                :key="option.id"
                                :value="option.id"
                            >
                                {{ option.name }}
                            </option>
                        </select>
                        <p
                            v-if="category"
                            class="text-xs text-muted-foreground"
                        >
                            <template
                                v-if="
                                    category.ageMin !== null ||
                                    category.ageMax !== null
                                "
                                >{{
                                    $t('Ages :min–:max', {
                                        min: String(category.ageMin ?? 0),
                                        max: String(category.ageMax ?? 99),
                                    })
                                }}
                                ·
                            </template>
                            {{
                                $t(':min–:max members per team', {
                                    min: String(category.membersMin),
                                    max: String(category.membersMax),
                                })
                            }}
                        </p>
                        <InputError :message="errors.category_id" />
                    </div>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div class="grid gap-2">
                            <Label for="team-name">{{ $t('Team name') }}</Label>
                            <Input
                                id="team-name"
                                name="team_name"
                                maxlength="120"
                                required
                                autocomplete="organization"
                            />
                            <InputError :message="errors.team_name" />
                        </div>
                        <div class="grid gap-2">
                            <Label for="city">{{ $t('City') }}</Label>
                            <Input
                                id="city"
                                name="city"
                                maxlength="120"
                                required
                                autocomplete="address-level2"
                            />
                            <InputError :message="errors.city" />
                        </div>
                    </div>
                    <div class="grid gap-2">
                        <Label for="robot-name"
                            >{{ $t('Robot name') }}
                            <span class="text-muted-foreground">{{
                                $t('(optional)')
                            }}</span></Label
                        >
                        <Input
                            id="robot-name"
                            name="robot_name"
                            maxlength="120"
                        />
                        <InputError :message="errors.robot_name" />
                    </div>
                </section>

                <section class="grid gap-4 rounded-xl border bg-card p-5">
                    <div>
                        <h2 class="font-semibold">
                            {{ $t('Coach or contact person') }}
                        </h2>
                        <p class="text-sm text-muted-foreground">
                            {{
                                $t(
                                    'We contact this person about the registration.',
                                )
                            }}
                        </p>
                    </div>
                    <div class="grid gap-2">
                        <Label for="contact-name">{{ $t('Full name') }}</Label>
                        <Input
                            id="contact-name"
                            name="contact_name"
                            maxlength="120"
                            required
                            autocomplete="name"
                        />
                        <InputError :message="errors.contact_name" />
                    </div>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div class="grid gap-2">
                            <Label for="contact-phone">{{ $t('Phone') }}</Label>
                            <Input
                                id="contact-phone"
                                name="contact_phone"
                                type="tel"
                                dir="ltr"
                                maxlength="32"
                                required
                                autocomplete="tel"
                                placeholder="+218 9X XXX XXXX"
                            />
                            <InputError :message="errors.contact_phone" />
                        </div>
                        <div class="grid gap-2">
                            <Label for="contact-email">{{
                                $t('Email address')
                            }}</Label>
                            <Input
                                id="contact-email"
                                name="contact_email"
                                type="email"
                                dir="ltr"
                                maxlength="190"
                                required
                                autocomplete="email"
                            />
                            <InputError :message="errors.contact_email" />
                        </div>
                    </div>
                </section>

                <section class="grid gap-4 rounded-xl border bg-card p-5">
                    <div
                        class="flex flex-wrap items-center justify-between gap-2"
                    >
                        <h2 class="font-semibold">{{ $t('Team members') }}</h2>
                        <span class="text-sm text-muted-foreground">{{
                            $t(':min–:max members per team', {
                                min: String(minMembers),
                                max: String(maxMembers),
                            })
                        }}</span>
                    </div>
                    <InputError :message="errors.members" />

                    <fieldset
                        v-for="(key, index) in members"
                        :key="key"
                        class="grid gap-3 rounded-lg border p-3 sm:grid-cols-[1fr_12rem_auto] sm:items-end"
                        :data-test="`member-${index}`"
                    >
                        <legend class="sr-only">
                            {{
                                $t('Member :number', {
                                    number: String(index + 1),
                                })
                            }}
                        </legend>
                        <div class="grid gap-2">
                            <Label :for="`member-${key}-name`">{{
                                $t('Member :number', {
                                    number: String(index + 1),
                                })
                            }}</Label>
                            <Input
                                :id="`member-${key}-name`"
                                :name="`members[${index}][name]`"
                                maxlength="120"
                                required
                                :placeholder="$t('Full name')"
                            />
                            <InputError
                                :message="errors[`members.${index}.name`]"
                            />
                        </div>
                        <div class="grid gap-2">
                            <Label :for="`member-${key}-birthdate`">{{
                                $t('Birthdate')
                            }}</Label>
                            <Input
                                :id="`member-${key}-birthdate`"
                                :name="`members[${index}][birthdate]`"
                                type="date"
                                required
                            />
                            <InputError
                                :message="errors[`members.${index}.birthdate`]"
                            />
                        </div>
                        <Button
                            type="button"
                            variant="ghost"
                            size="icon"
                            :disabled="members.length <= minMembers"
                            :aria-label="$t('Remove member')"
                            @click="removeMember(key)"
                        >
                            <Trash2 class="size-4" />
                        </Button>
                    </fieldset>

                    <div>
                        <Button
                            type="button"
                            variant="outline"
                            :disabled="members.length >= maxMembers"
                            data-test="add-member"
                            @click="addMember"
                        >
                            <Plus class="size-4" />
                            {{ $t('Add member') }}
                        </Button>
                    </div>
                </section>

                <InputError :message="errors.registration" />
                <BotGuard :errors="errors" />

                <Button
                    type="submit"
                    size="lg"
                    :disabled="processing"
                    data-test="submit-registration"
                >
                    <Spinner v-if="processing" />
                    {{ $t('Send registration') }}
                </Button>
            </Form>
        </div>
    </section>
</template>
