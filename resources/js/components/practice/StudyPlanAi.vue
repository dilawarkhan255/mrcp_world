<script setup lang="ts">
import RichText from '@/components/practice/RichText.vue';
import { Button } from '@/components/ui/button';
import { postJson } from '@/lib/post-json';
import { CalendarClock, Clock, Loader2, Send, Sparkles } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';

/**
 * "Ask AI about your revision" on the Study Plan page. The student can ask
 * their own question (or pick a suggestion), and optionally give the exam
 * date and the hours they can study a day; Claude answers from the student's
 * own subjects and plan. Exam date and hours are remembered in this browser.
 */
const STORE_KEY = 'mw-study-plan-ai';

function load(): { examDate: string; hours: string } {
    try {
        const saved = JSON.parse(localStorage.getItem(STORE_KEY) ?? '{}') as { examDate?: string; hours?: string };
        return { examDate: saved.examDate ?? '', hours: saved.hours ?? '' };
    } catch {
        return { examDate: '', hours: '' };
    }
}

const saved = load();
const examDate = ref(saved.examDate);
const hours = ref(saved.hours);
const question = ref('');
const loading = ref(false);
const error = ref<string | null>(null);
const remaining = ref<number | null>(null);
const answers = ref<{ asked: string; html: string }[]>([]);

watch([examDate, hours], () => {
    try {
        localStorage.setItem(STORE_KEY, JSON.stringify({ examDate: examDate.value, hours: hours.value }));
    } catch {
        // Storage can be blocked; the fields still work for this visit.
    }
});

const today = new Date().toISOString().slice(0, 10);
const daysLeft = computed(() => {
    if (!examDate.value) return null;
    const diff = Math.round((new Date(examDate.value + 'T00:00:00').getTime() - new Date(today + 'T00:00:00').getTime()) / 86400000);
    return diff >= 0 ? diff : null;
});

const suggestions = [
    'Plan my revision for the days left',
    'How should I revise my weakest subject?',
    'How many MCQs should I do a day?',
    'How do I use mock exams best?',
];

async function ask(text?: string) {
    const value = (text ?? question.value).trim();
    if (loading.value || (text === undefined && value.length > 0 && value.length < 3)) return;
    loading.value = true;
    error.value = null;
    try {
        const body: Record<string, unknown> = {};
        if (value) body.question = value;
        if (examDate.value) body.exam_date = examDate.value;
        if (hours.value) body.hours = Number(hours.value);
        const { ok, status, data } = await postJson<{ html?: string; error?: string; message?: string; remaining?: number }>('/study-plan/ai', body);
        remaining.value = data.remaining ?? remaining.value;
        if (ok && data.html) {
            answers.value.unshift({ asked: value || 'AI advice on my plan', html: data.html });
            question.value = '';
        } else {
            error.value =
                data.error ??
                data.message ??
                (status === 429 ? 'Too many requests. Please wait a minute.' : 'The AI tutor is not available right now.');
        }
    } catch {
        error.value = 'The AI tutor could not be reached. Please try again.';
    } finally {
        loading.value = false;
    }
}
</script>

<template>
    <section class="relative space-y-4 overflow-hidden rounded-xl border bg-card p-5" aria-labelledby="plan-ai-heading">
        <span class="mw-logo-line absolute inset-x-0 top-0 h-1" aria-hidden="true" />
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div class="flex items-start gap-3">
                <span class="mw-logo-soft flex size-10 shrink-0 items-center justify-center rounded-xl">
                    <Sparkles class="size-5 text-primary" aria-hidden="true" />
                </span>
                <div>
                    <h2 id="plan-ai-heading" class="font-semibold">Ask AI about your revision</h2>
                    <p class="text-xs text-muted-foreground">Answers use your own subjects and plan. Add your exam date for a countdown plan.</p>
                </div>
            </div>
            <Button size="sm" :disabled="loading" @click="ask('')">
                <Loader2 v-if="loading" class="size-4 animate-spin" aria-hidden="true" /><Sparkles v-else class="size-4" aria-hidden="true" />{{
                    loading ? 'Thinking…' : 'Get AI advice'
                }}
            </Button>
        </div>

        <div class="grid gap-3 sm:grid-cols-[1fr_1fr_auto] sm:items-end">
            <label class="grid gap-1.5 text-xs font-medium">
                <span class="flex items-center gap-1.5"><CalendarClock class="size-3.5 text-primary" aria-hidden="true" />Exam date (optional)</span>
                <input v-model="examDate" type="date" :min="today" class="h-10 rounded-md border bg-background px-3 text-sm" />
            </label>
            <label class="grid gap-1.5 text-xs font-medium">
                <span class="flex items-center gap-1.5"><Clock class="size-3.5 text-primary" aria-hidden="true" />Study hours a day (optional)</span>
                <input
                    v-model="hours"
                    type="number"
                    min="0.5"
                    max="16"
                    step="0.5"
                    placeholder="e.g. 3"
                    class="h-10 rounded-md border bg-background px-3 text-sm"
                />
            </label>
            <p v-if="daysLeft !== null" class="mw-logo-soft rounded-lg px-3 py-2 text-center text-sm">
                <strong class="text-lg text-primary">{{ daysLeft }}</strong> <span class="text-xs text-muted-foreground">days left</span>
            </p>
        </div>

        <div class="flex flex-wrap gap-2" role="group" aria-label="Suggested questions">
            <button
                v-for="suggestion in suggestions"
                :key="suggestion"
                type="button"
                class="mw-gradient-border rounded-full px-3 py-1 text-xs font-medium transition hover:-translate-y-0.5 disabled:opacity-50"
                :disabled="loading"
                @click="ask(suggestion)"
            >
                <span class="text-primary">{{ suggestion }}</span>
            </button>
        </div>

        <form class="flex gap-2" @submit.prevent="ask()">
            <label for="plan-ai-question" class="sr-only">Your question about your revision</label>
            <input
                id="plan-ai-question"
                v-model="question"
                type="text"
                maxlength="400"
                placeholder="Ask anything about your revision, e.g. How do I fit renal into my last two weeks?"
                class="h-10 min-w-0 flex-1 rounded-md border bg-background px-3 text-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
            />
            <Button type="submit" :disabled="loading || question.trim().length < 3" aria-label="Ask">
                <Loader2 v-if="loading" class="size-4 animate-spin" aria-hidden="true" /><Send v-else class="size-4" aria-hidden="true" />
            </Button>
        </form>

        <p v-if="error" role="alert" class="text-sm text-destructive">{{ error }}</p>

        <div
            v-for="(answer, index) in answers"
            :key="answers.length - index"
            class="mw-logo-left mw-logo-soft space-y-2 rounded-lg p-4"
            aria-live="polite"
        >
            <p class="text-xs font-semibold text-muted-foreground">{{ answer.asked }}</p>
            <RichText :html="answer.html" class="text-sm" />
        </div>
        <p v-if="answers.length" class="text-[11px] text-muted-foreground">
            It is a guide, not a rule: adjust it to your own pace.
            <template v-if="remaining !== null"> {{ remaining }} AI requests left today.</template>
        </p>
    </section>
</template>
