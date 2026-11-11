<script setup lang="ts">
import RichText from '@/components/practice/RichText.vue';
import { postJson } from '@/lib/post-json';
import { ChevronDown, Loader2, Lock, Send, Sparkles } from 'lucide-vue-next';
import { ref, watch } from 'vue';

/**
 * The small "Ask AI" box beside every kind of material (MCQs, mocks, past
 * papers, textbook chapters, flashcards, atlas pictures). A small icon header
 * that folds the box open or closed; suggestions, the student's own question
 * and Claude's answer. `locked` shows why it is off (an open mock).
 */
const props = withDefaults(
    defineProps<{
        url: string;
        suggestions: string[];
        title?: string;
        /** Shown under the input, e.g. that the AI has not seen the answer key. */
        hint?: string | null;
        /** When set, the box is off and says why. */
        locked?: string | null;
        /** Extra fields sent with each question. */
        body?: Record<string, unknown>;
        /** Changing it (the next MCQ or card) clears the answer. */
        resetKey?: string | number;
        startOpen?: boolean;
    }>(),
    { title: 'Ask AI', hint: null, locked: null, body: () => ({}), resetKey: '', startOpen: true },
);

const open = ref(props.startOpen);
const question = ref('');
const html = ref<string | null>(null);
const asked = ref<string | null>(null);
const error = ref<string | null>(null);
const remaining = ref<number | null>(null);
const loading = ref(false);

watch(
    () => props.resetKey,
    () => {
        question.value = '';
        html.value = null;
        asked.value = null;
        error.value = null;
    },
);

async function ask(text?: string) {
    const value = (text ?? question.value).trim();
    if (value.length < 3 || loading.value || props.locked) return;
    loading.value = true;
    error.value = null;
    try {
        const { ok, status, data } = await postJson<{ html?: string; error?: string; message?: string; remaining?: number }>(props.url, {
            ...props.body,
            question: value,
        });
        remaining.value = data.remaining ?? remaining.value;
        if (ok && data.html) {
            html.value = data.html;
            asked.value = value;
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
    <section class="relative overflow-hidden rounded-xl border bg-card" :aria-label="title" data-no-shortcuts>
        <span class="mw-logo-line absolute inset-x-0 top-0 h-1" aria-hidden="true" />
        <button type="button" class="flex w-full items-center gap-2.5 px-4 pb-3 pt-4 text-left" :aria-expanded="open" @click="open = !open">
            <span class="flex size-8 shrink-0 items-center justify-center rounded-full bg-primary text-primary-foreground shadow-sm">
                <Sparkles class="size-4" aria-hidden="true" />
            </span>
            <span class="min-w-0 flex-1">
                <span class="block text-sm font-semibold">{{ title }}</span>
                <span v-if="locked" class="block text-[11px] text-muted-foreground">Off for now</span>
                <span v-else class="block text-[11px] text-muted-foreground">Ask anything about this</span>
            </span>
            <ChevronDown class="size-4 shrink-0 text-primary transition" :class="open ? 'rotate-180' : ''" aria-hidden="true" />
        </button>

        <div v-show="open" class="space-y-3 px-4 pb-4">
            <p v-if="locked" class="flex items-start gap-2 rounded-lg bg-muted/60 p-3 text-xs text-muted-foreground">
                <Lock class="mt-0.5 size-3.5 shrink-0" aria-hidden="true" />{{ locked }}
            </p>
            <template v-else>
                <div class="flex flex-wrap gap-1.5" role="group" aria-label="Suggested questions">
                    <button
                        v-for="suggestion in suggestions"
                        :key="suggestion"
                        type="button"
                        class="mw-gradient-border rounded-full px-2.5 py-0.5 text-[11px] font-medium transition hover:-translate-y-0.5 disabled:opacity-50"
                        :disabled="loading"
                        @click="ask(suggestion)"
                    >
                        <span class="text-primary">{{ suggestion }}</span>
                    </button>
                </div>
                <form class="flex gap-1.5" @submit.prevent="ask()">
                    <label :for="'ai-quick-' + resetKey + url" class="sr-only">Your question for the AI tutor</label>
                    <input
                        :id="'ai-quick-' + resetKey + url"
                        v-model="question"
                        type="text"
                        maxlength="400"
                        placeholder="Type your question…"
                        class="h-9 min-w-0 flex-1 rounded-md border bg-background px-2.5 text-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                    />
                    <button
                        type="submit"
                        class="flex size-9 shrink-0 items-center justify-center rounded-md bg-primary text-primary-foreground disabled:opacity-50"
                        :disabled="loading || question.trim().length < 3"
                        aria-label="Ask"
                    >
                        <Loader2 v-if="loading" class="size-4 animate-spin" aria-hidden="true" /><Send v-else class="size-4" aria-hidden="true" />
                    </button>
                </form>
                <p v-if="loading" class="flex items-center gap-1.5 text-xs text-muted-foreground">
                    <Loader2 class="size-3.5 animate-spin" aria-hidden="true" />Thinking…
                </p>
                <p v-if="hint && !html" class="text-[11px] text-muted-foreground">{{ hint }}</p>
                <p v-if="error" role="alert" class="text-xs text-destructive">{{ error }}</p>
                <div v-if="html" class="mw-logo-left mw-logo-soft max-h-96 space-y-2 overflow-y-auto rounded-lg p-3" aria-live="polite">
                    <p class="text-[11px] font-semibold text-muted-foreground">{{ asked }}</p>
                    <RichText :html="html" class="text-sm" />
                </div>
                <p v-if="html" class="text-[11px] text-muted-foreground">
                    Check important facts against current NICE guidance.
                    <template v-if="remaining !== null"> {{ remaining }} AI requests left today.</template>
                </p>
            </template>
        </div>
    </section>
</template>
