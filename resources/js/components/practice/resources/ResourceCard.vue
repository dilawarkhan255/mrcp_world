<script setup lang="ts">
import type { Component } from 'vue';

/** One section of the MCQ resources sidebar: a heading with an icon and a coloured left border. */
defineProps<{ title: string; icon: Component; accent: string; count?: number }>();
</script>
<template>
    <section class="rounded-xl border border-l-4 bg-card p-4 shadow-sm" :style="{ borderLeftColor: accent }">
        <h3 class="flex items-center gap-2 text-sm font-semibold">
            <component :is="icon" class="size-4 shrink-0" :style="{ color: accent }" aria-hidden="true" />
            <span class="min-w-0 flex-1">{{ title }}</span>
            <span v-if="count" class="rounded-full bg-muted px-2 py-0.5 text-[11px] font-medium tabular-nums text-muted-foreground">{{ count }}</span>
        </h3>
        <div class="mt-3 text-sm">
            <slot />
        </div>
    </section>
</template>
