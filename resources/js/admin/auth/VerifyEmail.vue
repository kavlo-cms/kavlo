<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import AuthLayout from '@/layouts/AuthLayout.vue';

defineProps<{
    status?: string | null;
}>();

const form = useForm({});
</script>

<template>
    <Head title="Verify email" />

    <AuthLayout title="Verify your email" description="Check your inbox for a verification link before continuing.">
        <div
            v-if="status === 'verification-link-sent'"
            class="mb-4 rounded-md border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm text-emerald-700"
        >
            A fresh verification link has been sent to your email address.
        </div>

        <div class="space-y-4">
            <Button class="w-full" :disabled="form.processing" @click="form.post('/email/verification-notification')">
                Resend verification email
            </Button>

            <Link
                href="/logout"
                method="post"
                as="button"
                class="w-full text-sm text-muted-foreground hover:text-foreground"
            >
                Sign out
            </Link>
        </div>
    </AuthLayout>
</template>
