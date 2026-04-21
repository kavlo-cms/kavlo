<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AuthLayout from '@/layouts/AuthLayout.vue';

const useRecoveryCode = ref(false);

const form = useForm({
    code: '',
    recovery_code: '',
});

const activeError = computed(() => form.errors.code || form.errors.recovery_code);

function submit() {
    form.post('/two-factor-challenge', {
        onFinish: () => form.reset('code', 'recovery_code'),
    });
}
</script>

<template>
    <Head title="Two-factor challenge" />

    <AuthLayout title="Two-factor authentication" description="Enter your authenticator code or a recovery code.">
        <form class="space-y-4" @submit.prevent="submit">
            <div class="space-y-2">
                <Label :for="useRecoveryCode ? 'recovery_code' : 'code'">
                    {{ useRecoveryCode ? 'Recovery code' : 'Authentication code' }}
                </Label>
                <Input
                    v-if="!useRecoveryCode"
                    id="code"
                    v-model="form.code"
                    inputmode="numeric"
                    autocomplete="one-time-code"
                    autofocus
                />
                <Input
                    v-else
                    id="recovery_code"
                    v-model="form.recovery_code"
                    autocomplete="one-time-code"
                    autofocus
                />
                <InputError :message="activeError" />
            </div>

            <Button type="submit" class="w-full" :disabled="form.processing">
                Continue
            </Button>
        </form>

        <button
            type="button"
            class="mt-6 w-full text-sm text-muted-foreground hover:text-foreground"
            @click="useRecoveryCode = !useRecoveryCode"
        >
            {{ useRecoveryCode ? 'Use an authentication code instead' : 'Use a recovery code instead' }}
        </button>
    </AuthLayout>
</template>
