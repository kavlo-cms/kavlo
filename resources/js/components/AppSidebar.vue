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
    Search,
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
import type { Auth, AdminNavItem, NavItem } from '@/types';

type GuardedNavItem = NavItem & { permission?: string };

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
    'search': Search,
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

const page = usePage<{ auth?: Auth; adminNav?: AdminNavItem[] }>();
const adminNav: AdminNavItem[] = page.props.adminNav ?? [];
const authPermissions = computed(() => new Set(page.props.auth?.permissions ?? []));

function canAccess(permission?: string): boolean {
    return !permission || authPermissions.value.has(permission);
}

function filterItems(items: GuardedNavItem[]): NavItem[] {
    return items.filter((item) => canAccess(item.permission)).map(({ permission, ...item }) => item);
}

function dynamicGroup(label: string): NavItem[] {
    return adminNav.filter((i) => i.group === label && canAccess(i.permission)).map(toNavItem);
}

const dynamicGroups = computed(() => {
    const knownGroups = new Set(['Content', 'Structure', 'Settings', 'System']);
    const extra: Record<string, NavItem[]> = {};
    for (const item of adminNav) {
        if (!knownGroups.has(item.group) && canAccess(item.permission)) {
            (extra[item.group] ??= []).push(toNavItem(item));
        }
    }
    return extra;
});

const contentItems = computed(() => filterItems([
    { title: 'Dashboard', href: admin.dashboard.url(), icon: LayoutDashboard },
    { title: 'Search', href: '/admin/search', icon: Search },
    { title: 'Pages', href: admin.pages.index.url(), icon: FileText, permission: 'view pages' },
    { title: 'Media', href: '/admin/media', icon: Image, permission: 'view media' },
    { title: 'Forms', href: admin.forms.index.url(), icon: ClipboardList, permission: 'view forms' },
    ...dynamicGroup('Content'),
]));

const structureItems = computed(() => filterItems([
    { title: 'Menus', href: '/admin/menus', icon: Navigation, permission: 'view menus' },
    { title: 'Email Templates', href: '/admin/email-templates', icon: Mail, permission: 'view email templates' },
    { title: 'DataHub', href: admin.datahub.index.url(), icon: Database, permission: 'view datahub' },
    { title: 'Blocks', href: admin.blocks.index.url(), icon: LayoutTemplate, permission: 'view pages' },
    { title: 'Redirects', href: admin.redirects.index.url(), icon: ArrowRightLeft, permission: 'view redirects' },
    ...dynamicGroup('Structure'),
]));

const settingsItems = computed(() => filterItems([
    { title: 'General', href: admin.settings.index.url(), icon: Settings, permission: 'view settings' },
    { title: 'Scripts', href: '/admin/scripts', icon: Code, permission: 'view scripts' },
    { title: 'Email', href: admin.settings.email.index.url(), icon: Mail, permission: 'view settings' },
    { title: 'Themes', href: admin.themes.index.url(), icon: Paintbrush, permission: 'view themes' },
    { title: 'Plugins', href: admin.plugins.index.url(), icon: Plug, permission: 'view plugins' },
    { title: 'Users & Roles', href: admin.users.index.url(), icon: Users, permission: 'view users' },
    ...dynamicGroup('Settings'),
]));

const systemItems = computed(() => filterItems([
    { title: 'Analytics', href: '/admin/analytics', icon: Globe, permission: 'view analytics' },
    { title: 'Activity Log', href: admin.activity.index.url(), icon: History, permission: 'view activity log' },
    { title: 'Backups', href: admin.backups.index.url(), icon: Archive, permission: 'manage backups' },
    { title: 'Cache', href: admin.cache.index.url(), icon: Zap, permission: 'manage cache' },
    { title: 'Maintenance', href: admin.maintenance.index.url(), icon: WrenchIcon, permission: 'manage maintenance' },
    ...dynamicGroup('System'),
]));
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
            <NavMain v-if="contentItems.length" label="Content" :items="contentItems" />
            <NavMain v-if="structureItems.length" label="Structure" :items="structureItems" />
            <NavMain v-if="settingsItems.length" label="Settings" :items="settingsItems" />
            <NavMain v-if="systemItems.length" label="System" :items="systemItems" />
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
