<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { AlertTriangle, CheckCircle, Lock, WrenchIcon } from 'lucide-vue-next';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import AppLayout from '@/layouts/AppLayout.vue';
import admin from '@/routes/admin';
import type { BreadcrumbItem } from '@/types';

const props = defineProps<{
    isDown: boolean;
    message: string;
    secret: string;
    retry: number;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Maintenance', href: admin.maintenance.index.url() },
];

const enableForm = useForm({
    message: props.message || '',
    secret:  props.secret  || '',
    retry:   props.retry   || 60,
});

const disableForm = useForm({});
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex items-center justify-between">
            <h1 class="text-2xl font-semibold tracking-tight">Maintenance Mode</h1>
            <Badge :variant="isDown ? 'destructive' : 'default'">
                <component :is="isDown ? AlertTriangle : CheckCircle" class="mr-1.5 h-3 w-3" />
                {{ isDown ? 'Site is Down' : 'Site is Online' }}
            </Badge>
        </div>

        <!-- Status card -->
        <Card :class="isDown ? 'border-destructive/50 bg-destructive/5' : ''">
            <CardHeader>
                <div class="flex items-center gap-3">
                    <div class="flex h-10 w-10 items-center justify-center rounded-lg" :class="isDown ? 'bg-destructive/10' : 'bg-muted'">
                        <WrenchIcon class="h-5 w-5" :class="isDown ? 'text-destructive' : 'text-muted-foreground'" />
                    </div>
                    <div>
                        <CardTitle>{{ isDown ? 'Maintenance mode is active' : 'Site is running normally' }}</CardTitle>
                        <CardDescription v-if="isDown && message">Message: {{ message }}</CardDescription>
                    </div>
                </div>
            </CardHeader>
            <CardContent v-if="isDown">
                <Button
                    variant="default"
                    :disabled="disableForm.processing"
                    @click="disableForm.post(admin.maintenance.disable.url())"
                >
                    <CheckCircle class="mr-2 h-4 w-4" />
                    Bring Site Back Online
                </Button>
            </CardContent>
        </Card>

        <!-- Enable form (only shown when online) -->
        <Card v-if="!isDown">
            <CardHeader>
                <CardTitle>Enable Maintenance Mode</CardTitle>
                <CardDescription>
                    Visitors will see a 503 maintenance page. You can access the site via the secret URL bypass.
                </CardDescription>
            </CardHeader>
            <CardContent>
                <form @submit.prevent="enableForm.post(admin.maintenance.enable.url())" class="space-y-4">
                    <div class="space-y-1.5">
                        <Label for="maint-message">Message <span class="text-muted-foreground">(optional)</span></Label>
                        <Textarea
                            id="maint-message"
                            v-model="enableForm.message"
                            placeholder="We're performing scheduled maintenance. Back soon!"
                            rows="2"
                        />
                        <p v-if="enableForm.errors.message" class="text-xs text-destructive">{{ enableForm.errors.message }}</p>
                    </div>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <div class="space-y-1.5">
                            <Label for="maint-secret">
                                <Lock class="inline h-3 w-3 mr-1" />
                                Bypass Secret <span class="text-muted-foreground">(optional)</span>
                            </Label>
                            <Input
                                id="maint-secret"
                                v-model="enableForm.secret"
                                placeholder="my-secret-token"
                            />
                            <p class="text-xs text-muted-foreground">
                                Access the site at <code class="font-mono">/{{ enableForm.secret || 'your-secret' }}</code>
                            </p>
                        </div>
                        <div class="space-y-1.5">
                            <Label for="maint-retry">Retry After (seconds)</Label>
                            <Input
                                id="maint-retry"
                                v-model.number="enableForm.retry"
                                type="number"
                                min="1"
                                max="3600"
                            />
                            <p class="text-xs text-muted-foreground">Sent as <code class="font-mono">Retry-After</code> HTTP header</p>
                        </div>
                    </div>

                    <Button type="submit" variant="destructive" :disabled="enableForm.processing">
                        <AlertTriangle class="mr-2 h-4 w-4" />
                        Enable Maintenance Mode
                    </Button>
                </form>
            </CardContent>
        </Card>
    </AppLayout>
</template>
