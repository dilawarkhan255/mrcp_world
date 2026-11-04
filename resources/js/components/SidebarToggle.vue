<script setup lang="ts">
import { useSidebar } from '@/components/ui/sidebar';
import { Menu } from 'lucide-vue-next';
/**
 * The menu button of the top bar. The admin panel uses the same icon, size
 * and outline (resources/views/filament/topbar-toggle.blade.php).
 */
const { toggleSidebar, open, isMobile, openMobile } = useSidebar();
</script>

<template>
    <button
        type="button"
        data-sidebar="trigger"
        class="inline-flex size-10 shrink-0 items-center justify-center rounded-lg border bg-background text-muted-foreground transition-colors hover:border-primary/40 hover:bg-accent hover:text-primary focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
        :aria-label="(isMobile ? openMobile : open) ? 'Close the menu' : 'Open the menu'"
        :aria-expanded="isMobile ? openMobile : open"
        @click="toggleSidebar"
    >
        <Menu class="size-5" />
    </button>
</template>
