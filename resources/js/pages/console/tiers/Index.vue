<script setup lang="ts">
import { Head, router, usePage } from '@inertiajs/vue3';
import { Check, Pencil, Plus, Star, Trash2, X } from '@lucide/vue';
import { computed, ref } from 'vue';
import TierFormDialog from '@/components/app/TierFormDialog.vue';
import type {
    FeatureDefinition,
    TierFormValue,
} from '@/components/app/TierFormDialog.vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardFooter,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { formatNumber } from '@/lib/i18n';
import { dashboard } from '@/routes/console';
import {
    defaultMethod as defaultRoute,
    destroy,
    index,
} from '@/routes/console/tiers';

type TierCard = TierFormValue & {
    id: number;
    name: string;
    isDefault: boolean;
    teamsCount: number;
    features: {
        name: string;
        type: 'number' | 'boolean';
        value: boolean | number;
    }[];
};

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Organisation console', href: dashboard() },
            { title: 'Tiers', href: index() },
        ],
    },
});

defineProps<{
    tiers: TierCard[];
    featureNames: FeatureDefinition[];
}>();

const page = usePage();
const tierError = computed(
    () => (page.props.errors as Record<string, string | undefined>).tier,
);

const formOpen = ref(false);
const editing = ref<TierCard | null>(null);
const deleting = ref<TierCard | null>(null);

function openCreate(): void {
    editing.value = null;
    formOpen.value = true;
}

function openEdit(tier: TierCard): void {
    editing.value = tier;
    formOpen.value = true;
}

function makeDefault(tier: TierCard): void {
    router.patch(defaultRoute(tier.slug).url, {}, { preserveScroll: true });
}

function confirmDelete(): void {
    if (!deleting.value) {
        return;
    }

    router.delete(destroy(deleting.value.slug).url, {
        preserveScroll: true,
        onFinish: () => (deleting.value = null),
    });
}
</script>

<template>
    <Head :title="$t('Tiers')" />

    <div class="flex flex-col gap-6 p-4 md:p-6">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <Heading
                :title="$t('Tiers')"
                :description="
                    $t(
                        'A tier is a package of features. Every team is on one tier; changes apply to its teams straight away.',
                    )
                "
            />
            <Button
                data-test="new-tier"
                data-tour="tiers-new"
                @click="openCreate"
            >
                <Plus class="size-4" />
                {{ $t('New tier') }}
            </Button>
        </div>

        <InputError :message="tierError" />

        <div
            class="grid gap-4 md:grid-cols-2 xl:grid-cols-3"
            data-tour="tiers-list"
        >
            <Card
                v-for="tier in tiers"
                :key="tier.id"
                :data-test="`tier-${tier.slug}`"
                class="flex flex-col"
            >
                <CardHeader class="gap-2">
                    <div class="flex items-center justify-between gap-2">
                        <CardTitle>{{ tier.name }}</CardTitle>
                        <Badge v-if="tier.isDefault" variant="secondary">
                            <Star class="size-3" />
                            {{ $t('Default') }}
                        </Badge>
                    </div>
                    <p class="text-sm text-muted-foreground">
                        {{
                            $t(':count teams', {
                                count: formatNumber(tier.teamsCount),
                            })
                        }}
                    </p>
                </CardHeader>
                <CardContent class="flex-1">
                    <ul class="grid gap-1.5 text-sm">
                        <li
                            v-for="feature in tier.features"
                            :key="feature.name"
                            class="flex items-center justify-between gap-3"
                        >
                            <span
                                :class="
                                    feature.type === 'boolean' &&
                                    !feature.value &&
                                    'text-muted-foreground'
                                "
                            >
                                {{ $t(`feature.${feature.name}`) }}
                            </span>
                            <span
                                v-if="feature.type === 'number'"
                                class="font-medium tabular-nums"
                            >
                                {{ formatNumber(Number(feature.value)) }}
                            </span>
                            <Check
                                v-else-if="feature.value"
                                class="size-4 text-primary"
                                :aria-label="$t('On')"
                            />
                            <X
                                v-else
                                class="size-4 text-muted-foreground"
                                :aria-label="$t('Off')"
                            />
                        </li>
                    </ul>
                </CardContent>
                <CardFooter class="flex flex-wrap gap-2">
                    <Button
                        size="sm"
                        variant="outline"
                        :data-test="`edit-${tier.slug}`"
                        @click="openEdit(tier)"
                    >
                        <Pencil class="size-4" />
                        {{ $t('Edit') }}
                    </Button>
                    <Button
                        v-if="!tier.isDefault"
                        size="sm"
                        variant="ghost"
                        @click="makeDefault(tier)"
                    >
                        <Star class="size-4" />
                        {{ $t('Make default') }}
                    </Button>
                    <Button
                        v-if="!tier.isDefault && tier.teamsCount === 0"
                        size="sm"
                        variant="ghost"
                        class="ms-auto text-destructive"
                        :data-test="`delete-${tier.slug}`"
                        @click="deleting = tier"
                    >
                        <Trash2 class="size-4" />
                        {{ $t('Delete') }}
                    </Button>
                </CardFooter>
            </Card>
        </div>

        <TierFormDialog
            v-model:open="formOpen"
            :definitions="featureNames"
            :tier="editing"
        />

        <Dialog
            :open="deleting !== null"
            @update:open="(open) => !open && (deleting = null)"
        >
            <DialogContent class="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>
                        {{
                            $t('Delete the :name tier?', {
                                name: deleting?.name ?? '',
                            })
                        }}
                    </DialogTitle>
                    <DialogDescription>
                        {{ $t('No teams use it. This cannot be undone.') }}
                    </DialogDescription>
                </DialogHeader>
                <DialogFooter>
                    <Button variant="outline" @click="deleting = null">
                        {{ $t('Cancel') }}
                    </Button>
                    <Button
                        variant="destructive"
                        data-test="confirm-delete-tier"
                        @click="confirmDelete"
                    >
                        {{ $t('Delete') }}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </div>
</template>
