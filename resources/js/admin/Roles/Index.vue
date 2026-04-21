<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import {
    ChevronDown,
    ChevronRight,
    Plus,
    ShieldCheck,
    Trash2,
} from 'lucide-vue-next';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import admin from '@/routes/admin';
import type { BreadcrumbItem } from '@/types';
import { computed, ref } from 'vue';

interface Role {
    id: number;
    name: string;
    permissions: string[];
    users_count: number;
}

const props = defineProps<{ roles: Role[]; permissions: string[] }>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Admin', href: admin.dashboard.url() },
    { title: 'Roles & Permissions', href: admin.users.index.url() },
];

const BUILTIN = ['super-admin', 'admin', 'editor', 'author'];

// Group permissions by prefix (e.g. "manage pages" → "pages")
const permissionGroups = computed(() => {
    const groups: Record<string, string[]> = {};
    for (const perm of props.permissions) {
        const parts = perm.split(' ');
        const group = parts.length > 1 ? parts.slice(1).join(' ') : 'other';
        (groups[group] ??= []).push(perm);
    }
    return Object.entries(groups).sort(([a], [b]) => a.localeCompare(b));
});

// ── Edit role permissions ──────────────────────────────────────────────────
const editing = ref<Role | null>(null);
const editPerms = ref<string[]>([]);
const expandedGroups = ref<Set<string>>(new Set());

function openEdit(role: Role) {
    editing.value = role;
    editPerms.value = [...role.permissions];
    expandedGroups.value = new Set(permissionGroups.value.map(([g]) => g));
}

function toggleGroup(group: string) {
    if (expandedGroups.value.has(group)) expandedGroups.value.delete(group);
    else expandedGroups.value.add(group);
}

function groupCheckedState(groupPerms: string[]): boolean | 'indeterminate' {
    const checked = groupPerms.filter((p) => editPerms.value.includes(p));
    if (checked.length === 0) return false;
    if (checked.length === groupPerms.length) return true;
    return 'indeterminate';
}

function toggleGroupPerms(groupPerms: string[], checked: boolean) {
    if (checked) {
        const toAdd = groupPerms.filter((p) => !editPerms.value.includes(p));
        editPerms.value.push(...toAdd);
    } else {
        editPerms.value = editPerms.value.filter(
            (p) => !groupPerms.includes(p),
        );
    }
}

function savePermissions() {
    if (!editing.value) return;
    router.put(
        admin.roles.update.url({ role: String(editing.value.id) }),
        { permissions: editPerms.value },
        {
            preserveScroll: true,
            onSuccess: () => {
                editing.value = null;
            },
        },
    );
}

// ── Create role ────────────────────────────────────────────────────────────
const creating = ref(false);
const newName = ref('');
const newPerms = ref<string[]>([]);

function openCreate() {
    newName.value = '';
    newPerms.value = [];
    creating.value = true;
}

function createRole() {
    router.post(
        admin.roles.store.url(),
        { name: newName.value, permissions: newPerms.value },
        {
            preserveScroll: true,
            onSuccess: () => {
                creating.value = false;
            },
        },
    );
}

// ── Delete role ────────────────────────────────────────────────────────────
function destroy(role: Role) {
    if (!confirm(`Delete role "${role.name}"? This cannot be undone.`)) return;
    router.delete(admin.roles.destroy.url({ role: String(role.id) }), {
        preserveScroll: true,
    });
}

const roleVariant = (name: string): 'default' | 'secondary' | 'outline' => {
    if (name === 'super-admin' || name === 'admin') return 'default';
    if (name === 'editor') return 'secondary';
    return 'outline';
};
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex items-center justify-between">
            <h1 class="text-2xl font-semibold tracking-tight">
                Roles &amp; Permissions
            </h1>
            <Button size="sm" @click="openCreate">
                <Plus class="mr-1.5 h-4 w-4" />
                New Role
            </Button>
        </div>

        <div class="space-y-3">
            <Card v-for="role in props.roles" :key="role.id">
                <CardHeader class="pb-3">
                    <div class="flex items-start justify-between gap-4">
                        <div class="min-w-0 flex-1">
                            <div class="flex flex-wrap items-center gap-2">
                                <CardTitle
                                    class="flex items-center gap-1.5 text-base capitalize"
                                >
                                    <ShieldCheck
                                        class="h-4 w-4 text-muted-foreground"
                                    />
                                    {{ role.name }}
                                </CardTitle>
                                <Badge
                                    :variant="roleVariant(role.name)"
                                    class="text-xs capitalize"
                                >
                                    {{ role.users_count }} user{{
                                        role.users_count === 1 ? '' : 's'
                                    }}
                                </Badge>
                                <Badge
                                    v-if="BUILTIN.includes(role.name)"
                                    variant="outline"
                                    class="text-xs"
                                >
                                    built-in
                                </Badge>
                            </div>
                        </div>
                        <div class="flex shrink-0 gap-2">
                            <Button
                                variant="outline"
                                size="sm"
                                :disabled="role.name === 'super-admin'"
                                @click="openEdit(role)"
                            >
                                Edit Permissions
                            </Button>
                            <Button
                                variant="destructive"
                                size="sm"
                                :disabled="BUILTIN.includes(role.name)"
                                @click="destroy(role)"
                            >
                                <Trash2 class="h-4 w-4" />
                            </Button>
                        </div>
                    </div>
                </CardHeader>
                <CardContent v-if="role.permissions.length" class="pt-0 pb-3">
                    <div class="flex flex-wrap gap-1.5">
                        <Badge
                            v-for="perm in role.permissions"
                            :key="perm"
                            variant="secondary"
                            class="text-xs font-normal"
                        >
                            {{ perm }}
                        </Badge>
                    </div>
                </CardContent>
                <CardContent
                    v-else
                    class="pt-0 pb-3 text-xs text-muted-foreground italic"
                >
                    No permissions assigned.
                </CardContent>
            </Card>
        </div>

        <!-- Edit permissions dialog -->
        <Dialog
            :open="!!editing"
            @update:open="
                (v) => {
                    if (!v) editing = null;
                }
            "
        >
            <DialogContent class="max-h-[80vh] overflow-y-auto sm:max-w-lg">
                <DialogHeader>
                    <DialogTitle class="capitalize"
                        >Edit — {{ editing?.name }}</DialogTitle
                    >
                    <DialogDescription
                        >Toggle groups or individual
                        permissions.</DialogDescription
                    >
                </DialogHeader>

                <div class="space-y-3 py-2">
                    <div
                        v-for="[group, perms] in permissionGroups"
                        :key="group"
                        class="rounded-md border"
                    >
                        <!-- Group header -->
                        <button
                            type="button"
                            class="flex w-full items-center gap-3 px-3 py-2 text-sm font-medium hover:bg-muted/50"
                            @click="toggleGroup(group)"
                        >
                            <Checkbox
                                :id="`group-${group}`"
                                :checked="groupCheckedState(perms)"
                                @update:checked="
                                    (v: boolean) => toggleGroupPerms(perms, v)
                                "
                                @click.stop
                            />
                            <span class="flex-1 text-left capitalize">{{
                                group
                            }}</span>
                            <ChevronDown
                                v-if="expandedGroups.has(group)"
                                class="h-4 w-4 text-muted-foreground"
                            />
                            <ChevronRight
                                v-else
                                class="h-4 w-4 text-muted-foreground"
                            />
                        </button>
                        <!-- Individual perms -->
                        <div
                            v-if="expandedGroups.has(group)"
                            class="divide-y border-t"
                        >
                            <div
                                v-for="perm in perms"
                                :key="perm"
                                class="flex items-center gap-3 px-3 py-2 pl-9"
                            >
                                <Checkbox
                                    :id="`perm-${perm}`"
                                    :checked="editPerms.includes(perm)"
                                    @update:checked="
                                        (v: boolean) =>
                                            v
                                                ? editPerms.push(perm)
                                                : editPerms.splice(
                                                      editPerms.indexOf(perm),
                                                      1,
                                                  )
                                    "
                                />
                                <Label
                                    :for="`perm-${perm}`"
                                    class="cursor-pointer text-sm"
                                    >{{ perm }}</Label
                                >
                            </div>
                        </div>
                    </div>
                </div>

                <DialogFooter>
                    <Button variant="outline" @click="editing = null"
                        >Cancel</Button
                    >
                    <Button @click="savePermissions">Save</Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <!-- Create role dialog -->
        <Dialog
            :open="creating"
            @update:open="
                (v) => {
                    if (!v) creating = false;
                }
            "
        >
            <DialogContent class="max-h-[80vh] overflow-y-auto sm:max-w-lg">
                <DialogHeader>
                    <DialogTitle>New Role</DialogTitle>
                    <DialogDescription
                        >Name your role and assign initial
                        permissions.</DialogDescription
                    >
                </DialogHeader>

                <div class="space-y-4 py-2">
                    <div class="space-y-1.5">
                        <Label for="role-name">Role name</Label>
                        <Input
                            id="role-name"
                            v-model="newName"
                            placeholder="e.g. moderator"
                        />
                    </div>

                    <div class="space-y-3">
                        <p class="text-sm font-medium">Permissions</p>
                        <div
                            v-for="[group, perms] in permissionGroups"
                            :key="group"
                            class="rounded-md border"
                        >
                            <button
                                type="button"
                                class="flex w-full items-center gap-3 px-3 py-2 text-sm font-medium hover:bg-muted/50"
                                @click="toggleGroup(group)"
                            >
                                <Checkbox
                                    :id="`new-group-${group}`"
                                    :checked="
                                        perms.every((p) =>
                                            newPerms.includes(p),
                                        ) ||
                                        (perms.some((p) => newPerms.includes(p))
                                            ? 'indeterminate'
                                            : false)
                                    "
                                    @update:checked="
                                        (v: boolean) =>
                                            v
                                                ? newPerms.push(
                                                      ...perms.filter(
                                                          (p) =>
                                                              !newPerms.includes(
                                                                  p,
                                                              ),
                                                      ),
                                                  )
                                                : (newPerms = newPerms.filter(
                                                      (p) => !perms.includes(p),
                                                  ))
                                    "
                                    @click.stop
                                />
                                <span class="flex-1 text-left capitalize">{{
                                    group
                                }}</span>
                                <ChevronDown
                                    v-if="expandedGroups.has(group)"
                                    class="h-4 w-4 text-muted-foreground"
                                />
                                <ChevronRight
                                    v-else
                                    class="h-4 w-4 text-muted-foreground"
                                />
                            </button>
                            <div
                                v-if="expandedGroups.has(group)"
                                class="divide-y border-t"
                            >
                                <div
                                    v-for="perm in perms"
                                    :key="perm"
                                    class="flex items-center gap-3 px-3 py-2 pl-9"
                                >
                                    <Checkbox
                                        :id="`new-perm-${perm}`"
                                        :checked="newPerms.includes(perm)"
                                        @update:checked="
                                            (v: boolean) =>
                                                v
                                                    ? newPerms.push(perm)
                                                    : (newPerms =
                                                          newPerms.filter(
                                                              (p) => p !== perm,
                                                          ))
                                        "
                                    />
                                    <Label
                                        :for="`new-perm-${perm}`"
                                        class="cursor-pointer text-sm"
                                        >{{ perm }}</Label
                                    >
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <DialogFooter>
                    <Button variant="outline" @click="creating = false"
                        >Cancel</Button
                    >
                    <Button :disabled="!newName.trim()" @click="createRole"
                        >Create</Button
                    >
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </AppLayout>
</template>
