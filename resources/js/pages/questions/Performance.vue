<script setup lang="ts">
import StatCard from '@/components/StatCard.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import type { CategoryPerformance, PerformanceSummary, TrendPoint, WeakQuestion } from '@/types/practice';
import { Head, Link } from '@inertiajs/vue3';
import { CalendarDays, Flame, ListTodo, Target } from 'lucide-vue-next';
import { computed } from 'vue';
const props = defineProps<{
    exam: { id: number; name: string };
    summary: PerformanceSummary;
    trend: TrendPoint[];
    categories: CategoryPerformance[];
    weakest: WeakQuestion[];
    streak: number;
}>();
const maxAnswered = computed(() => Math.max(1, ...props.trend.map((point) => point.answered)));
function shortDay(day: string): string {
    return new Date(day).toLocaleDateString(undefined, { day: 'numeric', month: 'short' });
}
function barHeight(answered: number): string {
    return Math.round((answered / maxAnswered.value) * 100) + '%';
}
</script>
<template>
    <Head title="Performance" />
    <AppLayout :breadcrumbs="[{ title: 'Performance', href: '/questions/performance' }]">
        <div class="mx-auto w-full max-w-6xl space-y-6 p-6 md:p-10">
            <div>
                <p class="text-sm font-semibold text-primary">{{ exam.name }}</p>
                <h1 class="mt-2 text-3xl font-semibold">Performance</h1>
                <p class="mt-3 text-sm text-muted-foreground">Your saved answers for the selected exam. Only submitted answers are counted.</p>
            </div>
            <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <StatCard
                    title="Questions answered"
                    :value="summary.answered.toLocaleString()"
                    description="Saved answers in this exam"
                    :icon="Target"
                />
                <StatCard
                    title="Accuracy"
                    :value="summary.answered ? summary.accuracy + '%' : '—'"
                    :description="`${summary.correct} correct answers`"
                    :icon="Target"
                />
                <StatCard
                    title="Sessions"
                    :value="summary.sessions.toLocaleString()"
                    :description="`${summary.completed} completed · ${summary.mocks} mocks`"
                    :icon="ListTodo"
                />
                <StatCard
                    title="Study streak"
                    :value="streak + (streak === 1 ? ' day' : ' days')"
                    description="Consecutive days with an answer"
                    :icon="Flame"
                />
            </div>
            <section class="relative overflow-hidden rounded-xl border bg-card p-5">
                <span class="mw-logo-line absolute inset-x-0 top-0 h-1" aria-hidden="true" />
                <h2 class="font-semibold">Last 14 days</h2>
                <p class="mt-1 text-xs text-muted-foreground">Answers submitted per day.</p>
                <!-- items-stretch: each column takes the full height, so the bars' percentage heights work. -->
                <div class="mt-6 flex h-40 items-stretch gap-1.5">
                    <div v-for="point in trend" :key="point.day" class="flex flex-1 flex-col items-center gap-2">
                        <span class="text-[10px] tabular-nums text-muted-foreground">{{ point.answered }}</span>
                        <div class="flex w-full flex-1 items-end">
                            <div
                                class="mw-logo-bar w-full rounded-t"
                                :style="{ height: barHeight(point.answered) }"
                                :title="point.day + ': ' + point.answered + ' answers'"
                            />
                        </div>
                        <span class="text-[10px] text-muted-foreground">{{ shortDay(point.day) }}</span>
                    </div>
                </div>
            </section>
            <section class="relative overflow-hidden rounded-xl border bg-card p-5">
                <span class="mw-logo-line absolute inset-x-0 top-0 h-1" aria-hidden="true" />
                <h2 class="font-semibold">Accuracy by category</h2>
                <p class="mt-1 text-xs text-muted-foreground">Where your answers are weakest.</p>
                <div v-if="!categories.length" class="mt-6 rounded-lg border border-dashed p-6 text-center text-sm text-muted-foreground">
                    Answer some questions to see a category breakdown.
                </div>
                <ul v-else class="mt-5 space-y-4">
                    <li v-for="category in categories" :key="category.id">
                        <div class="flex items-center justify-between gap-3 text-sm">
                            <span class="font-medium">{{ category.name }}</span>
                            <span class="text-xs text-muted-foreground"
                                >{{ category.correct }} / {{ category.answered }} correct · {{ category.accuracy }}%</span
                            >
                        </div>
                        <div class="mt-2 h-2 overflow-hidden rounded-full bg-muted">
                            <div class="mw-logo-line h-full rounded-full" :style="{ width: category.accuracy + '%' }" />
                        </div>
                    </li>
                </ul>
            </section>
            <section class="relative overflow-hidden rounded-xl border bg-card p-5">
                <span class="mw-logo-line absolute inset-x-0 top-0 h-1" aria-hidden="true" />
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <h2 class="font-semibold">Most missed questions</h2>
                    <Link href="/questions/review" class="text-sm font-semibold text-primary">Open the review queue →</Link>
                </div>
                <div v-if="!weakest.length" class="mt-6 rounded-lg border border-dashed p-6 text-center text-sm text-muted-foreground">
                    No wrong answers yet. Keep practising.
                </div>
                <ol v-else class="mt-5 space-y-3">
                    <li v-for="question in weakest" :key="question.id" class="mw-logo-left rounded-lg border p-4 text-sm">
                        <p class="line-clamp-2 leading-relaxed">{{ question.body }}</p>
                        <p class="mt-2 text-xs text-muted-foreground">
                            Answered wrongly {{ question.wrong_count }} time{{ question.wrong_count === 1 ? '' : 's' }}
                        </p>
                    </li>
                </ol>
            </section>
            <p class="flex items-center gap-2 text-xs text-muted-foreground">
                <CalendarDays class="size-4 text-primary" aria-hidden="true" /> Answers are counted for the exam selected in the sidebar.
            </p>
        </div>
    </AppLayout>
</template>
