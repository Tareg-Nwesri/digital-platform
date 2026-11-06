<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { ChevronsUpDown, Check } from '@lucide/vue';
import { computed } from 'vue';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import {
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { hub } from '@/routes';
import { dashboard as departmentDashboard } from '@/routes/console/departments';
import { dashboard as teamDashboard } from '@/routes/workspace';

const page = usePage();
const current = computed(() => page.props.workspace);
const workspaces = computed(() => page.props.auth.workspaces ?? []);

function href(workspace: { type: string; slug: string }) {
    return workspace.type === 'team'
        ? teamDashboard(workspace.slug)
        : departmentDashboard(workspace.slug);
}
</script>

<template>
    <SidebarMenu v-if="workspaces.length">
        <SidebarMenuItem>
            <DropdownMenu>
                <DropdownMenuTrigger as-child>
                    <SidebarMenuButton
                        size="lg"
                        class="border bg-sidebar-accent/40"
                        data-test="workspace-switcher"
                    >
                        <div class="grid flex-1 text-start text-sm leading-tight">
                            <span class="truncate text-xs text-muted-foreground">
                                {{ current?.type === 'department' ? 'Department' : current ? 'Team' : 'Workspace' }}
                            </span>
                            <span class="truncate font-semibold">
                                {{ current?.name ?? 'Choose a workspace' }}
                            </span>
                        </div>
                        <ChevronsUpDown class="ms-auto size-4" />
                    </SidebarMenuButton>
                </DropdownMenuTrigger>
                <DropdownMenuContent class="w-64" align="start">
                    <DropdownMenuLabel class="text-xs text-muted-foreground">
                        Switch workspace
                    </DropdownMenuLabel>
                    <DropdownMenuItem
                        v-for="workspace in workspaces"
                        :key="`${workspace.type}-${workspace.slug}`"
                        :as-child="true"
                    >
                        <Link :href="href(workspace)" class="flex w-full items-center gap-2">
                            <span class="truncate">{{ workspace.name }}</span>
                            <span class="ms-auto text-xs text-muted-foreground capitalize">{{ workspace.type }}</span>
                            <Check
                                v-if="current?.type === workspace.type && current?.slug === workspace.slug"
                                class="size-4"
                            />
                        </Link>
                    </DropdownMenuItem>
                    <DropdownMenuSeparator />
                    <DropdownMenuItem :as-child="true">
                        <Link :href="hub()" class="w-full">All workspaces</Link>
                    </DropdownMenuItem>
                </DropdownMenuContent>
            </DropdownMenu>
        </SidebarMenuItem>
    </SidebarMenu>
</template>
