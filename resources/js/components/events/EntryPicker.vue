<script setup lang="ts">
import { computed } from 'vue';
import InputError from '@/components/InputError.vue';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';
import { formatNumber } from '@/lib/i18n';

export type PickerCategory = {
    id: number;
    name: string;
    ageMin: number | null;
    ageMax: number | null;
    membersMin: number;
    membersMax: number;
    robotsPerTeam: number;
    maxWeightG: number | null;
    maxLengthMm: number | null;
    maxWidthMm: number | null;
    maxHeightMm: number | null;
};

export type PickerMember = {
    id: number;
    name: string;
    age: number | null;
    role: string | null;
};

export type PickerRobot = {
    id: number;
    name: string;
    status: string;
    weightG: number | null;
    lengthMm: number | null;
    widthMm: number | null;
    heightMm: number | null;
};

/**
 * Members and robots a team brings to one category, with hints for the
 * category's age, member, robot and size rules. The server decides.
 */
const props = defineProps<{
    category: PickerCategory | null;
    members: PickerMember[];
    robots: PickerRobot[];
    errors: Record<string, string | undefined>;
}>();

const memberIds = defineModel<number[]>('memberIds', { required: true });
const robotIds = defineModel<number[]>('robotIds', { required: true });

function toggle(list: number[], id: number, on: boolean): number[] {
    return on ? [...new Set([...list, id])] : list.filter((x) => x !== id);
}

function ageOutside(member: PickerMember): boolean {
    const category = props.category;

    if (!category || member.age === null) {
        return false;
    }

    return (
        (category.ageMin !== null && member.age < category.ageMin) ||
        (category.ageMax !== null && member.age > category.ageMax)
    );
}

function robotProblem(robot: PickerRobot): string | null {
    const category = props.category;

    if (!category) {
        return null;
    }

    const limits: [number | null, number | null][] = [
        [category.maxWeightG, robot.weightG],
        [category.maxLengthMm, robot.lengthMm],
        [category.maxWidthMm, robot.widthMm],
        [category.maxHeightMm, robot.heightMm],
    ];

    if (limits.some(([limit, value]) => limit !== null && value === null)) {
        return 'Measurements missing';
    }

    if (
        limits.some(
            ([limit, value]) =>
                limit !== null && value !== null && value > limit,
        )
    ) {
        return 'Over the limits';
    }

    return null;
}

const limitsText = computed(() => {
    const category = props.category;

    if (!category) {
        return null;
    }

    const parts: string[] = [];

    if (category.maxWeightG !== null) {
        parts.push(`≤ ${formatNumber(category.maxWeightG)} g`);
    }

    const size = [
        category.maxLengthMm,
        category.maxWidthMm,
        category.maxHeightMm,
    ];

    if (size.some((value) => value !== null)) {
        parts.push(
            size
                .map((value) => (value === null ? '—' : formatNumber(value)))
                .join(' × ') + ' mm',
        );
    }

    return parts.length ? parts.join(' · ') : null;
});
</script>

<template>
    <div class="grid gap-5">
        <fieldset class="grid gap-2">
            <legend
                class="mb-1 flex flex-wrap items-baseline gap-2 text-sm font-medium"
            >
                {{ $t('Team members') }}
                <span
                    v-if="category"
                    class="text-xs font-normal text-muted-foreground"
                    >{{
                        $t(':min–:max members per team', {
                            min: String(category.membersMin),
                            max: String(category.membersMax),
                        })
                    }}</span
                >
            </legend>
            <Label
                v-for="member in members"
                :key="member.id"
                class="flex items-center gap-3 rounded-lg border p-2 font-normal"
                :data-test="`pick-member-${member.id}`"
            >
                <Checkbox
                    :model-value="memberIds.includes(member.id)"
                    @update:model-value="
                        (on) =>
                            (memberIds = toggle(
                                memberIds,
                                member.id,
                                on === true,
                            ))
                    "
                />
                <span class="flex-1">{{ member.name }}</span>
                <span
                    class="text-xs tabular-nums"
                    :class="
                        ageOutside(member)
                            ? 'text-destructive'
                            : 'text-muted-foreground'
                    "
                    >{{
                        member.age === null
                            ? $t('No birthdate')
                            : $t(':age years', {
                                  age: formatNumber(member.age),
                              })
                    }}</span
                >
            </Label>
            <InputError :message="errors.member_ids" />
        </fieldset>

        <fieldset
            v-if="!category || category.robotsPerTeam > 0"
            class="grid gap-2"
        >
            <legend
                class="mb-1 flex flex-wrap items-baseline gap-2 text-sm font-medium"
            >
                {{ $t('Robots') }}
                <span
                    v-if="category"
                    class="text-xs font-normal text-muted-foreground"
                >
                    {{
                        $t('Up to :count', {
                            count: formatNumber(category.robotsPerTeam),
                        })
                    }}<template v-if="limitsText"> · {{ limitsText }}</template>
                </span>
            </legend>
            <p v-if="robots.length === 0" class="text-sm text-muted-foreground">
                {{ $t('Add a robot to your team first.') }}
            </p>
            <Label
                v-for="robot in robots"
                :key="robot.id"
                class="flex items-center gap-3 rounded-lg border p-2 font-normal"
                :data-test="`pick-robot-${robot.id}`"
            >
                <Checkbox
                    :model-value="robotIds.includes(robot.id)"
                    @update:model-value="
                        (on) =>
                            (robotIds = toggle(robotIds, robot.id, on === true))
                    "
                />
                <span class="flex-1">{{ robot.name }}</span>
                <span
                    v-if="robotProblem(robot)"
                    class="text-xs text-destructive"
                    >{{ $t(robotProblem(robot) ?? '') }}</span
                >
            </Label>
            <InputError :message="errors.robot_ids" />
        </fieldset>
    </div>
</template>
