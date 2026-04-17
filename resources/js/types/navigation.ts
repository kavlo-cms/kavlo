import type { InertiaLinkProps } from '@inertiajs/vue3';
import type { LucideIcon } from 'lucide-vue-next';

export type BreadcrumbItem = {
    title: string;
    href: NonNullable<InertiaLinkProps['href']>;
};

export type NavItem = {
    title: string;
    href: NonNullable<InertiaLinkProps['href']>;
    icon?: LucideIcon;
    isActive?: boolean;
};

/** Nav item registered server-side via the `admin.nav` hook. */
export type AdminNavItem = {
    group: string;
    title: string;
    href: string;
    icon?: string; // kebab-case lucide icon name, e.g. "shopping-cart"
};
