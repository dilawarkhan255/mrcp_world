<script setup lang="ts">
import StatCard from '@/components/StatCard.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import type { SharedData } from '@/types';
import { Head, Link, usePage } from '@inertiajs/vue3';
import { ArrowUpRight, BookOpen, Brain, CalendarDays, CheckCircle2, Flame, ListChecks, Target } from 'lucide-vue-next';
import { computed } from 'vue';
const page = usePage<SharedData>();
const firstName = computed(() => page.props.auth.user.name.split(' ')[0]);
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
        href: '/textbooks/high-yield',
        icon: BookOpen,
        description: 'Make room for the concepts that matter.',
        label: 'Explore textbooks',
    },
    {
        title: 'Knowledge tutor',
        href: '/tutor/flashcards',
        icon: Brain,
        description: 'Keep key facts fresh with regular recall.',
        label: 'Review your knowledge',
    },
];
</script>
<template>
    <Head title="Home" />
    <AppLayout :breadcrumbs="[{ title: 'Home', href: '/dashboard' }]">
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
                <StatCard title="Questions answered" value="—" description="Your practice will appear here" :icon="CheckCircle2" />
                <StatCard title="Accuracy" value="—" description="Insights after your first session" :icon="Target" />
                <StatCard title="Study streak" value="—" description="Build a steady revision habit" :icon="Flame" />
                <StatCard title="Days studied" value="—" description="A view of your consistency" :icon="CalendarDays" />
            </div>
            <section class="overflow-hidden rounded-xl border bg-card shadow-sm">
                <div class="flex flex-wrap items-center justify-between gap-3 border-b px-6 py-5">
                    <div>
                        <h2 class="font-semibold">Your study activity</h2>
                        <p class="mt-1 text-xs text-muted-foreground">Every session is a step forward.</p>
                    </div>
                    <span class="rounded-full bg-secondary px-3 py-1 text-[11px] font-semibold text-secondary-foreground">Coming soon</span>
                </div>
                <div class="px-6 py-7">
                    <div aria-hidden="true" class="grid grid-flow-col grid-rows-7 gap-1.5 overflow-hidden">
                        <span v-for="cell in 182" :key="cell" class="aspect-square min-h-2 rounded-sm bg-muted" />
                    </div>
                    <p class="mt-5 text-sm text-muted-foreground">Your activity heatmap will appear here when tracking becomes available.</p>
                </div>
            </section>
            <section>
                <div class="mb-4 flex items-center justify-between">
                    <h2 class="text-lg font-semibold">Make time for your next step</h2>
                    <span class="hidden text-xs text-muted-foreground sm:block">Your revision toolkit</span>
                </div>
                <div class="grid gap-5 lg:grid-cols-3">
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
                            ><span class="text-[10px] uppercase tracking-wider text-muted-foreground">Coming soon</span>
                        </div>
                    </Link>
                </div>
            </section>
            <p class="text-xs text-muted-foreground">
                Foundation preview · Your learning content and progress tracking will arrive in a future phase.
            </p>
        </div>
    </AppLayout>
</template>
