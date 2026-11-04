<script setup lang="ts">
import StatCard from '@/components/StatCard.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import type { SharedData } from '@/types';
import type { TrendPoint } from '@/types/practice';
import { Head, Link, usePage } from '@inertiajs/vue3';
import {
    ArrowUpRight,
    BookOpen,
    CalendarDays,
    CheckCircle2,
    ClipboardList,
    FileClock,
    Images,
    ListChecks,
    ListTodo,
    RotateCcw,
    Sparkles,
    Target,
} from 'lucide-vue-next';
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
// Quick start, in the same order as the sidebar.
const tools = [
    { title: 'Question bank', href: '/questions/bank', icon: ListChecks, description: 'Practise the main MCQ bank by subject or across the bank.' },
    { title: 'Past papers', href: '/questions/past-papers', icon: FileClock, description: 'Past paper MCQs, organised unit by unit.' },
    { title: 'Extended textbook', href: '/textbooks', icon: BookOpen, description: 'Chapters by subject with tables and key points.' },
    { title: 'Image Atlas', href: '/image-atlas', icon: Images, description: 'Clinical images with findings and teaching points.' },
    { title: 'Review questions', href: '/questions/review', icon: RotateCcw, description: 'Go back over the questions you got wrong.' },
    { title: 'Mock tests', href: '/questions/mocks', icon: ClipboardList, description: 'Timed papers that feel like the real exam.' },
    { title: 'AI Study Plan', href: '/study-plan', icon: Sparkles, description: 'Your weakest subjects and a plan for the next 7 days.' },
];
</script>
<template>
    <Head title="Dashboard" />
    <AppLayout :breadcrumbs="[{ title: 'Dashboard', href: '/dashboard' }]">
        <div class="mx-auto w-full max-w-7xl space-y-8 p-5 md:p-8 lg:p-10">
            <section class="relative flex flex-wrap items-end justify-between gap-4 overflow-hidden rounded-2xl border bg-card p-6 shadow-sm md:p-8">
                <span class="brand-rainbow absolute inset-x-0 top-0 h-1" aria-hidden="true" />
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
                    <h2 class="text-lg font-semibold">Quick start</h2>
                    <span class="hidden text-xs text-muted-foreground sm:block">Your revision toolkit</span>
                </div>
                <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    <Link
                        v-for="tool in tools"
                        :key="tool.title"
                        :href="tool.href"
                        class="group flex flex-col rounded-xl border bg-card p-5 shadow-sm transition hover:-translate-y-0.5 hover:border-primary/50 hover:shadow-md"
                    >
                        <div class="mb-4 flex items-start justify-between">
                            <span class="flex size-11 items-center justify-center rounded-xl bg-secondary text-primary"
                                ><component :is="tool.icon" class="size-5" /></span
                            ><ArrowUpRight class="size-4 text-muted-foreground transition group-hover:text-primary" />
                        </div>
                        <h3 class="font-semibold">{{ tool.title }}</h3>
                        <p class="mt-1.5 text-sm leading-relaxed text-muted-foreground">{{ tool.description }}</p>
                    </Link>
                </div>
            </section>
        </div>
    </AppLayout>
</template>
