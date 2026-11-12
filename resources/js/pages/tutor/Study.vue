<script setup lang="ts">
import AdminDelete from '@/components/AdminDelete.vue';
import AiQuickAsk from '@/components/practice/AiQuickAsk.vue';
import RichText from '@/components/practice/RichText.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import type { SharedData } from '@/types';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { ArrowLeft, CheckCircle2, Eye, Lightbulb, Zap } from 'lucide-vue-next';
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';

/** One fact at a time: think, show it, rate how well you remembered it. */
const props = defineProps<{
    exam: { id: number; name: string };
    run: { id: number; label: string; answered: number; remaining: number };
    card: {
        id: number;
        front_html: string;
        back_html: string;
        previews: Record<string, string>;
        high_yield: string;
        difficulty: number | null;
        subject: string;
        status: string;
        question_id: number | null;
    } | null;
}>();

const RATINGS = [
    { key: 'again', label: 'Again', help: 'I forgot', hint: '1', tone: 'border-rose-400/60 hover:bg-rose-500/10' },
    { key: 'hard', label: 'Hard', help: 'Only just', hint: '2', tone: 'border-amber-400/60 hover:bg-amber-500/10' },
    { key: 'good', label: 'Good', help: 'I knew it', hint: '3', tone: 'border-emerald-400/60 hover:bg-emerald-500/10' },
    { key: 'easy', label: 'Easy', help: 'Too easy', hint: '4', tone: 'border-sky-400/60 hover:bg-sky-500/10' },
] as const;

const STATUS: Record<string, { label: string; classes: string }> = {
    new: { label: 'New fact', classes: 'bg-sky-500/10 text-sky-700 dark:text-sky-300' },
    learning: { label: 'Learning', classes: 'bg-amber-500/10 text-amber-700 dark:text-amber-300' },
    weak: { label: 'Weak: forgotten before', classes: 'bg-rose-500/10 text-rose-700 dark:text-rose-300' },
    recalled: { label: 'Recalled', classes: 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-300' },
    mastered: { label: 'Mastered', classes: 'bg-violet-500/10 text-violet-700 dark:text-violet-300' },
};

const aiEnabled = computed(() => !!usePage<SharedData>().props.ai?.enabled);
const revealed = ref(false);
const saving = ref(false);
watch(
    () => props.card?.id,
    () => (revealed.value = false),
);
const progress = computed(() => {
    const total = props.run.answered + props.run.remaining;
    return total > 0 ? Math.round((props.run.answered / total) * 100) : 100;
});

function rate(rating: string) {
    if (!props.card || saving.value || !revealed.value) return;
    saving.value = true;
    router.post(`/tutor/study/${props.run.id}/${props.card.id}`, { rating }, { preserveScroll: true, onFinish: () => (saving.value = false) });
}

function onKey(event: KeyboardEvent) {
    if (event.target instanceof HTMLElement && (['INPUT', 'TEXTAREA'].includes(event.target.tagName) || event.target.closest('[data-no-shortcuts]')))
        return;
    if (event.key === ' ' || event.key === 'Enter') {
        event.preventDefault();
        revealed.value = true;
    } else if (revealed.value && ['1', '2', '3', '4'].includes(event.key)) {
        rate(RATINGS[Number(event.key) - 1].key);
    }
}
onMounted(() => window.addEventListener('keydown', onKey));
onBeforeUnmount(() => window.removeEventListener('keydown', onKey));
</script>

<template>
    <Head title="Knowledge Tutor" />
    <AppLayout
        :breadcrumbs="[
            { title: 'Knowledge Tutor', href: '/tutor' },
            { title: 'Session', href: '/tutor/study/' + run.id },
        ]"
    >
        <div class="mx-auto w-full max-w-6xl space-y-6 p-4 md:p-8">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div class="min-w-0">
                    <p class="truncate text-sm font-semibold text-primary" :title="run.label">{{ exam.name }} · {{ run.label }}</p>
                    <h1 class="mt-1 flex items-center gap-2 text-2xl font-semibold">
                        <Zap class="size-6 text-primary" aria-hidden="true" />Knowledge Tutor
                    </h1>
                </div>
                <Link href="/tutor" class="inline-flex items-center gap-1 text-sm font-semibold text-primary hover:underline"
                    ><ArrowLeft class="size-4" aria-hidden="true" />Change filters</Link
                >
            </div>

            <div class="rounded-xl border bg-card p-3 shadow-sm">
                <div class="flex items-center justify-between text-xs text-muted-foreground">
                    <span
                        ><strong class="text-foreground">{{ run.answered }}</strong> answered in this session</span
                    >
                    <span
                        ><strong class="text-foreground">{{ run.remaining }}</strong> still ready</span
                    >
                </div>
                <div
                    class="mt-2 h-2 overflow-hidden rounded-full bg-muted"
                    role="progressbar"
                    :aria-valuenow="progress"
                    aria-valuemin="0"
                    aria-valuemax="100"
                    aria-label="Session progress"
                >
                    <div class="brand-rainbow h-full rounded-full transition-all" :style="{ width: progress + '%' }" />
                </div>
            </div>

            <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_20rem]">
                <div class="min-w-0 space-y-5">
                    <div v-if="!card" class="flex flex-col items-center gap-3 rounded-2xl border bg-card p-12 text-center shadow-sm">
                        <CheckCircle2 class="size-10 text-emerald-500" aria-hidden="true" />
                        <h2 class="text-lg font-semibold">Session complete</h2>
                        <p class="max-w-md text-sm text-muted-foreground">
                            You answered {{ run.answered }} facts. Nothing else is ready with these filters: the facts you studied come back when it
                            is time to review them.
                        </p>
                        <div class="mt-2 flex flex-wrap justify-center gap-4 text-sm font-semibold">
                            <Link href="/tutor" class="text-primary hover:underline">Start another session</Link>
                            <Link href="/tutor/facts" class="text-primary hover:underline">Review the facts you answered</Link>
                        </div>
                    </div>

                    <template v-else>
                        <article class="relative overflow-hidden rounded-2xl border bg-card shadow-sm" aria-live="polite">
                            <span class="brand-rainbow absolute inset-x-0 top-0 h-1" aria-hidden="true" />
                            <div class="flex flex-wrap items-center gap-2 border-b px-6 py-3 text-xs">
                                <span class="rounded-full bg-muted px-2.5 py-1 font-semibold">{{ card.subject }}</span>
                                <span class="rounded-full bg-primary/10 px-2.5 py-1 font-semibold text-primary" title="High-yield rating"
                                    >{{ card.high_yield }} high-yield</span
                                >
                                <span v-if="card.difficulty !== null" class="rounded-full bg-muted px-2.5 py-1 font-semibold"
                                    >{{ card.difficulty }}% forget it</span
                                >
                                <span class="rounded-full px-2.5 py-1 font-semibold" :class="STATUS[card.status]?.classes">{{
                                    STATUS[card.status]?.label
                                }}</span>
                            </div>
                            <div class="p-6 md:p-8">
                                <RichText :html="card.front_html" class="text-base" />
                            </div>
                            <div v-if="revealed" class="border-t bg-muted/30 p-6 md:p-8">
                                <p class="mb-3 flex items-center gap-2 text-xs font-semibold uppercase tracking-wider text-muted-foreground">
                                    <Lightbulb class="size-4 text-primary" aria-hidden="true" />The fact
                                </p>
                                <RichText :html="card.back_html" class="text-base" />
                            </div>
                        </article>

                        <button
                            v-if="!revealed"
                            type="button"
                            class="flex w-full items-center justify-center gap-2 rounded-xl bg-primary px-4 py-3 text-sm font-semibold text-primary-foreground shadow-sm hover:opacity-90"
                            @click="revealed = true"
                        >
                            <Eye class="size-4" aria-hidden="true" />Show the fact
                            <kbd class="ml-1 rounded border border-white/30 px-1.5 text-[10px]">Space</kbd>
                        </button>
                        <div v-else>
                            <p class="mb-2 text-center text-sm font-medium" id="rate-title">How well did you remember it?</p>
                            <div role="group" aria-labelledby="rate-title" class="grid grid-cols-2 gap-3 sm:grid-cols-4">
                                <button
                                    v-for="rating in RATINGS"
                                    :key="rating.key"
                                    type="button"
                                    :disabled="saving"
                                    class="flex flex-col items-center rounded-xl border-2 bg-card px-3 py-3 text-sm font-semibold transition disabled:opacity-50"
                                    :class="rating.tone"
                                    @click="rate(rating.key)"
                                >
                                    <span>{{ rating.label }}</span>
                                    <span class="text-[11px] font-medium text-muted-foreground">{{ rating.help }}</span>
                                    <span class="mt-1 text-[11px] font-medium text-muted-foreground"
                                        >next: {{ card.previews[rating.key] }} · key {{ rating.hint }}</span
                                    >
                                </button>
                            </div>
                        </div>
                        <p class="text-center text-[11px] text-muted-foreground">Space shows the fact; 1–4 rate it.</p>
                        <div class="flex justify-center">
                            <AdminDelete
                                :url="'/staff/flashcards/' + card.id"
                                label="Delete fact"
                                confirm-text="Delete this fact for good? This cannot be undone."
                            />
                        </div>
                    </template>
                </div>
                <aside v-if="card && aiEnabled" class="lg:sticky lg:top-24 lg:self-start">
                    <AiQuickAsk
                        title="Ask AI about this fact"
                        :url="'/flashcards/' + card.id + '/ask'"
                        :suggestions="
                            revealed
                                ? ['Explain this fact', 'Give me a memory aid', 'What else is linked to this?']
                                : ['Give me a hint', 'What topic is this?']
                        "
                        :body="{ revealed }"
                        :reset-key="card.id + (revealed ? '-back' : '-front')"
                        :hint="revealed ? null : 'Before you show the fact, the AI only gives hints.'"
                    />
                </aside>
            </div>
        </div>
    </AppLayout>
</template>
