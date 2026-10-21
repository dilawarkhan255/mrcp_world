<script setup lang="ts">
import SiteFooter from '@/components/landing/SiteFooter.vue';
import TopBar from '@/components/landing/TopBar.vue';
defineProps<{ appName: string }>();
</script>
<template>
    <div class="flex min-h-screen flex-col bg-surface text-ink">
        <a
            href="#main-content"
            class="sr-only focus:not-sr-only focus:absolute focus:left-4 focus:top-4 focus:z-50 focus:rounded-lg focus:bg-white focus:px-4 focus:py-2 focus:text-sm focus:font-semibold focus:text-ink"
            >Skip to content</a
        >
        <TopBar :app-name="appName" />
        <main id="main-content" class="flex-1">
            <slot />
        </main>
        <SiteFooter :app-name="appName" />
    </div>
</template>
