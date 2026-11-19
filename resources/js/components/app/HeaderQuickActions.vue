<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { Moon, Sun } from '@lucide/vue';
import { computed } from 'vue';
import { Button } from '@/components/ui/button';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { useAppearance } from '@/composables/useAppearance';
import { update as switchLocale } from '@/routes/locale';

/**
 * The top bar's one-click switches: interface language (shown as the target
 * language's own short name) and light or dark theme.
 */
const page = usePage();
const target = computed(() => (page.props.locale === 'ar' ? 'en' : 'ar'));

const { resolvedAppearance, updateAppearance } = useAppearance();
const isDark = computed(() => resolvedAppearance.value === 'dark');

function toggleTheme(): void {
    updateAppearance(isDark.value ? 'light' : 'dark');
}
</script>

<template>
    <div class="flex items-center gap-1">
        <Tooltip>
            <TooltipTrigger as-child>
                <Button
                    variant="ghost"
                    size="sm"
                    class="h-8 min-w-8 px-2 font-semibold"
                    as-child
                >
                    <Link
                        :href="switchLocale(target)"
                        method="post"
                        as="button"
                        :lang="target"
                        :aria-label="
                            target === 'ar'
                                ? $t('Switch to Arabic')
                                : $t('Switch to English')
                        "
                        data-test="header-locale-switch"
                    >
                        <span v-if="target === 'ar'" class="font-arabic"
                            >ع</span
                        >
                        <span v-else>EN</span>
                    </Link>
                </Button>
            </TooltipTrigger>
            <TooltipContent>
                {{
                    target === 'ar'
                        ? $t('Switch to Arabic')
                        : $t('Switch to English')
                }}
            </TooltipContent>
        </Tooltip>

        <Tooltip>
            <TooltipTrigger as-child>
                <Button
                    variant="ghost"
                    size="icon"
                    class="size-8"
                    :aria-label="
                        isDark ? $t('Use light theme') : $t('Use dark theme')
                    "
                    data-test="header-theme-toggle"
                    @click="toggleTheme"
                >
                    <Sun v-if="isDark" class="size-4" />
                    <Moon v-else class="size-4" />
                </Button>
            </TooltipTrigger>
            <TooltipContent>
                {{ isDark ? $t('Use light theme') : $t('Use dark theme') }}
            </TooltipContent>
        </Tooltip>
    </div>
</template>
