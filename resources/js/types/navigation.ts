import type { InertiaLinkProps } from '@inertiajs/vue3';
import type { LucideIcon } from '@lucide/vue';

export type BreadcrumbItem = {
    title: string;
    /** Omit for the current page (the last crumb). */
    href?: NonNullable<InertiaLinkProps['href']>;
};

export type NavGroup = {
    key: string;
    label: string;
    items: NavItem[];
};

export type NavItem = {
    title: string;
    href: NonNullable<InertiaLinkProps['href']>;
    icon?: LucideIcon;
    isActive?: boolean;
};
