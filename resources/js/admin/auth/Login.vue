<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AuthLayout from '@/layouts/AuthLayout.vue';

const props = defineProps<{
    canResetPassword: boolean;
    status?: string | null;
}>();

const form = useForm({
    email: '',
    password: '',
    remember: false,
});

function submit() {
    form.post('/login', {
        onFinish: () => form.reset('password'),
    });
}
</script>

<template>
    <Head title="Sign in" />

    <AuthLayout
        title="Sign in to admin"
        description="Use your account to access the CMS."
    >
        <div
            v-if="props.status"
            class="mb-4 rounded-md border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm text-emerald-700"
        >
            {{ props.status }}
        </div>

        <form class="space-y-4" @submit.prevent="submit">
            <div class="space-y-2">
                <Label for="email">Email</Label>
                <Input
                    id="email"
                    v-model="form.email"
                    type="email"
                    autocomplete="email"
                    autofocus
                />
                <InputError :message="form.errors.email" />
            </div>

            <div class="space-y-2">
                <div class="flex items-center justify-between gap-3">
                    <Label for="password">Password</Label>
                    <Link
                        v-if="props.canResetPassword"
                        href="/forgot-password"
                        class="text-sm text-muted-foreground hover:text-foreground"
                    >
                        Forgot password?
                    </Link>
                </div>
                <Input
                    id="password"
                    v-model="form.password"
                    type="password"
                    autocomplete="current-password"
                />
                <InputError :message="form.errors.password" />
            </div>

            <label
                class="flex items-center gap-2 text-sm text-muted-foreground"
            >
                <input
                    v-model="form.remember"
                    type="checkbox"
                    class="h-4 w-4 rounded border-input text-primary"
                />
                Remember me
            </label>

            <Button type="submit" class="w-full" :disabled="form.processing">
                Sign in
            </Button>
        </form>
    </AuthLayout>
</template>
