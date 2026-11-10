<script setup lang="ts">
import { ExternalLink } from 'lucide-vue-next';

/** A link to another site from the resources sidebar, opened in a new tab. */
defineProps<{ href: string; label: string; hint?: string | null }>();
</script>
<template>
    <a
        :href="href"
        target="_blank"
        rel="noopener noreferrer nofollow"
        class="group flex items-start gap-2 rounded-md px-2 py-1.5 transition-colors hover:bg-muted"
    >
        <span class="min-w-0 flex-1">
            <span class="block font-medium leading-snug text-foreground group-hover:text-primary">{{ label }}</span>
            <span v-if="hint" class="block text-xs text-muted-foreground">{{ hint }}</span>
        </span>
        <ExternalLink class="mt-0.5 size-3.5 shrink-0 text-muted-foreground group-hover:text-primary" aria-hidden="true" />
        <span class="sr-only">(opens in a new tab)</span>
    </a>
</template>
