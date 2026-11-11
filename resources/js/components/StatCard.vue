<script setup lang="ts">
// Adapted from shadcn-vue-admin dashboard/overview-content.vue (MIT).
import type { LucideIcon } from 'lucide-vue-next';
defineProps<{ title: string; value: string; description: string; icon: LucideIcon }>();
</script>
<template>
    <section class="relative overflow-hidden rounded-xl border bg-card p-5 text-card-foreground shadow-sm">
        <span class="mw-logo-line absolute inset-x-0 top-0 h-1" aria-hidden="true" />
        <div class="flex items-center justify-between gap-3 pb-3">
            <h2 class="text-sm font-medium text-muted-foreground">{{ title }}</h2>
            <span class="mw-logo-soft flex size-8 items-center justify-center rounded-lg">
                <component :is="icon" class="size-4 text-primary" aria-hidden="true" />
            </span>
        </div>
        <div class="text-3xl font-semibold tracking-tight">{{ value }}</div>
        <p class="mt-2 text-xs text-muted-foreground">{{ description }}</p>
    </section>
</template>
