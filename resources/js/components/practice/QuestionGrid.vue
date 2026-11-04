<script setup lang="ts">
import type { NavigatorItem } from '@/types/practice';
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';
const props = defineProps<{
    items: NavigatorItem[];
    current: number;
    /** Session URL; the grid links to "?position=N". */
    baseUrl: string;
    /** Mock mode: answered questions are neutral because correctness is hidden. */
    hideCorrectness?: boolean;
}>();
const counts = computed(() => ({
    answered: props.items.filter((item) => item.answered).length,
    correct: props.items.filter((item) => item.correct === true).length,
    wrong: props.items.filter((item) => item.correct === false).length,
    read: props.items.filter((item) => item.read).length,
}));
function tone(item: NavigatorItem): string {
    if (item.position === props.current) return 'border-primary bg-primary text-primary-foreground shadow-sm';
    if (item.correct === true) return 'border-green-600 bg-green-50 text-green-800 dark:bg-green-950 dark:text-green-300';
    if (item.correct === false) return 'border-red-500 bg-red-50 text-red-700 dark:bg-red-950 dark:text-red-300';
    if (item.answered) return 'border-primary/60 bg-secondary text-secondary-foreground';
    return 'bg-card hover:border-primary/50';
}
function label(item: NavigatorItem): string {
    const state = item.correct === true ? 'correct' : item.correct === false ? 'incorrect' : item.answered ? 'answered' : 'not answered';
    return 'Question ' + item.position + ', ' + state + (item.read ? ', marked as read' : '');
}
</script>
<template>
    <section class="space-y-3 rounded-xl border bg-card p-4">
        <div class="flex items-center justify-between">
            <h2 class="text-sm font-semibold">Questions</h2>
            <span class="text-xs tabular-nums text-muted-foreground">{{ counts.answered }}/{{ items.length }} answered</span>
        </div>
        <nav aria-label="Question grid" class="grid max-h-[22rem] grid-cols-8 gap-1.5 overflow-y-auto pr-1 sm:grid-cols-10 lg:grid-cols-6">
            <Link
                v-for="item in items"
                :key="item.position"
                :href="baseUrl + '?position=' + item.position"
                preserve-scroll
                :aria-label="label(item)"
                :aria-current="item.position === current ? 'step' : undefined"
                class="relative flex h-8 items-center justify-center rounded-md border text-xs font-medium tabular-nums transition-colors"
                :class="tone(item)"
                >{{ item.position }}<span v-if="item.read" class="absolute right-0.5 top-0.5 size-1.5 rounded-full bg-amber" aria-hidden="true"
            /></Link>
        </nav>
        <ul class="grid grid-cols-2 gap-x-3 gap-y-1 text-[11px] text-muted-foreground">
            <template v-if="!hideCorrectness">
                <li class="flex items-center gap-1.5">
                    <span class="size-2.5 rounded-sm border border-green-600 bg-green-50" />Correct {{ counts.correct }}
                </li>
                <li class="flex items-center gap-1.5">
                    <span class="size-2.5 rounded-sm border border-red-500 bg-red-50" />Incorrect {{ counts.wrong }}
                </li>
            </template>
            <li v-else class="flex items-center gap-1.5"><span class="size-2.5 rounded-sm border border-primary/60 bg-secondary" />Saved</li>
            <li class="flex items-center gap-1.5"><span class="size-1.5 rounded-full bg-amber" />Read {{ counts.read }}</li>
        </ul>
    </section>
</template>
