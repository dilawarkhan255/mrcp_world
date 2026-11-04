<script setup lang="ts">
import AiAnswer from '@/components/practice/AiAnswer.vue';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import type { SharedData } from '@/types';
import { Head, router, usePage } from '@inertiajs/vue3';
import { CalendarDays, Compass, Info, Play, Sparkles, Target, TrendingDown } from 'lucide-vue-next';
import { computed, ref } from 'vue';

interface WeakSubject {
    id: number;
    name: string;
    answered: number;
    correct: number;
    accuracy: number;
    available: number;
    reason: string;
}
interface NewSubject {
    id: number;
    name: string;
    available: number;
    reason: string;
}
interface PlanItem {
    category_id: number;
    name: string;
    count: number;
    kind: 'weak' | 'new';
}
interface PlanDay {
    day: number;
    date: string;
    total: number;
    items: PlanItem[];
}

defineProps<{
    exam: { id: number; name: string };
    plan: { weak: WeakSubject[]; unpractised: NewSubject[]; days: PlanDay[]; has_data: boolean; min_answers: number };
}>();

const page = usePage<SharedData>();
const aiEnabled = computed(() => !!page.props.ai?.enabled);
const starting = ref<string | null>(null);
function start(day: number, item: PlanItem) {
    starting.value = day + ':' + item.category_id;
    router.post(
        '/questions/sessions',
        { count: item.count, category_id: item.category_id, source: 'main' },
        { onFinish: () => (starting.value = null) },
    );
}
function dayLabel(date: string): string {
    return new Date(date + 'T00:00:00').toLocaleDateString(undefined, { weekday: 'short', day: 'numeric', month: 'short' });
}
</script>
<template>
    <Head title="AI Study Plan" />
    <AppLayout :breadcrumbs="[{ title: 'AI Study Plan', href: '/study-plan' }]">
        <div class="mx-auto w-full max-w-5xl space-y-8 p-6 md:p-10">
            <div>
                <p class="text-sm font-semibold text-primary">{{ exam.name }}</p>
                <h1 class="mt-2 flex items-center gap-2 text-3xl font-semibold">
                    <Sparkles class="size-7 text-primary" aria-hidden="true" />AI Study Plan
                </h1>
                <p class="mt-2 text-sm text-muted-foreground">
                    Your weakest subjects and the ones you have not practised yet, spread over the next 7 days.
                </p>
                <p class="mt-2 flex items-start gap-1.5 text-xs text-muted-foreground">
                    <Info class="mt-0.5 size-3.5 shrink-0" aria-hidden="true" />
                    <template v-if="aiEnabled"
                        >The plan is worked out from your own saved answers in this exam. The AI advice below is written by Claude from your subjects
                        and scores only; your name and email are never sent.</template
                    >
                    <template v-else>Worked out from your own saved answers in this exam with fixed rules.</template>
                </p>
            </div>

            <p v-if="!plan.has_data" class="flex items-start gap-2 rounded-xl border bg-secondary p-4 text-sm text-secondary-foreground">
                <Info class="mt-0.5 size-4 shrink-0" aria-hidden="true" />
                Answer at least {{ plan.min_answers }} questions in a subject to see your weak areas. Until then, the plan starts with subjects you
                have not practised.
            </p>

            <div class="grid gap-5 md:grid-cols-2">
                <section class="space-y-3 rounded-xl border bg-card p-5">
                    <h2 class="flex items-center gap-2 font-semibold">
                        <TrendingDown class="size-4 text-destructive" aria-hidden="true" />Weakest subjects
                    </h2>
                    <ol v-if="plan.weak.length" class="space-y-2">
                        <li v-for="subject in plan.weak" :key="subject.id" class="rounded-lg border p-3">
                            <div class="flex items-center justify-between gap-2">
                                <span class="flex items-center gap-2 font-medium"
                                    ><TrendingDown class="size-4 shrink-0 text-destructive" aria-hidden="true" />{{ subject.name }}</span
                                >
                                <span class="text-sm font-semibold tabular-nums text-destructive">{{ subject.accuracy }}%</span>
                            </div>
                            <p class="mt-1 text-xs text-muted-foreground">Chosen because: {{ subject.reason }}.</p>
                        </li>
                    </ol>
                    <p v-else class="text-sm text-muted-foreground">No subject has {{ plan.min_answers }} answers yet.</p>
                </section>
                <section class="space-y-3 rounded-xl border bg-card p-5">
                    <h2 class="flex items-center gap-2 font-semibold"><Target class="size-4 text-primary" aria-hidden="true" />Not practised yet</h2>
                    <ul v-if="plan.unpractised.length" class="space-y-2">
                        <li v-for="subject in plan.unpractised.slice(0, 6)" :key="subject.id" class="rounded-lg border p-3">
                            <span class="flex items-center gap-2 font-medium"
                                ><Compass class="size-4 shrink-0 text-primary" aria-hidden="true" />{{ subject.name }}</span
                            >
                            <p class="mt-1 text-xs text-muted-foreground">{{ subject.reason }}</p>
                        </li>
                    </ul>
                    <p v-else class="text-sm text-muted-foreground">You have practised every subject. Well done.</p>
                </section>
            </div>

            <AiAnswer v-if="aiEnabled" url="/study-plan/ai" label="Get AI advice" title="AI advice on your plan" />

            <section class="space-y-3">
                <h2 class="flex items-center gap-2 text-lg font-semibold">
                    <CalendarDays class="size-5 text-primary" aria-hidden="true" />Your next 7 days
                </h2>
                <p class="flex flex-wrap gap-4 text-xs text-muted-foreground">
                    <span class="flex items-center gap-1"><TrendingDown class="size-3.5 text-destructive" aria-hidden="true" />Weak area</span>
                    <span class="flex items-center gap-1"><Compass class="size-3.5 text-primary" aria-hidden="true" />Not practised yet</span>
                </p>
                <p
                    v-if="!plan.days.some((day) => day.items.length)"
                    class="rounded-xl border border-dashed p-8 text-center text-sm text-muted-foreground"
                >
                    There is nothing to plan yet: keep practising and your plan will appear here.
                </p>
                <ol v-else class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                    <li v-for="day in plan.days" :key="day.day" class="rounded-xl border bg-card p-4">
                        <div class="flex items-baseline justify-between">
                            <h3 class="flex items-center gap-1.5 font-semibold">
                                <CalendarDays class="size-4 text-muted-foreground" aria-hidden="true" />Day {{ day.day }}
                            </h3>
                            <span class="text-xs text-muted-foreground">{{ dayLabel(day.date) }} · {{ day.total }} questions</span>
                        </div>
                        <ul class="mt-3 space-y-2">
                            <li v-for="item in day.items" :key="item.category_id" class="flex items-center justify-between gap-2 text-sm">
                                <span class="flex min-w-0 items-start gap-2">
                                    <component
                                        :is="item.kind === 'weak' ? TrendingDown : Compass"
                                        class="mt-0.5 size-4 shrink-0"
                                        :class="item.kind === 'weak' ? 'text-destructive' : 'text-primary'"
                                        :aria-label="item.kind === 'weak' ? 'Weak area' : 'Not practised yet'"
                                    />
                                    <span class="min-w-0">
                                        <span class="block truncate font-medium">{{ item.name }}</span>
                                        <span class="text-xs text-muted-foreground"
                                            >{{ item.count }} questions · {{ item.kind === 'weak' ? 'weak area' : 'new' }}</span
                                        >
                                    </span>
                                </span>
                                <Button size="sm" variant="outline" :disabled="starting !== null" @click="start(day.day, item)"
                                    ><Play class="size-3.5" aria-hidden="true" />{{
                                        starting === day.day + ':' + item.category_id ? 'Starting…' : 'Start'
                                    }}</Button
                                >
                            </li>
                        </ul>
                    </li>
                </ol>
            </section>
        </div>
    </AppLayout>
</template>
