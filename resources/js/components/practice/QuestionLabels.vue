<script setup lang="ts">
import type { QuestionLabels } from '@/types/practice';
import { BookMarked, HeartPulse, Layers, Repeat } from 'lucide-vue-next';
defineProps<{ labels?: QuestionLabels; code?: string | null }>();
</script>
<template>
    <ul v-if="labels || code" class="flex flex-wrap gap-2 text-xs" aria-label="Question details">
        <li v-if="code" class="rounded-full border bg-muted/60 px-2.5 py-1 font-mono text-muted-foreground">{{ code }}</li>
        <li v-if="labels?.subject" class="inline-flex items-center gap-1 rounded-full bg-secondary px-2.5 py-1 font-medium text-secondary-foreground">
            <Layers class="size-3.5" aria-hidden="true" /><span class="sr-only">Subject:</span>{{ labels.subject }}
        </li>
        <li
            v-if="labels?.system"
            class="inline-flex items-center gap-1 rounded-full px-2.5 py-1 font-medium"
            style="background: var(--tint-indigo-bg); color: var(--tint-indigo-fg)"
        >
            <HeartPulse class="size-3.5" aria-hidden="true" /><span class="sr-only">System:</span>{{ labels.system }}
        </li>
        <li
            v-if="labels && labels.repeats > 0"
            class="inline-flex items-center gap-1 rounded-full px-2.5 py-1 font-medium"
            style="background: var(--tint-amber-bg); color: var(--tint-amber-fg)"
        >
            <Repeat class="size-3.5" aria-hidden="true" />Repeated {{ labels.repeats }}×
        </li>
        <li
            v-if="labels?.reference"
            class="inline-flex items-center gap-1 rounded-full px-2.5 py-1 font-medium"
            style="background: var(--tint-plum-bg); color: var(--tint-plum-fg)"
        >
            <BookMarked class="size-3.5" aria-hidden="true" /><span class="sr-only">Reference:</span>{{ labels.reference }}
        </li>
    </ul>
</template>
