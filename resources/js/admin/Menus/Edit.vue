<script setup lang="ts">
import type { FormDataConvertible } from '@inertiajs/core';
import { Link } from '@inertiajs/vue3';
import { router } from '@inertiajs/vue3';
import { useLocalStorage } from '@vueuse/core';
import {
    ArrowLeft,
    CheckCircle2,
    Loader2,
    Monitor,
    Plus,
    Smartphone,
    Tablet,
    Trash2,
} from 'lucide-vue-next';
import { computed, ref } from 'vue';
import type { StyleValue } from 'vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import BuilderLayout from '@/layouts/BuilderLayout.vue';
import CanvasNavBar from '../Pages/partials/CanvasNavBar.vue';
import type { NavMenuItem } from '../Pages/partials/CanvasNavBar.vue';
import MenuItemList, { type MenuItemNode } from './partials/MenuItemList.vue';

interface Page {
    id: number;
    title: string;
    slug: string;
}

interface ThemeConfig {
    canvas?: { class?: string; font?: string | null };
}

interface StrippedMenuItem extends Record<string, FormDataConvertible> {
    id: number | null;
    label: string;
    url: string;
    page_id: number | null;
    target: string;
    children: StrippedMenuItem[];
}

interface Props {
    menu: { id: number; name: string; slug: string };
    items: object[];
    pages: Page[];
    themeConfig: ThemeConfig;
}

const props = defineProps<Props>();

// ── State ─────────────────────────────────────────────────────────────────────

const menuName = ref(props.menu.name);
const menuSlug = ref(props.menu.slug);
const processing = ref(false);

let idCounter = 0;
function makeId() {
    return `_${++idCounter}`;
}

function hydrate(raw: any[]): MenuItemNode[] {
    return raw.map((item) => ({
        _id: makeId(),
        id: item.id ?? null,
        label: item.label ?? '',
        url: item.url ?? '',
        page_id: item.page_id ?? null,
        target: item.target ?? '_self',
        _open: false,
        children: hydrate(item.children ?? []),
    }));
}

function strip(nodes: MenuItemNode[]): StrippedMenuItem[] {
    return nodes.map((node) => ({
        id: node.id,
        label: node.label,
        url: node.url,
        page_id: node.page_id,
        target: node.target,
        children: strip(node.children),
    }));
}

const items = ref<MenuItemNode[]>(hydrate(props.items as any[]));

// ── Device preview ────────────────────────────────────────────────────────────

type Device = 'desktop' | 'tablet' | 'mobile';
const device = ref<Device>('desktop');

const deviceList: { key: Device; icon: typeof Monitor; label: string }[] = [
    { key: 'desktop', icon: Monitor, label: 'Desktop' },
    { key: 'tablet', icon: Tablet, label: 'Tablet' },
    { key: 'mobile', icon: Smartphone, label: 'Mobile' },
];

const deviceMaxWidths: Record<Device, string | null> = {
    desktop: null,
    tablet: '768px',
    mobile: '390px',
};
const previewStyle = computed<StyleValue>(() => {
    const style: Record<string, string> = {};
    const maxWidth = deviceMaxWidths[device.value];

    if (maxWidth) {
        style.maxWidth = maxWidth;
    }

    if (props.themeConfig?.canvas?.font) {
        style.fontFamily = props.themeConfig.canvas.font;
    }

    return Object.keys(style).length > 0 ? style : undefined;
});

const canvasClass = computed(
    () => props.themeConfig?.canvas?.class ?? 'bg-background',
);

function toNavItems(nodes: MenuItemNode[]): NavMenuItem[] {
    return nodes.map((n, i) => ({
        id: n.id ?? -(i + 1),
        label: n.label || 'Untitled',
        url: n.url || '#',
        target: n.target,
        children: toNavItems(n.children),
    }));
}
const navItems = computed(() => toNavItems(items.value));

// ── Resizable sidebars ────────────────────────────────────────────────────────

const leftWidth = useLocalStorage('menu-editor-left-width', 280);
const rightWidth = useLocalStorage('menu-editor-right-width', 320);

function startResize(side: 'left' | 'right', e: MouseEvent) {
    const startX = e.clientX;
    const startWidth = side === 'left' ? leftWidth.value : rightWidth.value;

    document.body.style.cursor = 'col-resize';
    document.body.style.userSelect = 'none';

    function onMove(ev: MouseEvent) {
        const delta =
            side === 'left' ? ev.clientX - startX : startX - ev.clientX;
        const newWidth = Math.max(200, Math.min(480, startWidth + delta));
        if (side === 'left') leftWidth.value = newWidth;
        else rightWidth.value = newWidth;
    }
    function onUp() {
        document.body.style.cursor = '';
        document.body.style.userSelect = '';
        document.removeEventListener('mousemove', onMove);
        document.removeEventListener('mouseup', onUp);
    }
    document.addEventListener('mousemove', onMove);
    document.addEventListener('mouseup', onUp);
}

// ── Add items ─────────────────────────────────────────────────────────────────

const addTab = ref<'pages' | 'custom'>('pages');
const selectedPageIds = ref<Set<number>>(new Set());
const customUrl = ref('');
const customLabel = ref('');

function togglePage(id: number) {
    const s = new Set(selectedPageIds.value);

    if (s.has(id)) {
        s.delete(id);
    } else {
        s.add(id);
    }

    selectedPageIds.value = s;
}

function addSelectedPages() {
    props.pages
        .filter((p) => selectedPageIds.value.has(p.id))
        .forEach((p) => {
            items.value.push({
                _id: makeId(),
                id: null,
                label: p.title,
                url: `/${p.slug}`,
                page_id: p.id,
                target: '_self',
                _open: false,
                children: [],
            });
        });
    selectedPageIds.value = new Set();
}

function addCustomLink() {
    if (!customUrl.value && !customLabel.value) return;
    items.value.push({
        _id: makeId(),
        id: null,
        label: customLabel.value || customUrl.value,
        url: customUrl.value,
        page_id: null,
        target: '_self',
        _open: false,
        children: [],
    });
    customUrl.value = '';
    customLabel.value = '';
}

const pageSearch = ref('');
const filteredPages = computed(() =>
    pageSearch.value
        ? props.pages.filter((p) =>
              p.title.toLowerCase().includes(pageSearch.value.toLowerCase()),
          )
        : props.pages,
);

// ── Save ──────────────────────────────────────────────────────────────────────

function save() {
    processing.value = true;
    router.put(
        `/admin/menus/${props.menu.id}`,
        {
            name: menuName.value,
            slug: menuSlug.value,
            items: strip(items.value),
        },
        { onFinish: () => (processing.value = false) },
    );
}

const savedFlash = ref(false);
let savedTimer: ReturnType<typeof setTimeout> | null = null;

function autoSaveItems() {
    if (processing.value) return;
    processing.value = true;
    router.put(
        `/admin/menus/${props.menu.id}`,
        {
            name: menuName.value,
            slug: menuSlug.value,
            items: strip(items.value),
        },
        {
            preserveState: true,
            preserveScroll: true,
            onFinish: () => {
                processing.value = false;
                savedFlash.value = true;
                if (savedTimer) clearTimeout(savedTimer);
                savedTimer = setTimeout(() => (savedFlash.value = false), 2000);
            },
        },
    );
}

function deleteMenu() {
    if (!confirm(`Delete "${menuName.value}"? This cannot be undone.`)) return;
    router.delete(`/admin/menus/${props.menu.id}`);
}

function onNameBlur() {
    if (
        menuSlug.value === props.menu.slug &&
        menuName.value !== props.menu.name
    ) {
        menuSlug.value = menuName.value
            .toLowerCase()
            .replace(/[^a-z0-9]+/g, '-')
            .replace(/^-|-$/g, '');
    }
}
</script>

<template>
    <BuilderLayout>
        <!-- ── Header ────────────────────────────────────────────────────── -->
        <template #header>
            <Button
                variant="ghost"
                size="icon"
                class="h-8 w-8 shrink-0"
                as-child
            >
                <Link href="/admin/menus"><ArrowLeft class="h-4 w-4" /></Link>
            </Button>

            <div class="mx-2 h-5 w-px bg-border" />

            <input
                v-model="menuName"
                class="w-40 min-w-0 rounded bg-transparent px-2 py-1 text-sm font-medium focus:ring-1 focus:ring-ring focus:outline-none"
                placeholder="Menu name"
                @blur="onNameBlur"
            />
            <div class="flex items-center rounded border bg-muted/40 pl-2">
                <span class="text-xs text-muted-foreground">slug/</span>
                <input
                    v-model="menuSlug"
                    class="w-28 bg-transparent px-2 py-1 font-mono text-xs focus:outline-none"
                    placeholder="menu-slug"
                />
            </div>

            <div class="flex-1" />

            <!-- Device toggles -->
            <div class="flex items-center gap-0.5">
                <button
                    v-for="btn in deviceList"
                    :key="btn.key"
                    class="flex h-8 w-8 items-center justify-center rounded-md transition-colors"
                    :class="
                        device === btn.key
                            ? 'bg-accent text-foreground'
                            : 'text-muted-foreground hover:bg-accent hover:text-foreground'
                    "
                    :title="btn.label"
                    @click="device = btn.key"
                >
                    <component :is="btn.icon" class="h-4 w-4" />
                </button>
            </div>

            <div class="mx-2 h-5 w-px bg-border" />

            <Transition
                enter-active-class="transition-all duration-200"
                enter-from-class="opacity-0 scale-90"
                leave-active-class="transition-all duration-200"
                leave-to-class="opacity-0 scale-90"
            >
                <span
                    v-if="savedFlash"
                    class="flex items-center gap-1 text-xs text-muted-foreground"
                >
                    <CheckCircle2 class="h-3.5 w-3.5 text-green-500" /> Saved
                </span>
            </Transition>

            <Button
                variant="ghost"
                size="sm"
                class="h-7 gap-1.5 text-xs text-muted-foreground hover:text-destructive"
                @click="deleteMenu"
            >
                <Trash2 class="h-3.5 w-3.5" />
            </Button>

            <Button
                size="sm"
                class="h-7 gap-1.5 text-xs"
                :disabled="processing"
                @click="save"
            >
                <Loader2 v-if="processing" class="h-3.5 w-3.5 animate-spin" />
                Save Menu
            </Button>

            <div class="mx-1" />
        </template>

        <!-- ── Left sidebar: Add items ────────────────────────────────────── -->
        <div
            class="flex shrink-0 flex-col overflow-hidden border-r bg-muted/10"
            :style="{ width: leftWidth + 'px' }"
        >
            <div class="flex shrink-0 items-center border-b px-3 py-2">
                <span
                    class="text-xs font-medium tracking-wide text-muted-foreground uppercase"
                    >Add Items</span
                >
            </div>

            <div class="flex min-h-0 flex-1 flex-col gap-3 overflow-y-auto p-3">
                <!-- Tab switcher -->
                <div class="flex rounded-lg border bg-muted/40 p-1">
                    <button
                        class="flex-1 rounded-md px-2 py-1.5 text-xs font-medium transition-colors"
                        :class="
                            addTab === 'pages'
                                ? 'bg-background shadow-xs'
                                : 'text-muted-foreground hover:text-foreground'
                        "
                        @click="addTab = 'pages'"
                    >
                        Pages
                    </button>
                    <button
                        class="flex-1 rounded-md px-2 py-1.5 text-xs font-medium transition-colors"
                        :class="
                            addTab === 'custom'
                                ? 'bg-background shadow-xs'
                                : 'text-muted-foreground hover:text-foreground'
                        "
                        @click="addTab = 'custom'"
                    >
                        Custom Link
                    </button>
                </div>

                <!-- Pages panel -->
                <div v-if="addTab === 'pages'" class="space-y-2">
                    <Input
                        v-model="pageSearch"
                        placeholder="Search pages…"
                        class="h-8 text-xs"
                    />
                    <div
                        class="max-h-64 space-y-0.5 overflow-y-auto rounded-lg border bg-card p-1"
                    >
                        <label
                            v-for="page in filteredPages"
                            :key="page.id"
                            class="flex cursor-pointer items-center gap-2 rounded px-2 py-1.5 text-xs hover:bg-accent"
                        >
                            <input
                                type="checkbox"
                                class="h-3.5 w-3.5 rounded accent-primary"
                                :checked="selectedPageIds.has(page.id)"
                                @change="togglePage(page.id)"
                            />
                            <span class="min-w-0 flex-1 truncate">{{
                                page.title
                            }}</span>
                            <span
                                class="shrink-0 font-mono text-muted-foreground"
                                >/{{ page.slug }}</span
                            >
                        </label>
                        <p
                            v-if="filteredPages.length === 0"
                            class="py-3 text-center text-xs text-muted-foreground"
                        >
                            No pages found.
                        </p>
                    </div>
                    <Button
                        size="sm"
                        class="h-8 w-full text-xs"
                        :disabled="selectedPageIds.size === 0"
                        @click="addSelectedPages"
                    >
                        <Plus class="mr-1.5 h-3.5 w-3.5" /> Add to Menu
                    </Button>
                </div>

                <!-- Custom link panel -->
                <div v-else class="space-y-2">
                    <div class="space-y-1">
                        <Label class="text-xs">URL</Label>
                        <Input
                            v-model="customUrl"
                            placeholder="https://… or /path"
                            class="h-8 font-mono text-xs"
                            @keydown.enter="addCustomLink"
                        />
                    </div>
                    <div class="space-y-1">
                        <Label class="text-xs">Link Text</Label>
                        <Input
                            v-model="customLabel"
                            placeholder="My Link"
                            class="h-8 text-xs"
                            @keydown.enter="addCustomLink"
                        />
                    </div>
                    <Button
                        size="sm"
                        class="h-8 w-full text-xs"
                        @click="addCustomLink"
                    >
                        <Plus class="mr-1.5 h-3.5 w-3.5" /> Add to Menu
                    </Button>
                </div>

                <!-- Tips -->
                <div
                    class="space-y-1 rounded-lg border border-dashed p-3 text-xs text-muted-foreground"
                >
                    <p>• Drag items on the right to reorder.</p>
                    <p>
                        • Drag <strong>under and right</strong> to nest (up to 3
                        levels).
                    </p>
                </div>
            </div>
        </div>

        <!-- Left drag handle -->
        <div
            class="w-1 shrink-0 cursor-col-resize bg-border transition-colors hover:bg-primary/40 active:bg-primary/60"
            @mousedown.prevent="startResize('left', $event)"
        />

        <!-- ── Center: Live canvas preview ───────────────────────────────── -->
        <div
            class="relative flex min-w-0 flex-1 overflow-y-auto transition-colors duration-300"
            :class="device !== 'desktop' ? 'bg-muted/20 py-8' : canvasClass"
        >
            <div
                class="mx-auto w-full overflow-hidden transition-all duration-300"
                :class="[
                    device !== 'desktop'
                        ? 'rounded-xl shadow-2xl ring-1 ring-border'
                        : '',
                    canvasClass,
                ]"
                :style="previewStyle"
            >
                <!-- Nav bar (live, updates as you edit) -->
                <CanvasNavBar
                    v-if="navItems.length > 0"
                    :items="navItems"
                    :theme-class="canvasClass"
                />
                <div
                    v-else
                    class="flex items-center justify-center border-b bg-card px-6 py-3 text-xs text-muted-foreground/50"
                >
                    Add items from the left panel to preview the nav
                </div>

                <!-- Mock hero -->
                <div class="space-y-3 px-10 py-14">
                    <div class="h-3 w-1/4 rounded bg-muted-foreground/15" />
                    <div class="h-7 w-2/3 rounded bg-muted-foreground/20" />
                    <div class="h-7 w-1/2 rounded bg-muted-foreground/15" />
                    <div
                        class="mt-1 h-3 w-3/4 rounded bg-muted-foreground/10"
                    />
                    <div class="h-3 w-2/3 rounded bg-muted-foreground/10" />
                    <div class="mt-5 flex gap-3">
                        <div
                            class="h-9 w-28 rounded-md bg-muted-foreground/25"
                        />
                        <div
                            class="h-9 w-28 rounded-md bg-muted-foreground/10"
                        />
                    </div>
                </div>

                <!-- Mock content cards -->
                <div class="grid grid-cols-3 gap-5 px-10 pb-14">
                    <div
                        v-for="n in 3"
                        :key="n"
                        class="space-y-2 rounded-xl border p-4"
                    >
                        <div class="h-20 rounded-lg bg-muted-foreground/10" />
                        <div class="h-3 w-3/4 rounded bg-muted-foreground/15" />
                        <div
                            class="h-2.5 w-1/2 rounded bg-muted-foreground/10"
                        />
                        <div
                            class="h-2.5 w-2/3 rounded bg-muted-foreground/10"
                        />
                    </div>
                </div>
            </div>
        </div>

        <!-- Right drag handle -->
        <div
            class="w-1 shrink-0 cursor-col-resize bg-border transition-colors hover:bg-primary/40 active:bg-primary/60"
            @mousedown.prevent="startResize('right', $event)"
        />

        <!-- ── Right sidebar: Menu structure ──────────────────────────────── -->
        <div
            class="flex shrink-0 flex-col overflow-hidden border-l bg-muted/10"
            :style="{ width: rightWidth + 'px' }"
        >
            <div
                class="flex shrink-0 items-center justify-between border-b px-3 py-2"
            >
                <span
                    class="text-xs font-medium tracking-wide text-muted-foreground uppercase"
                    >Structure</span
                >
                <span class="text-xs text-muted-foreground"
                    >{{ items.length }} top-level</span
                >
            </div>

            <div class="flex min-h-0 flex-1 flex-col overflow-y-auto p-3">
                <div
                    v-if="items.length === 0"
                    class="flex flex-1 items-center justify-center rounded-lg border border-dashed py-10 text-xs text-muted-foreground"
                >
                    Add items from the left panel
                </div>
                <MenuItemList v-else v-model="items" @sort="autoSaveItems" />
            </div>
        </div>
    </BuilderLayout>
</template>
