<script setup lang="ts">
import RichText from '@/components/practice/RichText.vue';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import { postJson } from '@/lib/post-json';
import { Head } from '@inertiajs/vue3';
import { GraduationCap, Loader2, RotateCcw, Send, Sparkles } from 'lucide-vue-next';
import { nextTick, ref } from 'vue';

/**
 * The Knowledge Tutor: a conversation with Claude about any topic of the
 * syllabus. The conversation lives in this page only (not stored); the last
 * turns are sent with each question so the tutor can follow on.
 */
const props = defineProps<{ exam: { id: number; name: string }; enabled: boolean; remaining: number; historyLimit: number }>();

interface Turn {
    role: 'user' | 'assistant';
    content: string;
    html?: string;
}
const turns = ref<Turn[]>([]);
const message = ref('');
const loading = ref(false);
const error = ref<string | null>(null);
const remaining = ref(props.remaining);
const list = ref<HTMLElement | null>(null);

const STARTERS = [
    'Explain the causes of hyponatraemia and how to work them up',
    'What are the key features of sarcoidosis that MRCP likes to test?',
    'Compare type 1 and type 2 respiratory failure',
    'How do I interpret an ABG step by step?',
];

async function send(text?: string) {
    const value = (text ?? message.value).trim();
    if (value.length < 2 || loading.value) return;
    error.value = null;
    loading.value = true;
    const history = turns.value.slice(-props.historyLimit * 2).map(({ role, content }) => ({ role, content }));
    turns.value.push({ role: 'user', content: value });
    message.value = '';
    await scroll();
    try {
        const { ok, status, data } = await postJson<{ text?: string; html?: string; error?: string; remaining?: number }>('/tutor/chat', {
            message: value,
            history,
        });
        remaining.value = data.remaining ?? remaining.value;
        if (ok && data.text) {
            turns.value.push({ role: 'assistant', content: data.text, html: data.html });
        } else {
            turns.value.pop();
            message.value = value;
            error.value =
                data.error ?? (status === 429 ? 'Too many questions at once. Please wait a minute.' : 'The tutor is not available right now.');
        }
    } catch {
        turns.value.pop();
        message.value = value;
        error.value = 'The tutor could not be reached. Please try again.';
    } finally {
        loading.value = false;
        await scroll();
    }
}

async function scroll() {
    await nextTick();
    list.value?.lastElementChild?.scrollIntoView({ behavior: 'smooth', block: 'end' });
}
</script>

<template>
    <Head title="Knowledge Tutor" />
    <AppLayout :breadcrumbs="[{ title: 'Knowledge Tutor', href: '/tutor' }]">
        <div class="mx-auto flex w-full max-w-4xl flex-col gap-6 p-6 md:p-10">
            <div class="flex flex-wrap items-end justify-between gap-3">
                <div>
                    <p class="text-sm font-semibold text-primary">{{ exam.name }}</p>
                    <h1 class="mt-1 flex items-center gap-2 text-3xl font-semibold">
                        <GraduationCap class="size-7 text-primary" aria-hidden="true" />Knowledge Tutor
                    </h1>
                    <p class="mt-2 max-w-2xl text-sm text-muted-foreground">
                        Ask about any topic of the syllabus and follow up as you would with a tutor. For a question about a specific MCQ, use Ask AI
                        under that question.
                    </p>
                </div>
                <Button v-if="turns.length" variant="outline" size="sm" @click="turns = []"
                    ><RotateCcw class="size-4" aria-hidden="true" />New conversation</Button
                >
            </div>

            <div v-if="!enabled" class="rounded-xl border border-dashed p-10 text-center text-sm text-muted-foreground">
                The Knowledge Tutor is not switched on yet. Please check back soon.
            </div>

            <template v-else>
                <div v-if="!turns.length" class="grid gap-3 sm:grid-cols-2" role="group" aria-label="Suggested topics">
                    <button
                        v-for="starter in STARTERS"
                        :key="starter"
                        type="button"
                        class="rounded-xl border bg-card p-4 text-left text-sm font-medium shadow-sm transition hover:-translate-y-0.5 hover:border-primary/50 hover:shadow-md"
                        :disabled="loading"
                        @click="send(starter)"
                    >
                        <Sparkles class="mb-2 size-4 text-primary" aria-hidden="true" />{{ starter }}
                    </button>
                </div>

                <ol ref="list" class="space-y-4" aria-live="polite">
                    <li v-for="(turn, index) in turns" :key="index" :class="turn.role === 'user' ? 'flex justify-end' : ''">
                        <div
                            v-if="turn.role === 'user'"
                            class="max-w-[85%] rounded-2xl rounded-br-sm bg-primary px-4 py-3 text-sm text-primary-foreground"
                        >
                            {{ turn.content }}
                        </div>
                        <div v-else class="relative overflow-hidden rounded-2xl rounded-bl-sm border bg-card p-5 shadow-sm">
                            <span class="brand-rainbow absolute inset-x-0 top-0 h-0.5" aria-hidden="true" />
                            <RichText :html="turn.html ?? ''" class="text-sm" />
                        </div>
                    </li>
                    <li v-if="loading" class="flex items-center gap-2 text-sm text-muted-foreground">
                        <Loader2 class="size-4 animate-spin" aria-hidden="true" />The tutor is writing…
                    </li>
                </ol>

                <form class="sticky bottom-4 flex gap-2 rounded-2xl border bg-card p-2 shadow-lg" @submit.prevent="send()">
                    <label for="tutor-message" class="sr-only">Your question for the tutor</label>
                    <textarea
                        id="tutor-message"
                        v-model="message"
                        rows="2"
                        maxlength="2000"
                        placeholder="Ask about a topic, a mechanism, a guideline…"
                        class="min-w-0 flex-1 resize-none rounded-xl bg-transparent px-3 py-2 text-sm focus-visible:outline-none"
                        @keydown.enter.exact.prevent="send()"
                    />
                    <Button type="submit" class="self-end" :disabled="loading || message.trim().length < 2" aria-label="Send">
                        <Loader2 v-if="loading" class="size-4 animate-spin" aria-hidden="true" /><Send v-else class="size-4" aria-hidden="true" />
                    </Button>
                </form>
                <p v-if="error" role="alert" class="text-sm text-destructive">{{ error }}</p>
                <p class="text-[11px] text-muted-foreground">
                    Written by AI (Claude). It can be wrong: check important facts against the textbook and current NICE guidance. Do not enter
                    patient details. {{ remaining }} AI requests left today.
                </p>
            </template>
        </div>
    </AppLayout>
</template>
