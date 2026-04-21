<script setup lang="ts">
import { router, useForm } from '@inertiajs/vue3';
import {
    KeyRound,
    RefreshCw,
    Save,
    ShieldCheck,
    Trash2,
    User as UserIcon,
} from 'lucide-vue-next';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
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

interface ApiKeyAbility {
    key: string;
    label: string;
    description: string;
}

interface AccountApiKey {
    id: number;
    name: string;
    key_prefix: string;
    abilities: string[];
    last_used_at: string | null;
    last_used_ip: string | null;
    expires_at: string | null;
    revoked_at: string | null;
    created_at: string;
    status: 'active' | 'expired' | 'revoked';
}

interface GeneratedApiKey {
    id: number;
    name: string;
    token: string;
}

const props = defineProps<{
    user: AccountUser;
    apiKeys: AccountApiKey[];
    apiKeyAbilities: ApiKeyAbility[];
    generatedApiKey: GeneratedApiKey | null;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Account Settings', href: admin.account.index.url() },
];

// ── Profile form ────────────────────────────────────────────────────────────
const profileForm = useForm({
    name: props.user.name,
    email: props.user.email,
});

function saveProfile() {
    profileForm.put(admin.account.profile.url(), { preserveScroll: true });
}

// ── Password form ────────────────────────────────────────────────────────────
const passwordForm = useForm({
    current_password: '',
    password: '',
    password_confirmation: '',
});

function savePassword() {
    passwordForm.put(admin.account.password.url(), {
        preserveScroll: true,
        onSuccess: () => passwordForm.reset(),
    });
}

const apiKeysBaseUrl = '/admin/account/api-keys';

const apiKeyForm = useForm({
    name: '',
    abilities: ['graphql.read'],
    expires_at: '',
});

function toggleAbility(ability: string, checked: boolean) {
    if (checked) {
        if (!apiKeyForm.abilities.includes(ability)) {
            apiKeyForm.abilities.push(ability);
        }

        if (
            ability === 'graphql.preview' &&
            !apiKeyForm.abilities.includes('graphql.read')
        ) {
            apiKeyForm.abilities.push('graphql.read');
        }

        return;
    }

    if (
        ability === 'graphql.read' &&
        apiKeyForm.abilities.includes('graphql.preview')
    ) {
        return;
    }

    apiKeyForm.abilities = apiKeyForm.abilities.filter(
        (value) => value !== ability,
    );
}

function createApiKey() {
    apiKeyForm.post(apiKeysBaseUrl, {
        preserveScroll: true,
        onSuccess: () => apiKeyForm.reset('name', 'expires_at'),
    });
}

function revokeApiKey(id: number) {
    router.delete(`${apiKeysBaseUrl}/${id}`, {
        preserveScroll: true,
    });
}

function rotateApiKey(id: number) {
    router.post(
        `${apiKeysBaseUrl}/${id}/rotate`,
        {},
        {
            preserveScroll: true,
        },
    );
}

function formatDate(value: string | null) {
    return value ? new Date(value).toLocaleString() : 'Never';
}

function statusClass(status: AccountApiKey['status']) {
    return {
        active: 'bg-emerald-500/10 text-emerald-600',
        expired: 'bg-amber-500/10 text-amber-600',
        revoked: 'bg-muted text-muted-foreground',
    }[status];
}
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex items-center justify-between">
            <h1 class="text-2xl font-semibold tracking-tight">
                Account Settings
            </h1>
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
                        <CardDescription
                            >Update your name and email
                            address.</CardDescription
                        >
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
                                <p
                                    v-if="profileForm.errors.name"
                                    class="text-sm text-destructive"
                                >
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
                                <p
                                    v-if="profileForm.errors.email"
                                    class="text-sm text-destructive"
                                >
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
                                <Button
                                    type="submit"
                                    :disabled="profileForm.processing"
                                >
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
                        <CardDescription
                            >Use a strong, unique password.</CardDescription
                        >
                    </CardHeader>
                    <CardContent>
                        <form @submit.prevent="savePassword" class="space-y-4">
                            <div class="space-y-1.5">
                                <Label for="current_password"
                                    >Current Password</Label
                                >
                                <Input
                                    id="current_password"
                                    v-model="passwordForm.current_password"
                                    type="password"
                                    autocomplete="current-password"
                                />
                                <p
                                    v-if="passwordForm.errors.current_password"
                                    class="text-sm text-destructive"
                                >
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
                                <p
                                    v-if="passwordForm.errors.password"
                                    class="text-sm text-destructive"
                                >
                                    {{ passwordForm.errors.password }}
                                </p>
                            </div>

                            <div class="space-y-1.5">
                                <Label for="password_confirmation"
                                    >Confirm New Password</Label
                                >
                                <Input
                                    id="password_confirmation"
                                    v-model="passwordForm.password_confirmation"
                                    type="password"
                                    autocomplete="new-password"
                                />
                            </div>

                            <div class="flex justify-end pt-1">
                                <Button
                                    type="submit"
                                    :disabled="passwordForm.processing"
                                >
                                    <Save class="mr-2 h-4 w-4" />
                                    Update Password
                                </Button>
                            </div>
                        </form>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle class="flex items-center gap-2">
                            <ShieldCheck class="h-4 w-4" />
                            API Keys
                        </CardTitle>
                        <CardDescription>
                            Create personal keys for DataHub and GraphQL access.
                            Secrets are shown only once.
                        </CardDescription>
                    </CardHeader>
                    <CardContent class="space-y-6">
                        <div
                            v-if="generatedApiKey"
                            class="space-y-2 rounded-lg border border-emerald-500/40 bg-emerald-500/5 p-4"
                        >
                            <div>
                                <p class="text-sm font-medium">
                                    New API key created
                                </p>
                                <p class="text-sm text-muted-foreground">
                                    Copy this token now. It will not be shown
                                    again for
                                    <span class="font-medium">{{
                                        generatedApiKey.name
                                    }}</span
                                    >.
                                </p>
                            </div>
                            <Input
                                :model-value="generatedApiKey.token"
                                readonly
                            />
                        </div>

                        <form
                            @submit.prevent="createApiKey"
                            class="space-y-4 rounded-lg border p-4"
                        >
                            <div class="space-y-1.5">
                                <Label for="api_key_name">Key name</Label>
                                <Input
                                    id="api_key_name"
                                    v-model="apiKeyForm.name"
                                    placeholder="DataHub integration"
                                />
                                <p
                                    v-if="apiKeyForm.errors.name"
                                    class="text-sm text-destructive"
                                >
                                    {{ apiKeyForm.errors.name }}
                                </p>
                            </div>

                            <div class="space-y-3">
                                <Label>Scopes</Label>
                                <div
                                    v-for="ability in apiKeyAbilities"
                                    :key="ability.key"
                                    class="flex items-start gap-3 rounded-md border p-3"
                                >
                                    <Checkbox
                                        :id="ability.key"
                                        :checked="
                                            apiKeyForm.abilities.includes(
                                                ability.key,
                                            )
                                        "
                                        @update:checked="
                                            (value: boolean) =>
                                                toggleAbility(
                                                    ability.key,
                                                    value,
                                                )
                                        "
                                    />
                                    <div class="space-y-1">
                                        <Label
                                            :for="ability.key"
                                            class="font-medium"
                                            >{{ ability.label }}</Label
                                        >
                                        <p
                                            class="text-sm text-muted-foreground"
                                        >
                                            {{ ability.description }}
                                        </p>
                                    </div>
                                </div>
                                <p
                                    v-if="apiKeyForm.errors.abilities"
                                    class="text-sm text-destructive"
                                >
                                    {{ apiKeyForm.errors.abilities }}
                                </p>
                            </div>

                            <div class="space-y-1.5">
                                <Label for="api_key_expires_at"
                                    >Expires at</Label
                                >
                                <Input
                                    id="api_key_expires_at"
                                    v-model="apiKeyForm.expires_at"
                                    type="datetime-local"
                                />
                                <p class="text-xs text-muted-foreground">
                                    Leave blank to keep the key active until it
                                    is revoked.
                                </p>
                                <p
                                    v-if="apiKeyForm.errors.expires_at"
                                    class="text-sm text-destructive"
                                >
                                    {{ apiKeyForm.errors.expires_at }}
                                </p>
                            </div>

                            <div class="flex justify-end">
                                <Button
                                    type="submit"
                                    :disabled="apiKeyForm.processing"
                                >
                                    <KeyRound class="mr-2 h-4 w-4" />
                                    Create API Key
                                </Button>
                            </div>
                        </form>

                        <div class="space-y-3">
                            <div class="flex items-center justify-between">
                                <h3 class="text-sm font-medium">
                                    Existing keys
                                </h3>
                                <p class="text-sm text-muted-foreground">
                                    {{ apiKeys.length }} total
                                </p>
                            </div>

                            <div
                                v-if="apiKeys.length === 0"
                                class="rounded-lg border border-dashed p-4 text-sm text-muted-foreground"
                            >
                                No API keys created yet.
                            </div>

                            <div v-else class="space-y-3">
                                <div
                                    v-for="apiKey in apiKeys"
                                    :key="apiKey.id"
                                    class="rounded-lg border p-4"
                                >
                                    <div
                                        class="flex flex-col gap-4 md:flex-row md:items-start md:justify-between"
                                    >
                                        <div class="space-y-2">
                                            <div
                                                class="flex items-center gap-2"
                                            >
                                                <p class="font-medium">
                                                    {{ apiKey.name }}
                                                </p>
                                                <span
                                                    class="rounded-full px-2 py-0.5 text-xs"
                                                    :class="
                                                        statusClass(
                                                            apiKey.status,
                                                        )
                                                    "
                                                >
                                                    {{
                                                        apiKey.status
                                                            .charAt(0)
                                                            .toUpperCase() +
                                                        apiKey.status.slice(1)
                                                    }}
                                                </span>
                                            </div>
                                            <p
                                                class="font-mono text-xs text-muted-foreground"
                                            >
                                                {{ apiKey.key_prefix }}...
                                            </p>
                                            <div class="flex flex-wrap gap-2">
                                                <span
                                                    v-for="ability in apiKey.abilities"
                                                    :key="ability"
                                                    class="rounded-full bg-muted px-2 py-0.5 text-xs text-muted-foreground"
                                                >
                                                    {{ ability }}
                                                </span>
                                            </div>
                                            <div
                                                class="grid gap-1 text-sm text-muted-foreground"
                                            >
                                                <p>
                                                    Created:
                                                    {{
                                                        formatDate(
                                                            apiKey.created_at,
                                                        )
                                                    }}
                                                </p>
                                                <p>
                                                    Last used:
                                                    {{
                                                        formatDate(
                                                            apiKey.last_used_at,
                                                        )
                                                    }}
                                                </p>
                                                <p>
                                                    Last IP:
                                                    {{
                                                        apiKey.last_used_ip ??
                                                        'Unknown'
                                                    }}
                                                </p>
                                                <p>
                                                    Expires:
                                                    {{
                                                        formatDate(
                                                            apiKey.expires_at,
                                                        )
                                                    }}
                                                </p>
                                            </div>
                                        </div>

                                        <div
                                            v-if="apiKey.status === 'active'"
                                            class="flex shrink-0 gap-2"
                                        >
                                            <Button
                                                type="button"
                                                variant="outline"
                                                @click="rotateApiKey(apiKey.id)"
                                            >
                                                <RefreshCw
                                                    class="mr-2 h-4 w-4"
                                                />
                                                Rotate
                                            </Button>
                                            <Button
                                                type="button"
                                                variant="outline"
                                                @click="revokeApiKey(apiKey.id)"
                                            >
                                                <Trash2 class="mr-2 h-4 w-4" />
                                                Revoke
                                            </Button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </CardContent>
                </Card>
            </div>

            <!-- Sidebar summary -->
            <div class="space-y-4">
                <Card>
                    <CardHeader>
                        <CardTitle class="text-sm font-medium"
                            >Account Info</CardTitle
                        >
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
                                :class="
                                    user.email_verified_at
                                        ? 'text-green-500'
                                        : 'text-amber-500'
                                "
                            >
                                {{
                                    user.email_verified_at
                                        ? 'Verified'
                                        : 'Unverified'
                                }}
                            </p>
                        </div>
                        <div>
                            <p class="text-muted-foreground">Member since</p>
                            <p class="font-medium">
                                {{
                                    new Date(
                                        user.created_at,
                                    ).toLocaleDateString()
                                }}
                            </p>
                        </div>
                        <div>
                            <p class="text-muted-foreground">Active API keys</p>
                            <p class="font-medium">
                                {{
                                    apiKeys.filter(
                                        (apiKey) => !apiKey.revoked_at,
                                    ).length
                                }}
                            </p>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </div>
    </AppLayout>
</template>
