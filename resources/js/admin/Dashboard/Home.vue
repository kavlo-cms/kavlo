<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';

</script>

<template>
    <Head title="Dashboard" />

    <div class="flex min-h-screen items-center justify-center bg-muted/30 px-4 py-10">
        <div class="w-full max-w-xl rounded-xl border bg-background p-8 shadow-sm">
            <h1 class="text-2xl font-semibold tracking-tight">You are signed in</h1>
            <p class="mt-2 text-sm text-muted-foreground">
                This account does not have access to the admin interface, but authentication is working.
            </p>

            <div class="mt-6 flex flex-wrap gap-3">
                <Button as-child>
                    <Link href="/">
                        Go to site
                    </Link>
                </Button>

                <Link
                    href="/logout"
                    method="post"
                    as="button"
                    class="inline-flex h-10 items-center justify-center rounded-md border px-4 text-sm font-medium hover:bg-muted"
                >
                    Sign out
                </Link>
            </div>
        </div>
    </div>
</template>
