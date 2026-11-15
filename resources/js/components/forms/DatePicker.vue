<script setup lang="ts">
import type { CalendarDate, DateValue } from '@internationalized/date';
import { getLocalTimeZone, parseDate, today } from '@internationalized/date';
import { CalendarDays, ChevronLeft, ChevronRight, X } from '@lucide/vue';
import {
    CalendarCell,
    CalendarCellTrigger,
    CalendarGrid,
    CalendarGridBody,
    CalendarGridHead,
    CalendarGridRow,
    CalendarHeadCell,
    CalendarRoot,
    PopoverAnchor,
    PopoverContent,
    PopoverPortal,
    PopoverRoot,
} from 'reka-ui';
import { computed, ref, shallowRef, watch } from 'vue';
import FormOption from '@/components/forms/FormOption.vue';
import FormSelect from '@/components/forms/FormSelect.vue';
import { currentLocale, formatDate } from '@/lib/i18n';
import { cn } from '@/lib/utils';

/**
 * The app's date (and date-time) picker. It keeps the API of a native
 * <input type="date"> / "datetime-local": v-model holds 'YYYY-MM-DD' or
 * 'YYYY-MM-DDTHH:mm' (or '' / null when empty), and `name` posts that
 * string from native forms. Weeks start on Saturday, digits are Western,
 * and the month and year can be picked directly (birthdates).
 */
const props = withDefaults(
    defineProps<{
        modelValue?: string | null;
        withTime?: boolean;
        id?: string;
        name?: string;
        required?: boolean;
        disabled?: boolean;
        min?: string | null;
        max?: string | null;
        /** Refuse dates after today (birthdates, payments made). */
        noFuture?: boolean;
        placeholder?: string;
        invalid?: boolean;
        clearable?: boolean;
        class?: string;
    }>(),
    { modelValue: undefined, clearable: true },
);

const emit = defineEmits<{
    'update:modelValue': [value: string];
    change: [value: string];
}>();

const local = ref<string>(props.modelValue ?? '');
watch(
    () => props.modelValue,
    (value) => {
        if (value !== undefined) {
            local.value = value ?? '';
        }
    },
);

const open = ref(false);
const locale = computed(() => `${currentLocale()}-u-nu-latn`);
const dir = computed(() => (currentLocale() === 'ar' ? 'rtl' : 'ltr'));

function parse(value: string | null | undefined): CalendarDate | undefined {
    if (!value) {
        return undefined;
    }

    try {
        return parseDate(value.slice(0, 10));
    } catch {
        return undefined;
    }
}

const date = computed(() => parse(local.value));
const time = computed(() =>
    local.value.length >= 16 ? local.value.slice(11, 16) : '09:00',
);
const minValue = computed(() => parse(props.min));
const maxValue = computed(
    () =>
        parse(props.max) ??
        (props.noFuture ? today(getLocalTimeZone()) : undefined),
);

const view = shallowRef<DateValue>(date.value ?? today(getLocalTimeZone()));
watch(open, (isOpen) => {
    if (isOpen) {
        view.value = date.value ?? clampToRange(today(getLocalTimeZone()));
    }
});

function clampToRange(value: CalendarDate): CalendarDate {
    if (maxValue.value && value.compare(maxValue.value) > 0) {
        return maxValue.value;
    }

    if (minValue.value && value.compare(minValue.value) < 0) {
        return minValue.value;
    }

    return value;
}

function commit(value: string): void {
    local.value = value;
    emit('update:modelValue', value);
    emit('change', value);
}

function pick(value: DateValue | undefined): void {
    if (!value) {
        return;
    }

    const day = value.toString().slice(0, 10);
    commit(props.withTime ? `${day}T${time.value}` : day);

    if (!props.withTime) {
        open.value = false;
    }
}

function pickToday(): void {
    pick(clampToRange(today(getLocalTimeZone())));
}

function setTime(part: 'hour' | 'minute', value: unknown): void {
    if (!date.value) {
        return;
    }

    const [hour, minute] = time.value.split(':');
    const next =
        part === 'hour'
            ? `${String(value).padStart(2, '0')}:${minute}`
            : `${hour}:${String(value).padStart(2, '0')}`;
    commit(`${date.value.toString()}T${next}`);
}

const label = computed(() => {
    if (!date.value) {
        return '';
    }

    const options: Intl.DateTimeFormatOptions = props.withTime
        ? {
              day: 'numeric',
              month: 'long',
              year: 'numeric',
              hour: '2-digit',
              minute: '2-digit',
          }
        : { day: 'numeric', month: 'long', year: 'numeric' };

    return formatDate(
        props.withTime
            ? `${date.value.toString()}T${time.value}`
            : `${date.value.toString()}T00:00`,
        options,
    );
});

const months = computed(() =>
    Array.from({ length: 12 }, (_, index) => ({
        value: index + 1,
        label: formatDate(new Date(2026, index, 1), { month: 'long' }),
    })),
);

const years = computed(() => {
    const now = today(getLocalTimeZone()).year;
    const first = minValue.value?.year ?? 1940;
    const last = maxValue.value?.year ?? now + 10;
    const list: number[] = [];

    for (let year = last; year >= first; year--) {
        list.push(year);
    }

    if (!list.includes(view.value.year)) {
        list.push(view.value.year);
    }

    return list;
});

const hours = Array.from({ length: 24 }, (_, hour) => hour);
const minutes = computed(() => {
    const steps = Array.from({ length: 12 }, (_, index) => index * 5);
    const current = Number(time.value.slice(3, 5));

    return steps.includes(current)
        ? steps
        : [...steps, current].sort((a, b) => a - b);
});

const pad = (value: number) => String(value).padStart(2, '0');
</script>

<template>
    <PopoverRoot v-model:open="open">
        <PopoverAnchor as-child>
            <div class="relative">
                <button
                    :id="id"
                    type="button"
                    :disabled="disabled"
                    :aria-invalid="invalid ? 'true' : undefined"
                    aria-haspopup="dialog"
                    :aria-expanded="open ? 'true' : 'false'"
                    :data-state="open ? 'open' : 'closed'"
                    @click="open = !open"
                    :class="
                        cn(
                            'flex h-9 w-full min-w-36 items-center gap-2 rounded-md border border-input bg-transparent px-3 text-start text-sm shadow-xs transition-[color,box-shadow] outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 disabled:cursor-not-allowed disabled:opacity-50 aria-invalid:border-destructive data-[state=open]:border-ring',
                            clearable && local ? 'pe-8' : '',
                            props.class,
                        )
                    "
                >
                    <CalendarDays
                        class="size-4 shrink-0 text-muted-foreground"
                    />
                    <span v-if="label" class="truncate tabular-nums">{{
                        label
                    }}</span>
                    <span v-else class="truncate text-muted-foreground">{{
                        placeholder ??
                        (withTime
                            ? $t('Pick a date and time')
                            : $t('Pick a date'))
                    }}</span>
                </button>
                <button
                    v-if="clearable && local && !disabled"
                    type="button"
                    class="absolute end-2 top-1/2 -translate-y-1/2 rounded-sm p-0.5 text-muted-foreground hover:text-foreground"
                    :aria-label="$t('Clear')"
                    @click="commit('')"
                >
                    <X class="size-3.5" />
                </button>
                <input v-if="name" type="hidden" :name="name" :value="local" />
                <input
                    v-if="required"
                    class="pointer-events-none absolute inset-0 opacity-0"
                    tabindex="-1"
                    aria-hidden="true"
                    :value="local"
                    required
                />
            </div>
        </PopoverAnchor>

        <PopoverPortal>
            <PopoverContent
                align="start"
                :side-offset="6"
                class="z-50 w-auto rounded-xl border bg-popover p-3 text-popover-foreground shadow-lg outline-none data-[state=open]:animate-in data-[state=open]:fade-in-0 data-[state=open]:zoom-in-95"
            >
                <CalendarRoot
                    v-slot="{ grid, weekDays }"
                    :model-value="date"
                    :placeholder="view"
                    :locale="locale"
                    :dir="dir"
                    :week-starts-on="6"
                    :weekday-format="dir === 'rtl' ? 'narrow' : 'short'"
                    fixed-weeks
                    :min-value="minValue"
                    :max-value="maxValue"
                    prevent-deselect
                    @update:placeholder="(value: DateValue) => (view = value)"
                    @update:model-value="
                        (value) => pick(value as DateValue | undefined)
                    "
                >
                    <div class="mb-3 flex items-center gap-2">
                        <button
                            type="button"
                            class="inline-flex size-8 items-center justify-center rounded-md border hover:bg-accent"
                            :aria-label="$t('Previous month')"
                            @click="view = view.subtract({ months: 1 })"
                        >
                            <ChevronLeft class="size-4 rtl:rotate-180" />
                        </button>
                        <FormSelect
                            :model-value="view.month"
                            class="h-8 min-w-0 flex-1"
                            :aria-label="$t('Month')"
                            @update:model-value="
                                (value) =>
                                    (view = view.set({ month: Number(value) }))
                            "
                        >
                            <FormOption
                                v-for="month in months"
                                :key="month.value"
                                :value="month.value"
                                >{{ month.label }}</FormOption
                            >
                        </FormSelect>
                        <FormSelect
                            :model-value="view.year"
                            class="h-8 w-24 min-w-0"
                            :aria-label="$t('Year')"
                            @update:model-value="
                                (value) =>
                                    (view = view.set({ year: Number(value) }))
                            "
                        >
                            <FormOption
                                v-for="year in years"
                                :key="year"
                                :value="year"
                                >{{ year }}</FormOption
                            >
                        </FormSelect>
                        <button
                            type="button"
                            class="inline-flex size-8 items-center justify-center rounded-md border hover:bg-accent"
                            :aria-label="$t('Next month')"
                            @click="view = view.add({ months: 1 })"
                        >
                            <ChevronRight class="size-4 rtl:rotate-180" />
                        </button>
                    </div>

                    <CalendarGrid
                        v-for="month in grid"
                        :key="month.value.toString()"
                        class="w-full border-collapse"
                    >
                        <CalendarGridHead>
                            <CalendarGridRow class="grid grid-cols-7">
                                <CalendarHeadCell
                                    v-for="day in weekDays"
                                    :key="day"
                                    class="pb-1 text-center text-[11px] font-medium text-muted-foreground"
                                    >{{ day }}</CalendarHeadCell
                                >
                            </CalendarGridRow>
                        </CalendarGridHead>
                        <CalendarGridBody>
                            <CalendarGridRow
                                v-for="(week, index) in month.rows"
                                :key="index"
                                class="grid grid-cols-7"
                            >
                                <CalendarCell
                                    v-for="day in week"
                                    :key="day.toString()"
                                    :date="day"
                                    class="p-0.5"
                                >
                                    <CalendarCellTrigger
                                        :day="day"
                                        :month="month.value"
                                        class="inline-flex size-9 items-center justify-center rounded-lg text-sm tabular-nums outline-none hover:bg-accent focus-visible:ring-2 focus-visible:ring-ring data-[disabled]:pointer-events-none data-[disabled]:opacity-30 data-[outside-view]:text-muted-foreground/50 data-[selected]:bg-primary data-[selected]:font-semibold data-[selected]:text-primary-foreground data-[today]:not-[[data-selected]]:font-semibold data-[today]:not-[[data-selected]]:text-primary data-[today]:not-[[data-selected]]:ring-1 data-[today]:not-[[data-selected]]:ring-primary/40 data-[unavailable]:line-through"
                                        >{{ day.day }}</CalendarCellTrigger
                                    >
                                </CalendarCell>
                            </CalendarGridRow>
                        </CalendarGridBody>
                    </CalendarGrid>
                </CalendarRoot>

                <div
                    v-if="withTime"
                    class="mt-3 flex items-center gap-2 border-t pt-3"
                >
                    <span class="text-sm text-muted-foreground">{{
                        $t('Time')
                    }}</span>
                    <div class="flex items-center gap-1" dir="ltr">
                        <FormSelect
                            :model-value="Number(time.slice(0, 2))"
                            :disabled="!date"
                            class="h-8 w-20 min-w-0"
                            :aria-label="$t('Hour')"
                            @update:model-value="
                                (value) => setTime('hour', value)
                            "
                        >
                            <FormOption
                                v-for="hour in hours"
                                :key="hour"
                                :value="hour"
                                >{{ pad(hour) }}</FormOption
                            >
                        </FormSelect>
                        <span class="text-muted-foreground">:</span>
                        <FormSelect
                            :model-value="Number(time.slice(3, 5))"
                            :disabled="!date"
                            class="h-8 w-20 min-w-0"
                            :aria-label="$t('Minute')"
                            @update:model-value="
                                (value) => setTime('minute', value)
                            "
                        >
                            <FormOption
                                v-for="minute in minutes"
                                :key="minute"
                                :value="minute"
                                >{{ pad(minute) }}</FormOption
                            >
                        </FormSelect>
                    </div>
                </div>

                <div
                    class="mt-3 flex items-center justify-between gap-2 border-t pt-3"
                >
                    <button
                        type="button"
                        class="text-sm font-medium text-primary hover:underline"
                        @click="pickToday"
                    >
                        {{ $t('Today') }}
                    </button>
                    <div class="flex items-center gap-3">
                        <button
                            v-if="clearable && local"
                            type="button"
                            class="text-sm text-muted-foreground hover:text-foreground"
                            @click="commit('')"
                        >
                            {{ $t('Clear') }}
                        </button>
                        <button
                            v-if="withTime"
                            type="button"
                            class="rounded-md bg-primary px-3 py-1 text-sm font-medium text-primary-foreground hover:bg-primary/90"
                            @click="open = false"
                        >
                            {{ $t('Done') }}
                        </button>
                    </div>
                </div>
            </PopoverContent>
        </PopoverPortal>
    </PopoverRoot>
</template>
