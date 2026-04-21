<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AuthLayout from '@/layouts/AuthLayout.vue';

const form = useForm({
    password: '',
});

function submit() {
    form.post('/user/confirm-password', {
        onFinish: () => form.reset('password'),
    });
}
</script>

<template>
    <Head title="Confirm password" />

    <AuthLayout
        title="Confirm your password"
        description="Re-enter your password to continue."
    >
        <form class="space-y-4" @submit.prevent="submit">
            <div class="space-y-2">
                <Label for="password">Password</Label>
                <Input
                    id="password"
                    v-model="form.password"
                    type="password"
                    autocomplete="current-password"
                    autofocus
                />
                <InputError :message="form.errors.password" />
            </div>

            <Button type="submit" class="w-full" :disabled="form.processing">
                Confirm password
            </Button>
        </form>
    </AuthLayout>
</template>
