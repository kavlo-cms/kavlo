<script setup lang="ts">
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { Link } from '@inertiajs/vue3';
import {
    Archive,
    ArrowRightLeft,
    Calendar,
    ClipboardList,
    Code,
    Database,
    FileText,
    Globe,
    History,
    Image,
    LayoutDashboard,
    LayoutTemplate,
    Link as LinkIcon,
    type LucideIcon,
    Mail,
    Navigation,
    Package,
    Paintbrush,
    Plug,
    Settings,
    ShieldCheck,
    ShoppingCart,
    Star,
    Tag,
    Users,
    WrenchIcon,
    Zap,
} from 'lucide-vue-next';
import AppLogo from '@/components/AppLogo.vue';
import NavMain from '@/components/NavMain.vue';
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
import admin from '@/routes/admin';
import type { AdminNavItem, NavItem } from '@/types';

const iconMap: Record<string, LucideIcon> = {
    'archive': Archive,
    'calendar': Calendar,
    'code': Code,
    'database': Database,
    'file-text': FileText,
    'globe': Globe,
    'image': Image,
    'link': LinkIcon,
    'mail': Mail,
    'navigation': Navigation,
    'package': Package,
    'paintbrush': Paintbrush,
    'plug': Plug,
    'settings': Settings,
    'shield-check': ShieldCheck,
    'shopping-cart': ShoppingCart,
    'star': Star,
    'tag': Tag,
    'users': Users,
};

function resolveIcon(name?: string): LucideIcon | undefined {
    return name ? (iconMap[name] ?? Plug) : undefined;
}

function toNavItem(item: AdminNavItem): NavItem {
    return { title: item.title, href: item.href, icon: resolveIcon(item.icon) };
}

const adminNav: AdminNavItem[] = usePage().props.adminNav ?? [];

function dynamicGroup(label: string): NavItem[] {
    return adminNav.filter((i) => i.group === label).map(toNavItem);
}

const dynamicGroups = computed(() => {
    const knownGroups = new Set(['Content', 'Structure', 'Settings', 'System']);
    const extra: Record<string, NavItem[]> = {};
    for (const item of adminNav) {
        if (!knownGroups.has(item.group)) {
            (extra[item.group] ??= []).push(toNavItem(item));
        }
    }
    return extra;
});

const contentItems: NavItem[] = [
    { title: 'Dashboard', href: admin.dashboard.url(), icon: LayoutDashboard },
    { title: 'Pages',     href: admin.pages.index.url(), icon: FileText },
    { title: 'Media',     href: '/admin/media', icon: Image },
    { title: 'Forms',     href: admin.forms.index.url(), icon: ClipboardList },
    ...dynamicGroup('Content'),
];

const structureItems: NavItem[] = [
    { title: 'Menus',     href: '/admin/menus', icon: Navigation },
    { title: 'Blocks',    href: admin.blocks.index.url(), icon: LayoutTemplate },
    { title: 'Redirects', href: admin.redirects.index.url(), icon: ArrowRightLeft },
    ...dynamicGroup('Structure'),
];

const settingsItems: NavItem[] = [
    { title: 'General',       href: admin.settings.index.url(), icon: Settings },
    { title: 'Email',         href: admin.settings.email.index.url(), icon: Mail },
    { title: 'Themes',        href: admin.themes.index.url(),   icon: Paintbrush },
    { title: 'Plugins',       href: admin.plugins.index.url(),  icon: Plug },
    { title: 'Users & Roles', href: admin.users.index.url(),    icon: Users },
    ...dynamicGroup('Settings'),
];

const systemItems: NavItem[] = [
    { title: 'Activity Log',  href: admin.activity.index.url(),    icon: History },
    { title: 'Cache',         href: admin.cache.index.url(),       icon: Zap },
    { title: 'Maintenance',   href: admin.maintenance.index.url(), icon: WrenchIcon },
    ...dynamicGroup('System'),
];
</script>

<template>
    <Sidebar collapsible="icon" variant="inset">
        <SidebarHeader>
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton size="lg" as-child>
                        <Link :href="admin.dashboard.url()">
                            <AppLogo />
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarHeader>

        <SidebarContent>
            <NavMain label="Content" :items="contentItems" />
            <NavMain label="Structure" :items="structureItems" />
            <NavMain label="Settings" :items="settingsItems" />
            <NavMain label="System" :items="systemItems" />
            <NavMain
                v-for="(items, label) in dynamicGroups"
                :key="label"
                :label="label"
                :items="items"
            />
        </SidebarContent>

        <SidebarFooter>
            <NavUser />
        </SidebarFooter>
    </Sidebar>
</template>
