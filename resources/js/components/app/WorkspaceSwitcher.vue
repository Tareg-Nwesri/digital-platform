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
import { show as departmentDashboard } from '@/routes/console/departments';
import { dashboard as teamDashboard } from '@/routes/workspace';

const page = usePage();
const current = computed(() => page.props.workspace);
const workspaces = computed(() => page.props.auth.workspaces ?? []);

/** Organisation-wide console pages (/admin/...) have no workspace of their own. */
const inConsole = computed(() => page.url.startsWith('/admin'));

function href(workspace: { type: string; slug: string }) {
    return workspace.type === 'team'
        ? teamDashboard(workspace.slug)
        : departmentDashboard(workspace.slug);
}
</script>

<template>
    <SidebarMenu v-if="workspaces.length" data-tour="workspace-switcher">
        <SidebarMenuItem>
            <DropdownMenu>
                <DropdownMenuTrigger as-child>
                    <SidebarMenuButton
                        size="lg"
                        class="border bg-sidebar-accent/40"
                        data-test="workspace-switcher"
                    >
                        <div
                            class="grid flex-1 text-start text-sm leading-tight"
                        >
                            <span
                                class="truncate text-xs text-muted-foreground"
                            >
                                {{
                                    current?.type === 'department'
                                        ? $t('Department')
                                        : current
                                          ? $t('Team')
                                          : $t('Workspace')
                                }}
                            </span>
                            <span class="truncate font-semibold">
                                {{
                                    current?.name ??
                                    (inConsole
                                        ? $t('Organisation console')
                                        : $t('Choose a workspace'))
                                }}
                            </span>
                        </div>
                        <ChevronsUpDown class="ms-auto size-4" />
                    </SidebarMenuButton>
                </DropdownMenuTrigger>
                <DropdownMenuContent class="w-64" align="start">
                    <DropdownMenuLabel class="text-xs text-muted-foreground">
                        {{ $t('Switch workspace') }}
                    </DropdownMenuLabel>
                    <DropdownMenuItem
                        v-for="workspace in workspaces"
                        :key="`${workspace.type}-${workspace.slug}`"
                        :as-child="true"
                    >
                        <Link
                            :href="href(workspace)"
                            class="flex w-full items-center gap-2"
                        >
                            <span class="truncate">{{ workspace.name }}</span>
                            <span
                                class="ms-auto text-xs text-muted-foreground"
                                >{{
                                    workspace.type === 'team'
                                        ? $t('Team')
                                        : $t('Department')
                                }}</span
                            >
                            <Check
                                v-if="
                                    current?.type === workspace.type &&
                                    current?.slug === workspace.slug
                                "
                                class="size-4"
                            />
                        </Link>
                    </DropdownMenuItem>
                    <DropdownMenuSeparator />
                    <DropdownMenuItem :as-child="true">
                        <Link :href="hub()" class="w-full">{{
                            $t('All workspaces')
                        }}</Link>
                    </DropdownMenuItem>
                </DropdownMenuContent>
            </DropdownMenu>
        </SidebarMenuItem>
    </SidebarMenu>
</template>
