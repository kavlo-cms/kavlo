<script setup lang="ts">
import { useForm, router } from '@inertiajs/vue3';
import { Mail, Save, Send } from 'lucide-vue-next';
import { ref } from 'vue';
import { toast } from 'vue-sonner';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import AppLayout from '@/layouts/AppLayout.vue';
import admin from '@/routes/admin';
import type { BreadcrumbItem } from '@/types';

interface EmailSettings {
    mail_mailer?: string;
    mail_host?: string;
    mail_port?: string;
    mail_username?: string;
    mail_encryption?: string;
    mail_from_address?: string;
    mail_from_name?: string;
    mail_test_template_id?: string;
}

interface TemplateOption {
    value: string;
    label: string;
}

interface DeliveryStatus {
    connection: string;
    queue: string;
    async: boolean;
    after_commit: boolean;
    failed_jobs: number | null;
}

const props = defineProps<{
    settings: EmailSettings;
    hasPassword: boolean;
    availableTemplates: TemplateOption[];
    delivery: DeliveryStatus;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Settings', href: admin.settings.index.url() },
    { title: 'Email', href: admin.settings.email.index.url() },
];

const passwordChanged = ref(false);

const form = useForm({
    mail_mailer:       props.settings.mail_mailer       ?? 'smtp',
    mail_host:         props.settings.mail_host         ?? '',
    mail_port:         props.settings.mail_port         ?? '587',
    mail_username:     props.settings.mail_username     ?? '',
    mail_password:     '',
    mail_encryption:   props.settings.mail_encryption   ?? 'tls',
    mail_from_address: props.settings.mail_from_address ?? '',
    mail_from_name:    props.settings.mail_from_name    ?? '',
    mail_test_template_id: props.settings.mail_test_template_id ?? '__plain__',
});

function save() {
    const data: Record<string, string | null> = {
        mail_mailer:       form.mail_mailer,
        mail_host:         form.mail_host,
        mail_port:         form.mail_port,
        mail_username:     form.mail_username,
        mail_encryption:   form.mail_encryption,
        mail_from_address: form.mail_from_address,
        mail_from_name:    form.mail_from_name,
        mail_test_template_id: form.mail_test_template_id === '__plain__' ? null : form.mail_test_template_id,
    };

    if (passwordChanged.value && form.mail_password !== '') {
        data.mail_password = form.mail_password;
    }

    form.transform(() => data).put(admin.settings.email.update.url(), {
        preserveScroll: true,
        onSuccess: () => {
            toast.success('Email settings saved.');

            if (passwordChanged.value) {
                form.mail_password = '';
                passwordChanged.value = false;
            }
        },
    });
}

const testSending = ref(false);

function sendTestEmail() {
    testSending.value = true;
    router.post(
        admin.settings.email.test.url(),
        {},
        {
            preserveScroll: true,
            onSuccess: (page) => {
                const flash = (page.props as Record<string, unknown>).flash as Record<string, string> | undefined;
                toast.success(flash?.success ?? 'Test email sent.');
            },
            onError: () => {
                toast.error('Failed to send test email.');
            },
            onFinish: () => {
                testSending.value = false;
            },
        },
    );
}
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex items-center justify-between">
            <h1 class="text-2xl font-semibold tracking-tight">Email Settings</h1>
            <Button :disabled="form.processing" @click="save">
                <Save class="mr-2 h-4 w-4" />
                Save Changes
            </Button>
        </div>

        <div class="grid gap-6 lg:grid-cols-3">
            <!-- Main column -->
            <div class="space-y-6 lg:col-span-2">

                <!-- SMTP Configuration -->
                <Card>
                    <CardHeader>
                        <CardTitle>SMTP Configuration</CardTitle>
                        <CardDescription>Configure the outgoing mail server.</CardDescription>
                    </CardHeader>
                    <CardContent class="space-y-4">

                        <div class="space-y-1.5">
                            <Label for="mail_mailer">Mailer</Label>
                            <Select v-model="form.mail_mailer">
                                <SelectTrigger id="mail_mailer">
                                    <SelectValue placeholder="Select mailer" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="smtp">SMTP</SelectItem>
                                    <SelectItem value="log">Log</SelectItem>
                                    <SelectItem value="mailgun">Mailgun</SelectItem>
                                    <SelectItem value="ses">Amazon SES</SelectItem>
                                    <SelectItem value="resend">Resend</SelectItem>
                                </SelectContent>
                            </Select>
                            <p v-if="form.errors.mail_mailer" class="text-sm text-destructive">{{ form.errors.mail_mailer }}</p>
                        </div>

                        <div class="grid gap-4 sm:grid-cols-2">
                            <div class="space-y-1.5">
                                <Label for="mail_host">SMTP Host</Label>
                                <Input
                                    id="mail_host"
                                    v-model="form.mail_host"
                                    placeholder="smtp.example.com"
                                />
                                <p v-if="form.errors.mail_host" class="text-sm text-destructive">{{ form.errors.mail_host }}</p>
                            </div>

                            <div class="space-y-1.5">
                                <Label for="mail_port">Port</Label>
                                <Input
                                    id="mail_port"
                                    v-model="form.mail_port"
                                    type="number"
                                    placeholder="587"
                                />
                                <p v-if="form.errors.mail_port" class="text-sm text-destructive">{{ form.errors.mail_port }}</p>
                            </div>
                        </div>

                        <div class="space-y-1.5">
                            <Label for="mail_username">Username</Label>
                            <Input
                                id="mail_username"
                                v-model="form.mail_username"
                                autocomplete="username"
                                placeholder="your@email.com"
                            />
                            <p v-if="form.errors.mail_username" class="text-sm text-destructive">{{ form.errors.mail_username }}</p>
                        </div>

                        <div class="space-y-1.5">
                            <Label for="mail_password">Password</Label>
                            <Input
                                id="mail_password"
                                v-model="form.mail_password"
                                type="password"
                                autocomplete="new-password"
                                :placeholder="hasPassword ? '••••••••' : 'Enter password'"
                                @input="passwordChanged = true"
                            />
                            <p v-if="form.errors.mail_password" class="text-sm text-destructive">{{ form.errors.mail_password }}</p>
                            <p v-if="hasPassword && !passwordChanged" class="text-xs text-muted-foreground">
                                A password is saved. Leave blank to keep it unchanged.
                            </p>
                        </div>

                        <div class="space-y-1.5">
                            <Label for="mail_encryption">Encryption</Label>
                            <Select v-model="form.mail_encryption">
                                <SelectTrigger id="mail_encryption">
                                    <SelectValue placeholder="Select encryption" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="tls">TLS</SelectItem>
                                    <SelectItem value="ssl">SSL</SelectItem>
                                    <SelectItem value="none">None</SelectItem>
                                </SelectContent>
                            </Select>
                            <p v-if="form.errors.mail_encryption" class="text-sm text-destructive">{{ form.errors.mail_encryption }}</p>
                        </div>

                    </CardContent>
                </Card>

                <!-- From Address -->
                <Card>
                    <CardHeader>
                        <CardTitle>From Address</CardTitle>
                        <CardDescription>The sender name and address used for outgoing mail.</CardDescription>
                    </CardHeader>
                    <CardContent class="space-y-4">

                        <div class="space-y-1.5">
                            <Label for="mail_from_address">From Address</Label>
                            <Input
                                id="mail_from_address"
                                v-model="form.mail_from_address"
                                type="email"
                                placeholder="no-reply@example.com"
                            />
                            <p v-if="form.errors.mail_from_address" class="text-sm text-destructive">{{ form.errors.mail_from_address }}</p>
                        </div>

                        <div class="space-y-1.5">
                            <Label for="mail_from_name">From Name</Label>
                            <Input
                                id="mail_from_name"
                                v-model="form.mail_from_name"
                                placeholder="My Site"
                            />
                            <p v-if="form.errors.mail_from_name" class="text-sm text-destructive">{{ form.errors.mail_from_name }}</p>
                        </div>

                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Email Templates</CardTitle>
                        <CardDescription>Select reusable builder-backed templates for outgoing system mail.</CardDescription>
                    </CardHeader>
                    <CardContent class="space-y-4">
                        <div class="space-y-1.5">
                            <Label for="mail_test_template_id">Test Email Template</Label>
                            <Select v-model="form.mail_test_template_id">
                                <SelectTrigger id="mail_test_template_id">
                                    <SelectValue placeholder="Use plain-text fallback" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="__plain__">Use plain-text fallback</SelectItem>
                                    <SelectItem v-for="template in availableTemplates" :key="template.value" :value="template.value">
                                        {{ template.label }}
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                            <p class="text-xs text-muted-foreground">Used by the test email action below. Form notifications choose their own template inside each form.</p>
                        </div>

                        <Button variant="outline" as-child>
                            <a href="/admin/email-templates">Manage Email Templates</a>
                        </Button>
                    </CardContent>
                </Card>

            </div>

            <!-- Side column -->
            <div class="space-y-6">

                <!-- Test Email -->
                <Card>
                    <CardHeader>
                        <CardTitle class="flex items-center gap-2">
                            <Mail class="h-4 w-4" />
                            Test Email
                        </CardTitle>
                        <CardDescription>
                            Send a test email to the administration address to verify your settings.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <dl class="mb-4 space-y-1 text-sm text-muted-foreground">
                            <div class="flex justify-between gap-3">
                                <dt>Queue connection</dt>
                                <dd class="font-medium text-foreground">{{ props.delivery.connection }}</dd>
                            </div>
                            <div class="flex justify-between gap-3">
                                <dt>Queue name</dt>
                                <dd class="font-medium text-foreground">{{ props.delivery.queue }}</dd>
                            </div>
                            <div class="flex justify-between gap-3">
                                <dt>Delivery mode</dt>
                                <dd class="font-medium text-foreground">{{ props.delivery.async ? 'Asynchronous' : 'Inline / sync' }}</dd>
                            </div>
                            <div class="flex justify-between gap-3">
                                <dt>Dispatch after commit</dt>
                                <dd class="font-medium text-foreground">{{ props.delivery.after_commit ? 'Enabled' : 'Disabled' }}</dd>
                            </div>
                            <div v-if="props.delivery.failed_jobs !== null" class="flex justify-between gap-3">
                                <dt>Failed jobs</dt>
                                <dd class="font-medium text-foreground">{{ props.delivery.failed_jobs }}</dd>
                            </div>
                        </dl>

                        <Button
                            variant="outline"
                            class="w-full"
                            :disabled="testSending"
                            @click="sendTestEmail"
                        >
                            <Send class="mr-2 h-4 w-4" />
                            {{ testSending ? 'Sending…' : 'Send Test Email' }}
                        </Button>
                    </CardContent>
                </Card>

            </div>
        </div>
    </AppLayout>
</template>
