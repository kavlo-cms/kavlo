<script setup lang="ts">
import { useForm, router } from '@inertiajs/vue3';
import { ArrowRight, Pencil, Trash2, ToggleLeft, ToggleRight } from 'lucide-vue-next';
import { ref } from 'vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import UrlField, { type PageOption } from '@/components/UrlField.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import admin from '@/routes/admin';
import type { BreadcrumbItem } from '@/types';

interface Redirect {
    id: number;
    from_url: string;
    to_url: string;
    type: 301 | 302;
    is_active: boolean;
    hits: number;
    last_hit_at: string | null;
    updated_at: string;
}

const props = defineProps<{
    redirects: Redirect[];
    pages: PageOption[];
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Redirects', href: admin.redirects.index.url() },
];

// ── Create form ──────────────────────────────────────────────────────────────
const createForm = useForm({
    from_url:  '',
    to_url:    '',
    type:      '301',
    is_active: true,
});

function submitCreate() {
    createForm.post(admin.redirects.store.url(), {
        onSuccess: () => createForm.reset(),
    });
}

// ── Edit dialog ──────────────────────────────────────────────────────────────
const editOpen   = ref(false);
const editTarget = ref<Redirect | null>(null);
// key to force UrlField to remount (reset internal type state) when opening a different redirect
const editKey    = ref(0);

const editForm = useForm({
    from_url:  '',
    to_url:    '',
    type:      '301',
    is_active: true,
});

function openEdit(redirect: Redirect) {
    editTarget.value   = redirect;
    editForm.from_url  = redirect.from_url;
    editForm.to_url    = redirect.to_url;
    editForm.type      = String(redirect.type);
    editForm.is_active = redirect.is_active;
    editKey.value++;
    editOpen.value = true;
}

function submitEdit() {
    if (!editTarget.value) return;
    editForm.put(admin.redirects.update(editTarget.value.id).url(), {
        onSuccess: () => { editOpen.value = false; },
    });
}

// ── Actions ──────────────────────────────────────────────────────────────────
function toggle(redirect: Redirect) {
    router.patch(admin.redirects.toggle(redirect.id).url());
}

function destroy(redirect: Redirect) {
    if (!confirm(`Delete redirect from "${redirect.from_url}"?`)) return;
    router.delete(admin.redirects.destroy(redirect.id).url());
}
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex items-center justify-between">
            <h1 class="text-2xl font-semibold tracking-tight">Redirects</h1>
        </div>

        <!-- Create form -->
        <form @submit.prevent="submitCreate" class="rounded-lg border bg-card p-5 space-y-4">
            <p class="text-sm font-medium">Add Redirect</p>
            <div class="grid gap-4 sm:grid-cols-[1fr_auto_1fr_auto_auto] items-end">
                <!-- From -->
                <div class="flex flex-col gap-1.5">
                    <Label>From</Label>
                    <UrlField
                        v-model="createForm.from_url"
                        :pages="props.pages"
                        side="from"
                        placeholder="/old-page"
                        :error="createForm.errors.from_url"
                    />
                </div>

                <ArrowRight class="h-4 w-4 text-muted-foreground mb-1 shrink-0 self-end" />

                <!-- To -->
                <div class="flex flex-col gap-1.5">
                    <Label>To</Label>
                    <UrlField
                        v-model="createForm.to_url"
                        :pages="props.pages"
                        side="to"
                        placeholder="/new-page"
                        :error="createForm.errors.to_url"
                    />
                </div>

                <!-- Type -->
                <div class="flex flex-col gap-1.5 w-32 shrink-0">
                    <Label>Type</Label>
                    <Select v-model="createForm.type">
                        <SelectTrigger>
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="301">301 Permanent</SelectItem>
                            <SelectItem value="302">302 Temporary</SelectItem>
                        </SelectContent>
                    </Select>
                </div>

                <Button type="submit" :disabled="createForm.processing" class="self-end shrink-0">
                    Add
                </Button>
            </div>
        </form>

        <!-- Empty state -->
        <div v-if="redirects.length === 0" class="flex min-h-40 items-center justify-center rounded-lg border border-dashed text-sm text-muted-foreground">
            No redirects yet.
        </div>

        <!-- Table -->
        <div v-else class="rounded-lg border overflow-hidden">
            <table class="w-full text-sm">
                <thead class="bg-muted/50 text-xs text-muted-foreground uppercase tracking-wide">
                    <tr>
                        <th class="px-4 py-2.5 text-left font-medium">From</th>
                        <th class="px-4 py-2.5 text-left font-medium">To</th>
                        <th class="px-4 py-2.5 text-left font-medium w-24">Type</th>
                        <th class="px-4 py-2.5 text-left font-medium w-16">Hits</th>
                        <th class="px-4 py-2.5 text-left font-medium w-20">Status</th>
                        <th class="px-4 py-2.5 w-24"></th>
                    </tr>
                </thead>
                <tbody class="divide-y">
                    <tr v-for="redirect in redirects" :key="redirect.id" class="hover:bg-muted/30 transition-colors">
                        <td class="px-4 py-2.5 font-mono text-xs">{{ redirect.from_url }}</td>
                        <td class="px-4 py-2.5 font-mono text-xs text-muted-foreground truncate max-w-xs">
                            {{ redirect.to_url }}
                        </td>
                        <td class="px-4 py-2.5">
                            <Badge variant="outline" class="text-xs">{{ redirect.type }}</Badge>
                        </td>
                        <td class="px-4 py-2.5 tabular-nums text-muted-foreground">{{ redirect.hits }}</td>
                        <td class="px-4 py-2.5">
                            <Badge :variant="redirect.is_active ? 'default' : 'secondary'" class="text-xs">
                                {{ redirect.is_active ? 'Active' : 'Off' }}
                            </Badge>
                        </td>
                        <td class="px-4 py-2.5">
                            <div class="flex items-center justify-end gap-0.5">
                                <Button
                                    variant="ghost" size="icon" class="h-7 w-7"
                                    :title="redirect.is_active ? 'Disable' : 'Enable'"
                                    @click="toggle(redirect)"
                                >
                                    <ToggleRight v-if="redirect.is_active" class="h-4 w-4 text-primary" />
                                    <ToggleLeft v-else class="h-4 w-4 text-muted-foreground" />
                                </Button>
                                <Button
                                    variant="ghost" size="icon" class="h-7 w-7"
                                    title="Edit"
                                    @click="openEdit(redirect)"
                                >
                                    <Pencil class="h-3.5 w-3.5" />
                                </Button>
                                <Button
                                    variant="ghost" size="icon" class="h-7 w-7 text-muted-foreground hover:text-destructive"
                                    title="Delete"
                                    @click="destroy(redirect)"
                                >
                                    <Trash2 class="h-3.5 w-3.5" />
                                </Button>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Edit dialog -->
        <Dialog v-model:open="editOpen">
            <DialogContent class="sm:max-w-lg">
                <DialogHeader>
                    <DialogTitle>Edit Redirect</DialogTitle>
                </DialogHeader>
                <form :key="editKey" @submit.prevent="submitEdit" class="space-y-4 pt-2">
                    <div class="space-y-1.5">
                        <Label>From</Label>
                        <UrlField
                            v-model="editForm.from_url"
                            :pages="props.pages"
                            side="from"
                            :error="editForm.errors.from_url"
                        />
                    </div>
                    <div class="space-y-1.5">
                        <Label>To</Label>
                        <UrlField
                            v-model="editForm.to_url"
                            :pages="props.pages"
                            side="to"
                            :error="editForm.errors.to_url"
                        />
                    </div>
                    <div class="space-y-1.5">
                        <Label>Type</Label>
                        <Select v-model="editForm.type">
                            <SelectTrigger>
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="301">301 Permanent</SelectItem>
                                <SelectItem value="302">302 Temporary</SelectItem>
                            </SelectContent>
                        </Select>
                    </div>
                    <div class="flex justify-end gap-2 pt-2">
                        <Button type="button" variant="outline" @click="editOpen = false">Cancel</Button>
                        <Button type="submit" :disabled="editForm.processing">Save Changes</Button>
                    </div>
                </form>
            </DialogContent>
        </Dialog>
    </AppLayout>
</template>
