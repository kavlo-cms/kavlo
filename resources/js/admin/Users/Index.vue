<script setup lang="ts">
import { router, usePage } from '@inertiajs/vue3';
import { ChevronDown, ChevronRight, Plus, ShieldCheck, Trash2, Users } from 'lucide-vue-next';
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
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import AppLayout from '@/layouts/AppLayout.vue';
import admin from '@/routes/admin';
import type { BreadcrumbItem } from '@/types';
import { computed, ref } from 'vue';

interface User {
    id: number;
    name: string;
    email: string;
    roles: string[];
    direct_permissions: string[];
    created_at: string;
}

interface Role {
    id: number;
    name: string;
    permissions: string[];
    users_count: number;
}

interface Permission {
    id: number;
    name: string;
}

const props = defineProps<{
    users: User[];
    roles: string[];
    allRoles: Role[];
    permissions: Permission[];
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Admin', href: admin.dashboard.url() },
    { title: 'Users & Roles', href: admin.users.index.url() },
];

const page = usePage<{ auth: { user: { id: number } } }>();

const BUILTIN = ['super-admin', 'admin', 'editor', 'author'];
const permissionNames = computed(() => props.permissions.map((p) => p.name));

// ── Permission groups ──────────────────────────────────────────────────────
const permissionGroups = computed(() => {
    const groups: Record<string, string[]> = {};
    for (const p of props.permissions) {
        const parts = p.name.split(' ');
        const group = parts.length > 1 ? parts.slice(1).join(' ') : 'other';
        (groups[group] ??= []).push(p.name);
    }
    return Object.entries(groups).sort(([a], [b]) => a.localeCompare(b));
});

const expandedGroups = ref<Set<string>>(new Set());
function toggleGroup(group: string) {
    if (expandedGroups.value.has(group)) expandedGroups.value.delete(group);
    else expandedGroups.value.add(group);
}

// ── Users: edit roles ──────────────────────────────────────────────────────
const editingUser = ref<User | null>(null);
const selectedRoles = ref<string[]>([]);
function openEditUser(user: User) { editingUser.value = user; selectedRoles.value = [...user.roles]; }
function saveUserRoles() {
    if (!editingUser.value) return;
    router.put(admin.users.updateRoles.url({ user: editingUser.value.id }), { roles: selectedRoles.value }, {
        preserveScroll: true, onSuccess: () => { editingUser.value = null; },
    });
}

// ── Users: edit direct permissions ────────────────────────────────────────
const editingUserPerms = ref<User | null>(null);
const selectedUserPerms = ref<string[]>([]);
function openEditUserPerms(user: User) {
    editingUserPerms.value = user;
    selectedUserPerms.value = [...user.direct_permissions];
    expandedGroups.value = new Set(permissionGroups.value.map(([g]) => g));
}
function saveUserPerms() {
    if (!editingUserPerms.value) return;
    router.put(admin.users.updatePermissions.url({ user: editingUserPerms.value.id }), { permissions: selectedUserPerms.value }, {
        preserveScroll: true, onSuccess: () => { editingUserPerms.value = null; },
    });
}
function groupUserPermsState(groupPerms: string[]): boolean | 'indeterminate' {
    const n = groupPerms.filter((p) => selectedUserPerms.value.includes(p)).length;
    if (n === 0) return false;
    if (n === groupPerms.length) return true;
    return 'indeterminate';
}
function toggleGroupUserPerms(groupPerms: string[], checked: boolean) {
    if (checked) selectedUserPerms.value.push(...groupPerms.filter((p) => !selectedUserPerms.value.includes(p)));
    else selectedUserPerms.value = selectedUserPerms.value.filter((p) => !groupPerms.includes(p));
}

// ── Users: create ──────────────────────────────────────────────────────────
const creatingUser = ref(false);
const newUser = ref({ name: '', email: '', password: '', password_confirmation: '', roles: [] as string[] });
function openCreateUser() { newUser.value = { name: '', email: '', password: '', password_confirmation: '', roles: [] }; creatingUser.value = true; }
function createUser() {
    router.post(admin.users.store.url(), newUser.value, { preserveScroll: true, onSuccess: () => { creatingUser.value = false; } });
}

// ── Users: delete ──────────────────────────────────────────────────────────
function destroyUser(user: User) {
    if (!confirm(`Delete ${user.name}? This cannot be undone.`)) return;
    router.delete(admin.users.destroy.url({ user: user.id }), { preserveScroll: true });
}

// ── Roles: edit permissions ────────────────────────────────────────────────
const editingRole = ref<Role | null>(null);
const editPerms = ref<string[]>([]);
function openEditRole(role: Role) {
    editingRole.value = role; editPerms.value = [...role.permissions];
    expandedGroups.value = new Set(permissionGroups.value.map(([g]) => g));
}
function groupCheckedState(groupPerms: string[]): boolean | 'indeterminate' {
    const n = groupPerms.filter((p) => editPerms.value.includes(p)).length;
    if (n === 0) return false;
    if (n === groupPerms.length) return true;
    return 'indeterminate';
}
function toggleGroupPerms(groupPerms: string[], checked: boolean) {
    if (checked) editPerms.value.push(...groupPerms.filter((p) => !editPerms.value.includes(p)));
    else editPerms.value = editPerms.value.filter((p) => !groupPerms.includes(p));
}
function saveRolePermissions() {
    if (!editingRole.value) return;
    router.put(admin.roles.update.url({ role: editingRole.value.id }), { permissions: editPerms.value }, {
        preserveScroll: true, onSuccess: () => { editingRole.value = null; },
    });
}

// ── Roles: create ──────────────────────────────────────────────────────────
const creatingRole = ref(false);
const newRole = ref({ name: '', permissions: [] as string[] });
function openCreateRole() { newRole.value = { name: '', permissions: [] }; expandedGroups.value = new Set(); creatingRole.value = true; }
function createRole() {
    router.post(admin.roles.store.url(), newRole.value, { preserveScroll: true, onSuccess: () => { creatingRole.value = false; } });
}

// ── Roles: delete ──────────────────────────────────────────────────────────
function destroyRole(role: Role) {
    if (!confirm(`Delete role "${role.name}"? This cannot be undone.`)) return;
    router.delete(admin.roles.destroy.url({ role: role.id }), { preserveScroll: true });
}

// ── Permissions: create ────────────────────────────────────────────────────
const creatingPermission = ref(false);
const newPermissionName = ref('');
function openCreatePermission() { newPermissionName.value = ''; creatingPermission.value = true; }
function createPermission() {
    router.post(admin.permissions.store.url(), { name: newPermissionName.value }, {
        preserveScroll: true, onSuccess: () => { creatingPermission.value = false; },
    });
}

// ── Permissions: delete ────────────────────────────────────────────────────
function destroyPermission(p: Permission) {
    if (!confirm(`Delete permission "${p.name}"? Roles and users with this permission will lose it.`)) return;
    router.delete(admin.permissions.destroy.url({ permission: p.id }), { preserveScroll: true });
}

const userRoleVariant = (role: string): 'default' | 'secondary' | 'outline' => {
    if (role === 'super-admin' || role === 'admin') return 'default';
    if (role === 'editor') return 'secondary';
    return 'outline';
};
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <h1 class="text-2xl font-semibold tracking-tight">Users &amp; Roles</h1>

        <Tabs default-value="users">
            <TabsList>
                <TabsTrigger value="users">
                    <Users class="mr-1.5 h-4 w-4" />Users
                    <Badge variant="secondary" class="ml-1.5 text-xs">{{ props.users.length }}</Badge>
                </TabsTrigger>
                <TabsTrigger value="roles">
                    <ShieldCheck class="mr-1.5 h-4 w-4" />Roles
                    <Badge variant="secondary" class="ml-1.5 text-xs">{{ props.allRoles.length }}</Badge>
                </TabsTrigger>
                <TabsTrigger value="permissions">
                    Permissions
                    <Badge variant="secondary" class="ml-1.5 text-xs">{{ props.permissions.length }}</Badge>
                </TabsTrigger>
            </TabsList>

            <!-- ── Users tab ──────────────────────────────────────────── -->
            <TabsContent value="users" class="space-y-3 pt-4">
                <div class="flex justify-end">
                    <Button size="sm" @click="openCreateUser"><Plus class="mr-1.5 h-4 w-4" />New User</Button>
                </div>
                <div v-if="props.users.length === 0" class="flex flex-col items-center justify-center rounded-lg border border-dashed py-16 text-center">
                    <Users class="mb-3 h-10 w-10 text-muted-foreground/40" />
                    <p class="text-sm text-muted-foreground">No users yet.</p>
                </div>
                <Card v-for="user in props.users" :key="user.id">
                    <CardHeader class="pb-2">
                        <div class="flex items-start justify-between gap-4">
                            <div class="min-w-0 flex-1">
                                <div class="flex flex-wrap items-center gap-2">
                                    <CardTitle class="text-base">{{ user.name }}</CardTitle>
                                    <Badge v-for="role in user.roles" :key="role" :variant="userRoleVariant(role)" class="text-xs capitalize">{{ role }}</Badge>
                                </div>
                                <p class="mt-0.5 text-sm text-muted-foreground">{{ user.email }}</p>
                            </div>
                            <div class="flex shrink-0 gap-2">
                                <Button variant="outline" size="sm" @click="openEditUser(user)">Roles</Button>
                                <Button variant="outline" size="sm" @click="openEditUserPerms(user)">Permissions</Button>
                                <Button variant="destructive" size="sm" :disabled="user.id === page.props.auth.user.id" @click="destroyUser(user)">
                                    <Trash2 class="h-4 w-4" />
                                </Button>
                            </div>
                        </div>
                    </CardHeader>
                    <CardContent v-if="user.direct_permissions.length" class="pb-3 pt-0">
                        <p class="mb-1.5 text-xs font-medium text-muted-foreground">Direct permissions</p>
                        <div class="flex flex-wrap gap-1.5">
                            <Badge v-for="perm in user.direct_permissions" :key="perm" variant="outline" class="text-xs font-normal">{{ perm }}</Badge>
                        </div>
                    </CardContent>
                </Card>
            </TabsContent>

            <!-- ── Roles tab ──────────────────────────────────────────── -->
            <TabsContent value="roles" class="space-y-3 pt-4">
                <div class="flex justify-end">
                    <Button size="sm" @click="openCreateRole"><Plus class="mr-1.5 h-4 w-4" />New Role</Button>
                </div>
                <Card v-for="role in props.allRoles" :key="role.id">
                    <CardHeader class="pb-2">
                        <div class="flex items-start justify-between gap-4">
                            <div class="min-w-0 flex-1">
                                <div class="flex flex-wrap items-center gap-2">
                                    <CardTitle class="flex items-center gap-1.5 text-base capitalize">
                                        <ShieldCheck class="h-4 w-4 text-muted-foreground" />{{ role.name }}
                                    </CardTitle>
                                    <Badge variant="secondary" class="text-xs">{{ role.users_count }} user{{ role.users_count === 1 ? '' : 's' }}</Badge>
                                    <Badge v-if="BUILTIN.includes(role.name)" variant="outline" class="text-xs">built-in</Badge>
                                </div>
                            </div>
                            <div class="flex shrink-0 gap-2">
                                <Button variant="outline" size="sm" :disabled="role.name === 'super-admin'" @click="openEditRole(role)">Edit Permissions</Button>
                                <Button variant="destructive" size="sm" :disabled="BUILTIN.includes(role.name)" @click="destroyRole(role)">
                                    <Trash2 class="h-4 w-4" />
                                </Button>
                            </div>
                        </div>
                    </CardHeader>
                    <CardContent v-if="role.permissions.length" class="pb-3 pt-0">
                        <div class="flex flex-wrap gap-1.5">
                            <Badge v-for="perm in role.permissions" :key="perm" variant="secondary" class="text-xs font-normal">{{ perm }}</Badge>
                        </div>
                    </CardContent>
                    <CardContent v-else class="pb-3 pt-0 text-xs italic text-muted-foreground">No permissions assigned.</CardContent>
                </Card>
            </TabsContent>

            <!-- ── Permissions tab ────────────────────────────────────── -->
            <TabsContent value="permissions" class="space-y-3 pt-4">
                <div class="flex justify-end">
                    <Button size="sm" @click="openCreatePermission"><Plus class="mr-1.5 h-4 w-4" />New Permission</Button>
                </div>
                <Card>
                    <CardContent class="pt-4">
                        <div v-if="props.permissions.length === 0" class="py-8 text-center text-sm text-muted-foreground italic">No permissions defined.</div>
                        <div v-else class="divide-y">
                            <div v-for="p in props.permissions" :key="p.id" class="flex items-center justify-between py-2">
                                <span class="text-sm">{{ p.name }}</span>
                                <Button variant="ghost" size="sm" class="h-7 w-7 p-0 text-destructive hover:bg-destructive/10" @click="destroyPermission(p)">
                                    <Trash2 class="h-3.5 w-3.5" />
                                </Button>
                            </div>
                        </div>
                    </CardContent>
                </Card>
            </TabsContent>
        </Tabs>

        <!-- Edit user roles dialog -->
        <Dialog :open="!!editingUser" @update:open="(v) => { if (!v) editingUser = null; }">
            <DialogContent class="sm:max-w-sm">
                <DialogHeader>
                    <DialogTitle>Roles — {{ editingUser?.name }}</DialogTitle>
                    <DialogDescription>Select one or more roles for this user.</DialogDescription>
                </DialogHeader>
                <div class="space-y-3 py-2">
                    <div v-for="role in props.roles" :key="role" class="flex items-center gap-3">
                        <Checkbox :id="`urole-${role}`" :checked="selectedRoles.includes(role)"
                            @update:checked="(v) => v ? selectedRoles.push(role) : selectedRoles.splice(selectedRoles.indexOf(role), 1)" />
                        <Label :for="`urole-${role}`" class="cursor-pointer capitalize">{{ role }}</Label>
                    </div>
                </div>
                <DialogFooter>
                    <Button variant="outline" @click="editingUser = null">Cancel</Button>
                    <Button @click="saveUserRoles">Save</Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <!-- Edit user direct permissions dialog -->
        <Dialog :open="!!editingUserPerms" @update:open="(v) => { if (!v) editingUserPerms = null; }">
            <DialogContent class="max-h-[80vh] overflow-y-auto sm:max-w-lg">
                <DialogHeader>
                    <DialogTitle>Direct Permissions — {{ editingUserPerms?.name }}</DialogTitle>
                    <DialogDescription>These are granted in addition to any permissions from roles.</DialogDescription>
                </DialogHeader>
                <div class="space-y-3 py-2">
                    <div v-for="([group, perms]) in permissionGroups" :key="group" class="rounded-md border">
                        <button type="button" class="flex w-full items-center gap-3 px-3 py-2 text-sm font-medium hover:bg-muted/50" @click="toggleGroup(group)">
                            <Checkbox :id="`upg-${group}`" :checked="groupUserPermsState(perms)"
                                @update:checked="(v) => toggleGroupUserPerms(perms, !!v)" @click.stop />
                            <span class="flex-1 text-left capitalize">{{ group }}</span>
                            <ChevronDown v-if="expandedGroups.has(group)" class="h-4 w-4 text-muted-foreground" />
                            <ChevronRight v-else class="h-4 w-4 text-muted-foreground" />
                        </button>
                        <div v-if="expandedGroups.has(group)" class="divide-y border-t">
                            <div v-for="perm in perms" :key="perm" class="flex items-center gap-3 px-3 py-2 pl-9">
                                <Checkbox :id="`upp-${perm}`" :checked="selectedUserPerms.includes(perm)"
                                    @update:checked="(v) => v ? selectedUserPerms.push(perm) : selectedUserPerms.splice(selectedUserPerms.indexOf(perm), 1)" />
                                <Label :for="`upp-${perm}`" class="cursor-pointer text-sm">{{ perm }}</Label>
                            </div>
                        </div>
                    </div>
                </div>
                <DialogFooter>
                    <Button variant="outline" @click="editingUserPerms = null">Cancel</Button>
                    <Button @click="saveUserPerms">Save</Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <!-- Create user dialog -->
        <Dialog :open="creatingUser" @update:open="(v) => { if (!v) creatingUser = false; }">
            <DialogContent class="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>New User</DialogTitle>
                    <DialogDescription>Create a new user and assign their role.</DialogDescription>
                </DialogHeader>
                <div class="space-y-4 py-2">
                    <div class="space-y-1.5"><Label for="new-name">Name</Label><Input id="new-name" v-model="newUser.name" placeholder="Jane Smith" /></div>
                    <div class="space-y-1.5"><Label for="new-email">Email</Label><Input id="new-email" v-model="newUser.email" type="email" placeholder="jane@example.com" /></div>
                    <div class="space-y-1.5"><Label for="new-pw">Password</Label><Input id="new-pw" v-model="newUser.password" type="password" /></div>
                    <div class="space-y-1.5"><Label for="new-pw2">Confirm Password</Label><Input id="new-pw2" v-model="newUser.password_confirmation" type="password" /></div>
                    <div class="space-y-2">
                        <Label>Roles</Label>
                        <div v-for="role in props.roles" :key="role" class="flex items-center gap-3">
                            <Checkbox :id="`new-urole-${role}`" :checked="newUser.roles.includes(role)"
                                @update:checked="(v) => v ? newUser.roles.push(role) : (newUser.roles = newUser.roles.filter(r => r !== role))" />
                            <Label :for="`new-urole-${role}`" class="cursor-pointer capitalize">{{ role }}</Label>
                        </div>
                    </div>
                </div>
                <DialogFooter>
                    <Button variant="outline" @click="creatingUser = false">Cancel</Button>
                    <Button :disabled="!newUser.name || !newUser.email || !newUser.password" @click="createUser">Create User</Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <!-- Edit role permissions dialog -->
        <Dialog :open="!!editingRole" @update:open="(v) => { if (!v) editingRole = null; }">
            <DialogContent class="max-h-[80vh] overflow-y-auto sm:max-w-lg">
                <DialogHeader>
                    <DialogTitle class="capitalize">Edit permissions — {{ editingRole?.name }}</DialogTitle>
                    <DialogDescription>Toggle groups or individual permissions.</DialogDescription>
                </DialogHeader>
                <div class="space-y-3 py-2">
                    <div v-for="([group, perms]) in permissionGroups" :key="group" class="rounded-md border">
                        <button type="button" class="flex w-full items-center gap-3 px-3 py-2 text-sm font-medium hover:bg-muted/50" @click="toggleGroup(group)">
                            <Checkbox :id="`eg-${group}`" :checked="groupCheckedState(perms)"
                                @update:checked="(v) => toggleGroupPerms(perms, !!v)" @click.stop />
                            <span class="flex-1 text-left capitalize">{{ group }}</span>
                            <ChevronDown v-if="expandedGroups.has(group)" class="h-4 w-4 text-muted-foreground" />
                            <ChevronRight v-else class="h-4 w-4 text-muted-foreground" />
                        </button>
                        <div v-if="expandedGroups.has(group)" class="divide-y border-t">
                            <div v-for="perm in perms" :key="perm" class="flex items-center gap-3 px-3 py-2 pl-9">
                                <Checkbox :id="`ep-${perm}`" :checked="editPerms.includes(perm)"
                                    @update:checked="(v) => v ? editPerms.push(perm) : editPerms.splice(editPerms.indexOf(perm), 1)" />
                                <Label :for="`ep-${perm}`" class="cursor-pointer text-sm">{{ perm }}</Label>
                            </div>
                        </div>
                    </div>
                </div>
                <DialogFooter>
                    <Button variant="outline" @click="editingRole = null">Cancel</Button>
                    <Button @click="saveRolePermissions">Save</Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <!-- Create role dialog -->
        <Dialog :open="creatingRole" @update:open="(v) => { if (!v) creatingRole = false; }">
            <DialogContent class="max-h-[80vh] overflow-y-auto sm:max-w-lg">
                <DialogHeader>
                    <DialogTitle>New Role</DialogTitle>
                    <DialogDescription>Name your role and assign initial permissions.</DialogDescription>
                </DialogHeader>
                <div class="space-y-4 py-2">
                    <div class="space-y-1.5"><Label for="role-name">Role name</Label><Input id="role-name" v-model="newRole.name" placeholder="e.g. moderator" /></div>
                    <div class="space-y-3">
                        <p class="text-sm font-medium">Permissions</p>
                        <div v-for="([group, perms]) in permissionGroups" :key="group" class="rounded-md border">
                            <button type="button" class="flex w-full items-center gap-3 px-3 py-2 text-sm font-medium hover:bg-muted/50" @click="toggleGroup(group)">
                                <Checkbox :id="`ng-${group}`"
                                    :checked="perms.every(p => newRole.permissions.includes(p)) || (perms.some(p => newRole.permissions.includes(p)) ? 'indeterminate' : false)"
                                    @update:checked="(v) => v ? newRole.permissions.push(...perms.filter(p => !newRole.permissions.includes(p))) : (newRole.permissions = newRole.permissions.filter(p => !perms.includes(p)))"
                                    @click.stop />
                                <span class="flex-1 text-left capitalize">{{ group }}</span>
                                <ChevronDown v-if="expandedGroups.has(group)" class="h-4 w-4 text-muted-foreground" />
                                <ChevronRight v-else class="h-4 w-4 text-muted-foreground" />
                            </button>
                            <div v-if="expandedGroups.has(group)" class="divide-y border-t">
                                <div v-for="perm in perms" :key="perm" class="flex items-center gap-3 px-3 py-2 pl-9">
                                    <Checkbox :id="`np-${perm}`" :checked="newRole.permissions.includes(perm)"
                                        @update:checked="(v) => v ? newRole.permissions.push(perm) : (newRole.permissions = newRole.permissions.filter(p => p !== perm))" />
                                    <Label :for="`np-${perm}`" class="cursor-pointer text-sm">{{ perm }}</Label>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <DialogFooter>
                    <Button variant="outline" @click="creatingRole = false">Cancel</Button>
                    <Button :disabled="!newRole.name.trim()" @click="createRole">Create Role</Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <!-- Create permission dialog -->
        <Dialog :open="creatingPermission" @update:open="(v) => { if (!v) creatingPermission = false; }">
            <DialogContent class="sm:max-w-sm">
                <DialogHeader>
                    <DialogTitle>New Permission</DialogTitle>
                    <DialogDescription>Use lowercase words separated by spaces, e.g. <code class="rounded bg-muted px-1">publish posts</code>.</DialogDescription>
                </DialogHeader>
                <div class="space-y-1.5 py-2">
                    <Label for="perm-name">Permission name</Label>
                    <Input id="perm-name" v-model="newPermissionName" placeholder="e.g. export reports" @keydown.enter="createPermission" />
                </div>
                <DialogFooter>
                    <Button variant="outline" @click="creatingPermission = false">Cancel</Button>
                    <Button :disabled="!newPermissionName.trim()" @click="createPermission">Create</Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </AppLayout>
</template>
