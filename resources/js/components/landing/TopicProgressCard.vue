<script setup lang="ts">
import { landingCopy } from '@/content/landing';
const topics = [
    { label: 'Cardiology', attempted: 128, total: 190 },
    { label: 'Endocrinology', attempted: 96, total: 140 },
    { label: 'Gastroenterology', attempted: 74, total: 120 },
    { label: 'Haematology', attempted: 52, total: 110 },
];
function width(attempted: number, total: number): string {
    return Math.round((attempted / total) * 100) + '%';
}
</script>
<template>
    <div class="rounded-card border bg-white p-6 shadow-sm">
        <div class="flex items-center justify-between border-b pb-3">
            <h3 class="text-sm font-semibold text-ink">{{ landingCopy.progress.heading }}</h3>
            <span class="text-sm font-semibold text-ink">{{ landingCopy.progress.attempted }}</span>
        </div>
        <ul class="mt-4 space-y-5">
            <li v-for="topic in topics" :key="topic.label">
                <div class="flex items-center justify-between gap-4 text-sm">
                    <span class="font-medium text-ink">{{ topic.label }}</span>
                    <span class="tabular-nums text-muted-soft"
                        >[{{ String(topic.attempted).padStart(3, '0') }}] of [{{ String(topic.total).padStart(3, '0') }}]</span
                    >
                </div>
                <div class="mt-2 h-2 overflow-hidden rounded-full" style="background-color: var(--track)">
                    <div class="h-full rounded-full bg-teal" :style="{ width: width(topic.attempted, topic.total) }" />
                </div>
            </li>
        </ul>
        <p class="mt-5 text-xs text-muted-soft">{{ landingCopy.progress.sampleLabel }}</p>
    </div>
</template>
