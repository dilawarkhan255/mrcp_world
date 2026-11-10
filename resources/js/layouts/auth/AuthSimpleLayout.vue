<script setup lang="ts">
import { APP_NAME } from '@/lib/brand';
import { Link } from '@inertiajs/vue3';

defineProps<{
    title?: string;
    description?: string;
}>();
</script>

<template>
    <div class="flex min-h-svh flex-col items-center justify-center gap-6 bg-background p-6 md:p-10">
        <div class="w-full max-w-sm">
            <div class="flex flex-col gap-8">
                <div class="flex flex-col items-center gap-4">
                    <!-- The brand logo (light "World" in dark mode), linking to the home page. -->
                    <Link :href="route('home')" class="flex flex-col items-center" :aria-label="APP_NAME + ', home page'">
                        <img src="/brand/mrcp-world-logo.trim.png" :alt="APP_NAME" class="h-20 w-auto object-contain dark:hidden" height="80" />
                        <img src="/brand/mrcp-world-logo.dark.png" :alt="APP_NAME" class="hidden h-20 w-auto object-contain dark:block" height="80" />
                    </Link>
                    <div class="space-y-2 text-center">
                        <h1 class="text-xl font-medium">{{ title }}</h1>
                        <p class="text-center text-sm text-muted-foreground">{{ description }}</p>
                    </div>
                </div>
                <slot />
            </div>
        </div>
    </div>
</template>
