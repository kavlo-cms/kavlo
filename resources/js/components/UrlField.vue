<script setup lang="ts">
/**
 * UrlField — type-aware URL input for redirects.
 *
 * Modes:
 *  - "page"     → pick from CMS pages (searchable dropdown)
 *  - "path"     → relative path  e.g. /old-blog
 *  - "external" → full URL       e.g. https://example.com
 *
 * "from" side only exposes page + path; "to" adds external.
 */
import { FileText, Globe, Link } from 'lucide-vue-next';
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';
import { Input } from '@/components/ui/input';

export interface PageOption {
    id: number;
    title: string;
    slug: string;
}

type UrlType = 'page' | 'path' | 'external';

const props = defineProps<{
    modelValue: string;
    pages: PageOption[];
    side: 'from' | 'to';
    error?: string;
    placeholder?: string;
}>();

const emit = defineEmits<{
    'update:modelValue': [value: string];
}>();

// ── Available types ───────────────────────────────────────────────────────────
const types = computed(
    (): { key: UrlType; label: string; icon: typeof Link }[] => {
        const list: { key: UrlType; label: string; icon: typeof Link }[] = [
            { key: 'page', label: 'Page', icon: FileText },
            { key: 'path', label: 'Path', icon: Link },
        ];
        if (props.side === 'to') {
            list.push({ key: 'external', label: 'External', icon: Globe });
        }
        return list;
    },
);

// ── Auto-detect type from stored value ────────────────────────────────────────
function detectType(value: string): UrlType {
    if (!value) return 'path';
    if (/^https?:\/\//i.test(value)) return 'external';
    if (
        props.pages.some(
            (p) =>
                '/' + p.slug === value ||
                p.slug === value ||
                ('/' + p.slug).replace(/\/+$/, '') ===
                    value.replace(/\/+$/, ''),
        )
    )
        return 'page';
    return 'path';
}

const activeType = ref<UrlType>(detectType(props.modelValue));

watch(
    () => props.modelValue,
    (v) => {
        // Only auto-detect if the user hasn't manually switched the type
        if (!userSwitchedType.value) {
            activeType.value = detectType(v);
        }
    },
);

const userSwitchedType = ref(false);

function setType(t: UrlType) {
    userSwitchedType.value = true;
    activeType.value = t;
    // Clear value on type switch
    emit('update:modelValue', '');
    pageSearch.value = '';
}

// ── Page picker state ─────────────────────────────────────────────────────────
const pageSearch = ref('');
const dropdownOpen = ref(false);
const pickerRef = ref<HTMLElement | null>(null);

const selectedPage = computed(
    () =>
        props.pages.find((p) => {
            const slug = p.slug.startsWith('/') ? p.slug : '/' + p.slug;
            return slug === props.modelValue || p.slug === props.modelValue;
        }) ?? null,
);

const filteredPages = computed(() => {
    const q = pageSearch.value.toLowerCase();
    if (!q) return props.pages.slice(0, 50);
    return props.pages
        .filter(
            (p) =>
                p.title.toLowerCase().includes(q) ||
                p.slug.toLowerCase().includes(q),
        )
        .slice(0, 50);
});

function selectPage(page: PageOption) {
    const slug = page.slug.startsWith('/') ? page.slug : '/' + page.slug;
    emit('update:modelValue', slug);
    pageSearch.value = '';
    dropdownOpen.value = false;
}

// ── Click outside to close ────────────────────────────────────────────────────
function onDocClick(e: MouseEvent) {
    if (pickerRef.value && !pickerRef.value.contains(e.target as Node)) {
        dropdownOpen.value = false;
    }
}

onMounted(() => document.addEventListener('mousedown', onDocClick));
onUnmounted(() => document.removeEventListener('mousedown', onDocClick));
</script>

<template>
    <div class="flex flex-col gap-1.5">
        <!-- Type selector tabs -->
        <div class="flex w-fit overflow-hidden rounded-md border text-xs">
            <button
                v-for="t in types"
                :key="t.key"
                type="button"
                class="flex items-center gap-1 px-2.5 py-1 transition-colors"
                :class="
                    activeType === t.key
                        ? 'bg-primary text-primary-foreground'
                        : 'bg-background text-muted-foreground hover:bg-muted hover:text-foreground'
                "
                @click="setType(t.key)"
            >
                <component :is="t.icon" class="h-3 w-3" />
                {{ t.label }}
            </button>
        </div>

        <!-- Page picker -->
        <div v-if="activeType === 'page'" ref="pickerRef" class="relative">
            <div
                class="flex h-9 w-full cursor-pointer items-center rounded-md border border-input bg-background px-3 py-1 text-sm shadow-xs transition-colors hover:bg-muted/40"
                :class="{ 'border-destructive': error }"
                @click="dropdownOpen = !dropdownOpen"
            >
                <span v-if="selectedPage" class="truncate">
                    {{ selectedPage.title }}
                    <span class="ml-1.5 text-xs text-muted-foreground"
                        >/{{ selectedPage.slug }}</span
                    >
                </span>
                <span v-else class="text-muted-foreground">Select a page…</span>
            </div>
            <div
                v-if="dropdownOpen"
                class="absolute z-50 mt-1 w-full rounded-md border bg-popover shadow-md"
            >
                <div class="border-b p-1.5">
                    <input
                        v-model="pageSearch"
                        class="w-full rounded bg-transparent px-2 py-1 text-sm outline-none placeholder:text-muted-foreground"
                        placeholder="Search pages…"
                        autofocus
                        @click.stop
                    />
                </div>
                <ul class="max-h-52 overflow-y-auto py-1">
                    <li
                        v-for="page in filteredPages"
                        :key="page.id"
                        class="flex cursor-pointer flex-col px-3 py-1.5 hover:bg-accent"
                        @click="selectPage(page)"
                    >
                        <span class="text-sm font-medium">{{
                            page.title
                        }}</span>
                        <span class="text-xs text-muted-foreground"
                            >/{{ page.slug }}</span
                        >
                    </li>
                    <li
                        v-if="filteredPages.length === 0"
                        class="px-3 py-3 text-center text-xs text-muted-foreground"
                    >
                        No pages found
                    </li>
                </ul>
            </div>
        </div>

        <!-- Path or External text input -->
        <Input
            v-else
            :model-value="modelValue"
            :placeholder="
                placeholder ??
                (activeType === 'external'
                    ? 'https://example.com'
                    : '/old-page')
            "
            :class="{ 'border-destructive': error }"
            @update:model-value="emit('update:modelValue', String($event))"
        />

        <p v-if="error" class="text-xs text-destructive">{{ error }}</p>
    </div>
</template>
