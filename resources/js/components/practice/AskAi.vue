<script setup lang="ts">
import RichText from '@/components/practice/RichText.vue';
import { Button } from '@/components/ui/button';
import { postJson } from '@/lib/post-json';
import { Loader2, Send, Sparkles } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';

/**
 * "Ask AI" under every question: the student types a question, or picks a
 * suggestion, and Claude answers. Before the answer is revealed, the server
 * gives Claude only the stem and options, never the answer key.
 */
const props = defineProps<{ sessionId: number; position: number; answered: boolean }>();
const question = ref('');
const html = ref<string | null>(null);
const asked = ref<string | null>(null);
const error = ref<string | null>(null);
const remaining = ref<number | null>(null);
const loading = ref(false);

const suggestions = computed(() =>
    props.answered
        ? ['Explain this question', 'Why is my choice wrong?', 'Give me a way to remember this']
        : ['What is the answer?', 'Explain the key clue', 'Explain each option'],
);

watch(
    () => props.position,
    () => {
        question.value = '';
        html.value = null;
        asked.value = null;
        error.value = null;
    },
);

async function ask(text?: string) {
    const value = (text ?? question.value).trim();
    if (value.length < 3 || loading.value) return;
    loading.value = true;
    error.value = null;
    try {
        const { ok, status, data } = await postJson<{ html?: string; error?: string; remaining?: number }>(
            '/questions/sessions/' + props.sessionId + '/ask/' + props.position,
            { question: value },
        );
        remaining.value = data.remaining ?? null;
        if (ok && data.html) {
            html.value = data.html;
            asked.value = value;
            question.value = '';
        } else {
            error.value = data.error ?? (status === 429 ? 'Too many requests. Please wait a minute.' : 'The AI tutor is not available right now.');
        }
    } catch {
        error.value = 'The AI tutor could not be reached. Please try again.';
    } finally {
        loading.value = false;
    }
}
</script>

<template>
    <section class="relative space-y-3 overflow-hidden rounded-xl border bg-card p-5" aria-labelledby="ask-ai-heading">
        <span class="brand-rainbow absolute inset-x-0 top-0 h-0.5" aria-hidden="true" />
        <h2 id="ask-ai-heading" class="flex items-center gap-2 text-sm font-semibold">
            <Sparkles class="size-4 text-primary" aria-hidden="true" />Ask AI about this question
        </h2>
        <div class="flex flex-wrap gap-2" role="group" aria-label="Suggested questions">
            <button
                v-for="suggestion in suggestions"
                :key="suggestion"
                type="button"
                class="rounded-full border bg-background px-3 py-1 text-xs font-medium hover:border-primary/50 hover:text-primary disabled:opacity-50"
                :disabled="loading"
                @click="ask(suggestion)"
            >
                {{ suggestion }}
            </button>
        </div>
        <form class="flex gap-2" @submit.prevent="ask()">
            <label :for="'ask-ai-' + position" class="sr-only">Your question for the AI tutor</label>
            <input
                :id="'ask-ai-' + position"
                v-model="question"
                type="text"
                maxlength="400"
                placeholder="Type your own question…"
                class="h-10 min-w-0 flex-1 rounded-md border bg-background px-3 text-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
            />
            <Button type="submit" :disabled="loading || question.trim().length < 3" aria-label="Ask">
                <Loader2 v-if="loading" class="size-4 animate-spin" aria-hidden="true" /><Send v-else class="size-4" aria-hidden="true" />
            </Button>
        </form>
        <p v-if="!answered" class="text-[11px] text-muted-foreground">
            The AI works the answer out itself; it is not given the answer key. Submit your answer to see the official explanation.
        </p>
        <p v-if="error" role="alert" class="text-sm text-destructive">{{ error }}</p>
        <div v-if="html" class="space-y-2 rounded-lg bg-muted/50 p-4" aria-live="polite">
            <p class="text-xs font-semibold text-muted-foreground">{{ asked }}</p>
            <RichText :html="html" class="text-sm" />
            <p class="text-[11px] text-muted-foreground">
                Written by AI (Claude). Check important facts against the explanation and current NICE guidance.
                <template v-if="remaining !== null"> {{ remaining }} AI requests left today.</template>
            </p>
        </div>
    </section>
</template>
