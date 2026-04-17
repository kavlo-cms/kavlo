<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { KeyRound, Save, User as UserIcon } from 'lucide-vue-next';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import admin from '@/routes/admin';
import type { BreadcrumbItem } from '@/types';

interface AccountUser {
    id: number;
    name: string;
    email: string;
    email_verified_at: string | null;
    created_at: string;
}

const props = defineProps<{ user: AccountUser }>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Account Settings', href: admin.account.index.url() },
];

// ── Profile form ────────────────────────────────────────────────────────────
const profileForm = useForm({
    name:  props.user.name,
    email: props.user.email,
});

function saveProfile() {
    profileForm.put(admin.account.profile.url(), { preserveScroll: true });
}

// ── Password form ────────────────────────────────────────────────────────────
const passwordForm = useForm({
    current_password:      '',
    password:              '',
    password_confirmation: '',
});

function savePassword() {
    passwordForm.put(admin.account.password.url(), {
        preserveScroll: true,
        onSuccess: () => passwordForm.reset(),
    });
}
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex items-center justify-between">
            <h1 class="text-2xl font-semibold tracking-tight">Account Settings</h1>
        </div>

        <div class="grid gap-6 lg:grid-cols-3">
            <!-- Main column -->
            <div class="space-y-6 lg:col-span-2">

                <!-- Profile -->
                <Card>
                    <CardHeader>
                        <CardTitle class="flex items-center gap-2">
                            <UserIcon class="h-4 w-4" />
                            Profile
                        </CardTitle>
                        <CardDescription>Update your name and email address.</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <form @submit.prevent="saveProfile" class="space-y-4">
                            <div class="space-y-1.5">
                                <Label for="name">Name</Label>
                                <Input
                                    id="name"
                                    v-model="profileForm.name"
                                    placeholder="Your name"
                                    autocomplete="name"
                                />
                                <p v-if="profileForm.errors.name" class="text-sm text-destructive">
                                    {{ profileForm.errors.name }}
                                </p>
                            </div>

                            <div class="space-y-1.5">
                                <Label for="email">Email</Label>
                                <Input
                                    id="email"
                                    v-model="profileForm.email"
                                    type="email"
                                    placeholder="you@example.com"
                                    autocomplete="email"
                                />
                                <p v-if="profileForm.errors.email" class="text-sm text-destructive">
                                    {{ profileForm.errors.email }}
                                </p>
                                <p
                                    v-if="user.email_verified_at === null"
                                    class="text-xs text-amber-500"
                                >
                                    Your email address is unverified.
                                </p>
                            </div>

                            <div class="flex justify-end pt-1">
                                <Button type="submit" :disabled="profileForm.processing">
                                    <Save class="mr-2 h-4 w-4" />
                                    Save Profile
                                </Button>
                            </div>
                        </form>
                    </CardContent>
                </Card>

                <!-- Password -->
                <Card>
                    <CardHeader>
                        <CardTitle class="flex items-center gap-2">
                            <KeyRound class="h-4 w-4" />
                            Change Password
                        </CardTitle>
                        <CardDescription>Use a strong, unique password.</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <form @submit.prevent="savePassword" class="space-y-4">
                            <div class="space-y-1.5">
                                <Label for="current_password">Current Password</Label>
                                <Input
                                    id="current_password"
                                    v-model="passwordForm.current_password"
                                    type="password"
                                    autocomplete="current-password"
                                />
                                <p v-if="passwordForm.errors.current_password" class="text-sm text-destructive">
                                    {{ passwordForm.errors.current_password }}
                                </p>
                            </div>

                            <div class="space-y-1.5">
                                <Label for="password">New Password</Label>
                                <Input
                                    id="password"
                                    v-model="passwordForm.password"
                                    type="password"
                                    autocomplete="new-password"
                                />
                                <p v-if="passwordForm.errors.password" class="text-sm text-destructive">
                                    {{ passwordForm.errors.password }}
                                </p>
                            </div>

                            <div class="space-y-1.5">
                                <Label for="password_confirmation">Confirm New Password</Label>
                                <Input
                                    id="password_confirmation"
                                    v-model="passwordForm.password_confirmation"
                                    type="password"
                                    autocomplete="new-password"
                                />
                            </div>

                            <div class="flex justify-end pt-1">
                                <Button type="submit" :disabled="passwordForm.processing">
                                    <Save class="mr-2 h-4 w-4" />
                                    Update Password
                                </Button>
                            </div>
                        </form>
                    </CardContent>
                </Card>
            </div>

            <!-- Sidebar summary -->
            <div class="space-y-4">
                <Card>
                    <CardHeader>
                        <CardTitle class="text-sm font-medium">Account Info</CardTitle>
                    </CardHeader>
                    <CardContent class="space-y-3 text-sm">
                        <div>
                            <p class="text-muted-foreground">Name</p>
                            <p class="font-medium">{{ user.name }}</p>
                        </div>
                        <div>
                            <p class="text-muted-foreground">Email</p>
                            <p class="font-medium">{{ user.email }}</p>
                        </div>
                        <div>
                            <p class="text-muted-foreground">Email status</p>
                            <p
                                class="font-medium"
                                :class="user.email_verified_at ? 'text-green-500' : 'text-amber-500'"
                            >
                                {{ user.email_verified_at ? 'Verified' : 'Unverified' }}
                            </p>
                        </div>
                        <div>
                            <p class="text-muted-foreground">Member since</p>
                            <p class="font-medium">
                                {{ new Date(user.created_at).toLocaleDateString() }}
                            </p>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </div>
    </AppLayout>
</template>
