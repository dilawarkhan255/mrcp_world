<script setup lang="ts">
import StatCard from '@/components/StatCard.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import type { SharedData } from '@/types';
import type { TrendPoint } from '@/types/practice';
import { Head, Link, usePage } from '@inertiajs/vue3';
import { ArrowUpRight, BookOpen, CalendarDays, CheckCircle2, ListChecks, ListTodo, Target } from 'lucide-vue-next';
import { computed } from 'vue';
const props = defineProps<{ stats: { answered: number; correct: number; sessions: number; completed: number }; trend: TrendPoint[] }>();
const page = usePage<SharedData>();
const firstName = computed(() => page.props.auth.user.name.split(' ')[0]);
const maxAnswered = computed(() => Math.max(1, ...props.trend.map((point) => point.answered)));
const trendAnswered = computed(() => props.trend.reduce((total, point) => total + point.answered, 0));
function barHeight(answered: number): string {
    return Math.round((answered / maxAnswered.value) * 100) + '%';
}
function shortDay(day: string): string {
    return new Date(day).toLocaleDateString(undefined, { day: 'numeric', month: 'short' });
}
const tools = [
    {
        title: 'Question bank',
        href: '/questions/bank',
        icon: ListChecks,
        description: 'Build confidence with focused question practice.',
        label: 'Start practising',
    },
    {
        title: 'Textbooks',
        href: '/textbooks',
        icon: BookOpen,
        description: 'Make room for the concepts that matter.',
        label: 'Explore textbooks',
    },
];
</script>
<template>
    <Head title="Dashboard" />
    <AppLayout :breadcrumbs="[{ title: 'Dashboard', href: '/dashboard' }]">
        <div class="mx-auto w-full max-w-7xl space-y-8 p-5 md:p-8 lg:p-10">
            <section class="flex flex-wrap items-end justify-between gap-4">
                <div>
                    <p class="mb-2 text-xs font-semibold uppercase tracking-[0.18em] text-primary">Your learning space</p>
                    <h1 class="text-2xl font-semibold tracking-tight md:text-3xl">Welcome back, {{ firstName }}.</h1>
                    <p class="mt-3 text-sm text-muted-foreground">A little progress, every day. Make your next study session count.</p>
                </div>
                <span class="rounded-full border bg-card px-4 py-2 text-xs font-semibold">{{
                    page.props.currentExam?.name || 'Choose your exam'
                }}</span>
            </section>
            <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <StatCard
                    title="Questions answered"
                    :value="stats.answered.toLocaleString()"
                    description="Across your saved sessions"
                    :icon="CheckCircle2"
                />
                <StatCard
                    title="Accuracy"
                    :value="stats.answered ? Math.round((stats.correct / stats.answered) * 100) + '%' : '—'"
                    description="Correct answers / submitted answers"
                    :icon="Target"
                />
                <StatCard
                    title="Sessions completed"
                    :value="stats.completed.toLocaleString()"
                    description="Completed practice sessions"
                    :icon="ListTodo"
                />
                <StatCard
                    title="In progress"
                    :value="(stats.sessions - stats.completed).toLocaleString()"
                    description="Resume in Previous sessions"
                    :icon="CalendarDays"
                />
            </div>
            <section class="overflow-hidden rounded-xl border bg-card shadow-sm">
                <div class="flex flex-wrap items-center justify-between gap-3 border-b px-6 py-5">
                    <div>
                        <h2 class="font-semibold">Your study activity</h2>
                        <p class="mt-1 text-xs text-muted-foreground">Answers submitted in the last 14 days.</p>
                    </div>
                    <Link href="/questions/performance" class="text-xs font-semibold text-primary">Full performance →</Link>
                </div>
                <div class="px-6 py-7">
                    <div class="flex h-24 items-end gap-1.5">
                        <div v-for="point in trend" :key="point.day" class="flex flex-1 flex-col items-center gap-2">
                            <div class="flex w-full flex-1 items-end">
                                <div
                                    class="w-full rounded-t bg-primary/80"
                                    :style="{ height: barHeight(point.answered) }"
                                    :title="point.day + ': ' + point.answered + ' answers'"
                                />
                            </div>
                            <span class="text-[10px] text-muted-foreground">{{ shortDay(point.day) }}</span>
                        </div>
                    </div>
                    <p class="mt-5 text-sm text-muted-foreground">
                        <template v-if="trendAnswered">You answered {{ trendAnswered }} questions in the last 14 days.</template>
                        <template v-else>No answers saved in the last 14 days. Start a session to build your streak.</template>
                    </p>
                </div>
            </section>
            <section>
                <div class="mb-4 flex items-center justify-between">
                    <h2 class="text-lg font-semibold">Make time for your next step</h2>
                    <span class="hidden text-xs text-muted-foreground sm:block">Your revision toolkit</span>
                </div>
                <div class="grid gap-5 lg:grid-cols-2">
                    <Link
                        v-for="tool in tools"
                        :key="tool.title"
                        :href="tool.href"
                        class="group rounded-xl border bg-card p-6 shadow-sm transition hover:border-primary/50 hover:shadow-md"
                    >
                        <div class="mb-6 flex items-start justify-between">
                            <span class="flex size-11 items-center justify-center rounded-xl bg-secondary text-primary"
                                ><component :is="tool.icon" class="size-5" /></span
                            ><ArrowUpRight class="size-4 text-muted-foreground transition group-hover:text-primary" />
                        </div>
                        <h3 class="text-lg font-semibold">{{ tool.title }}</h3>
                        <p class="mt-2 min-h-10 text-sm leading-relaxed text-muted-foreground">{{ tool.description }}</p>
                        <div class="mt-6 flex items-center justify-between">
                            <span class="text-xs font-semibold text-primary">{{ tool.label }}</span
                            ><span class="text-[10px] uppercase tracking-wider text-muted-foreground">{{
                                tool.title === 'Question bank' ? 'Ready' : 'Coming soon'
                            }}</span>
                        </div>
                    </Link>
                </div>
            </section>
            <p class="text-xs text-muted-foreground">
                Question practice, saved sessions, mock exams and performance reporting are available. Textbooks are coming later.
            </p>
        </div>
    </AppLayout>
</template>
