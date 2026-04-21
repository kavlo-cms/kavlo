<script setup lang="ts">
import {
    ChevronDown,
    ChevronRight,
    ExternalLink,
    Menu,
    X,
} from 'lucide-vue-next';
import { ref } from 'vue';

export interface NavMenuItem {
    id: number;
    label: string;
    url: string;
    target: string;
    children: NavMenuItem[];
}

defineProps<{
    items: NavMenuItem[];
    themeClass?: string;
    device?: 'desktop' | 'tablet' | 'mobile';
}>();

const mobileOpen = ref(false);
const expanded = ref<Set<number>>(new Set());

function toggle(id: number) {
    const s = new Set(expanded.value);

    if (s.has(id)) {
        s.delete(id);
    } else {
        s.add(id);
    }

    expanded.value = s;
}
</script>

<template>
    <div
        class="sticky top-0 z-10 border-b backdrop-blur-sm"
        :class="themeClass ? [themeClass, 'bg-opacity-95'] : 'bg-card/95'"
    >
        <!-- ── Main bar ─────────────────────────────────────────────────── -->
        <div class="flex items-center gap-4 px-6 py-2.5">
            <!-- Logo placeholder -->
            <div class="h-5 w-16 shrink-0 rounded bg-muted-foreground/20" />

            <!-- Desktop nav (hidden on mobile/tablet device) -->
            <nav
                v-if="device !== 'mobile'"
                class="flex flex-1 flex-wrap items-center gap-0.5"
            >
                <div
                    v-for="item in items"
                    :key="item.id"
                    class="group relative"
                >
                    <a
                        class="flex cursor-default items-center gap-1 rounded-md px-2.5 py-1.5 text-sm hover:bg-accent"
                        @click.prevent
                    >
                        <span>{{ item.label }}</span>
                        <ExternalLink
                            v-if="item.target === '_blank'"
                            class="h-3 w-3 text-muted-foreground/50"
                        />
                        <ChevronDown
                            v-if="item.children.length"
                            class="h-3 w-3 text-muted-foreground/50"
                        />
                    </a>

                    <!-- First-level dropdown -->
                    <div
                        v-if="item.children.length"
                        class="invisible absolute top-full left-0 z-50 mt-1 min-w-40 rounded-lg border bg-card p-1 shadow-lg group-hover:visible"
                    >
                        <div
                            v-for="child in item.children"
                            :key="child.id"
                            class="group/sub relative"
                        >
                            <a
                                class="flex cursor-default items-center gap-2 rounded-md px-3 py-1.5 text-sm hover:bg-accent"
                                @click.prevent
                            >
                                <span class="flex-1">{{ child.label }}</span>
                                <ExternalLink
                                    v-if="child.target === '_blank'"
                                    class="h-3 w-3 text-muted-foreground/50"
                                />
                                <ChevronRight
                                    v-if="child.children.length"
                                    class="h-3 w-3 text-muted-foreground/50"
                                />
                            </a>

                            <!-- Second-level flyout -->
                            <div
                                v-if="child.children.length"
                                class="invisible absolute top-0 left-full z-50 min-w-40 rounded-lg border bg-card p-1 shadow-lg group-hover/sub:visible"
                            >
                                <a
                                    v-for="gc in child.children"
                                    :key="gc.id"
                                    class="flex cursor-default items-center gap-2 rounded-md px-3 py-1.5 text-sm hover:bg-accent"
                                    @click.prevent
                                >
                                    <span class="flex-1">{{ gc.label }}</span>
                                    <ExternalLink
                                        v-if="gc.target === '_blank'"
                                        class="h-3 w-3 text-muted-foreground/50"
                                    />
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </nav>

            <div class="flex-1 md:hidden" />

            <!-- Mobile hamburger (visible on narrow canvas) -->
            <button
                class="flex h-8 w-8 items-center justify-center rounded-md hover:bg-accent md:hidden"
                @click.prevent="mobileOpen = !mobileOpen"
            >
                <X v-if="mobileOpen" class="h-4 w-4" />
                <Menu v-else class="h-4 w-4" />
            </button>

            <!-- Preview badge -->
            <span
                class="hidden shrink-0 rounded border border-dashed border-border px-1.5 py-0.5 text-xs text-muted-foreground/50 select-none md:inline"
            >
                nav preview
            </span>
        </div>

        <!-- ── Mobile drawer ───────────────────────────────────────────── -->
        <div v-if="mobileOpen" class="border-t px-4 pb-3 md:hidden">
            <div class="space-y-0.5 pt-2">
                <template v-for="item in items" :key="item.id">
                    <!-- Top-level item row -->
                    <div>
                        <div class="flex items-center">
                            <a
                                class="flex flex-1 cursor-default items-center gap-1 rounded-md px-3 py-2 text-sm hover:bg-accent"
                                @click.prevent
                            >
                                <span class="flex-1">{{ item.label }}</span>
                                <ExternalLink
                                    v-if="item.target === '_blank'"
                                    class="h-3 w-3 text-muted-foreground/50"
                                />
                            </a>
                            <button
                                v-if="item.children.length"
                                class="flex h-8 w-8 items-center justify-center rounded-md text-muted-foreground hover:bg-accent"
                                @click.prevent="toggle(item.id)"
                            >
                                <ChevronDown
                                    class="h-4 w-4 transition-transform duration-200"
                                    :class="
                                        expanded.has(item.id)
                                            ? 'rotate-180'
                                            : ''
                                    "
                                />
                            </button>
                        </div>

                        <!-- First-level children -->
                        <div
                            v-if="item.children.length && expanded.has(item.id)"
                            class="mt-0.5 ml-3 space-y-0.5 border-l pl-3"
                        >
                            <template
                                v-for="child in item.children"
                                :key="child.id"
                            >
                                <div>
                                    <div class="flex items-center">
                                        <a
                                            class="flex flex-1 cursor-default items-center gap-1 rounded-md px-3 py-1.5 text-sm hover:bg-accent"
                                            @click.prevent
                                        >
                                            <span class="flex-1">{{
                                                child.label
                                            }}</span>
                                            <ExternalLink
                                                v-if="child.target === '_blank'"
                                                class="h-3 w-3 text-muted-foreground/50"
                                            />
                                        </a>
                                        <button
                                            v-if="child.children.length"
                                            class="flex h-7 w-7 items-center justify-center rounded-md text-muted-foreground hover:bg-accent"
                                            @click.prevent="toggle(child.id)"
                                        >
                                            <ChevronDown
                                                class="h-3.5 w-3.5 transition-transform duration-200"
                                                :class="
                                                    expanded.has(child.id)
                                                        ? 'rotate-180'
                                                        : ''
                                                "
                                            />
                                        </button>
                                    </div>

                                    <!-- Second-level children -->
                                    <div
                                        v-if="
                                            child.children.length &&
                                            expanded.has(child.id)
                                        "
                                        class="mt-0.5 ml-3 space-y-0.5 border-l pl-3"
                                    >
                                        <a
                                            v-for="gc in child.children"
                                            :key="gc.id"
                                            class="flex cursor-default items-center gap-1 rounded-md px-3 py-1.5 text-sm hover:bg-accent"
                                            @click.prevent
                                        >
                                            <span class="flex-1">{{
                                                gc.label
                                            }}</span>
                                            <ExternalLink
                                                v-if="gc.target === '_blank'"
                                                class="h-3 w-3 text-muted-foreground/50"
                                            />
                                        </a>
                                    </div>
                                </div>
                            </template>
                        </div>
                    </div>
                </template>
            </div>
        </div>
    </div>
</template>
