<script setup lang="ts">
import type { Component } from 'vue';

/** One section of the MCQ resources sidebar: a heading with an icon, in the logo's colours (rainbow line on the left). */
defineProps<{ title: string; icon: Component; count?: number }>();
</script>
<template>
    <section class="mw-logo-left rounded-xl border bg-card p-4 shadow-sm">
        <h3 class="flex items-center gap-2 text-sm font-semibold">
            <component :is="icon" class="size-4 shrink-0 text-primary" aria-hidden="true" />
            <span class="min-w-0 flex-1">{{ title }}</span>
            <span v-if="count" class="mw-logo-soft rounded-full px-2 py-0.5 text-[11px] font-semibold tabular-nums"
                ><span class="text-primary">{{ count }}</span></span
            >
        </h3>
        <div class="mt-3 text-sm">
            <slot />
        </div>
    </section>
</template>
