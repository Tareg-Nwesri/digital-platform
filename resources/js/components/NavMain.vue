<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import {
    SidebarGroup,
    SidebarGroupLabel,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { useCurrentUrl } from '@/composables/useCurrentUrl';
import { toUrl } from '@/lib/utils';
import type { NavGroup } from '@/types';

/**
 * The sidebar's sections, one per domain (Navigation.php decides the
 * groups and their order). Labels hide when the sidebar is collapsed.
 */
const props = defineProps<{
    groups: NavGroup[];
}>();

const { currentUrl } = useCurrentUrl();

function pathOf(href: NavGroup['items'][number]['href']): string {
    const url = toUrl(href);

    try {
        return new URL(url, window.location.origin).pathname.replace(
            /\/+$/,
            '',
        );
    } catch {
        return url;
    }
}

/**
 * The item for the current page: the one whose URL is the longest match of
 * the current path, so /admin/finance/transactions keeps "Finance" lit and
 * the dashboard (/admin) only lights up on itself.
 */
const activeHref = computed<string | null>(() => {
    const current = currentUrl.value.replace(/\/+$/, '');
    let best: string | null = null;

    for (const group of props.groups) {
        for (const item of group.items) {
            const path = pathOf(item.href);

            if (
                (current === path || current.startsWith(`${path}/`)) &&
                path.length > (best?.length ?? -1)
            ) {
                best = path;
            }
        }
    }

    return best;
});
</script>

<template>
    <SidebarGroup v-for="group in groups" :key="group.key" class="px-2 py-1">
        <SidebarGroupLabel>{{ group.label }}</SidebarGroupLabel>
        <SidebarMenu>
            <SidebarMenuItem v-for="item in group.items" :key="item.title">
                <SidebarMenuButton
                    as-child
                    :is-active="pathOf(item.href) === activeHref"
                    :tooltip="item.title"
                    class="relative data-[active=true]:font-semibold data-[active=true]:before:absolute data-[active=true]:before:inset-y-2 data-[active=true]:before:start-0 data-[active=true]:before:w-0.75 data-[active=true]:before:rounded-full data-[active=true]:before:bg-primary"
                >
                    <Link
                        :href="item.href"
                        :aria-current="
                            pathOf(item.href) === activeHref
                                ? 'page'
                                : undefined
                        "
                    >
                        <component :is="item.icon" />
                        <span>{{ item.title }}</span>
                    </Link>
                </SidebarMenuButton>
            </SidebarMenuItem>
        </SidebarMenu>
    </SidebarGroup>
</template>
