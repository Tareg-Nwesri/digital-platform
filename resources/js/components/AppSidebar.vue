<script setup lang="ts">
import { useDirection } from '@/composables/useDirection';
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import WorkspaceSwitcher from '@/components/app/WorkspaceSwitcher.vue';
import AppLogo from '@/components/AppLogo.vue';
import NavMain from '@/components/NavMain.vue';
import MadeBy from '@/components/MadeBy.vue';
import NavUser from '@/components/NavUser.vue';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { navIcon } from '@/lib/navIcons';
import { dashboard } from '@/routes';
import type { NavGroup } from '@/types';

const page = usePage();

/**
 * The sidebar comes from the server (Navigation.php): only routes the viewer may use.
 */
const navGroups = computed<NavGroup[]>(() => {
    const groups: NavGroup[] = [];

    for (const item of page.props.nav ?? []) {
        let group = groups.find((existing) => existing.key === item.group);

        if (!group) {
            group = { key: item.group, label: item.groupLabel, items: [] };
            groups.push(group);
        }

        group.items.push({
            title: item.label,
            href: item.href,
            icon: navIcon(item.icon),
        });
    }

    return groups;
});

const { start } = useDirection();
</script>

<template>
    <Sidebar collapsible="icon" variant="inset" :side="start">
        <SidebarHeader>
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton size="lg" as-child>
                        <Link :href="dashboard()">
                            <AppLogo />
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
            <WorkspaceSwitcher />
        </SidebarHeader>

        <SidebarContent data-tour="app-sidebar">
            <NavMain :groups="navGroups" />
        </SidebarContent>

        <SidebarFooter>
            <NavUser />
            <div class="px-2 pb-1 group-data-[collapsible=icon]:hidden">
                <MadeBy />
            </div>
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
