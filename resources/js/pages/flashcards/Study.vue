<script setup lang="ts">
import AdminDelete from '@/components/AdminDelete.vue';
import AiQuickAsk from '@/components/practice/AiQuickAsk.vue';
import RichText from '@/components/practice/RichText.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import type { SharedData } from '@/types';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { CheckCircle2, Eye, Layers, RotateCcw } from 'lucide-vue-next';
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';

const props = defineProps<{
    exam: { id: number; name: string };
    subject: { slug: string | null; name: string };
    card: { id: number; front_html: string; back_html: string; previews: Record<string, string> } | null;
    counts: { due: number; new: number; total: number };
}>();

/** The four answers, all in the brand colour. */
const RATINGS = [
    {
        key: 'again',
        label: 'Again',
        hint: '1',
        classes: 'mw-gradient-border text-primary hover:brightness-110',
    },
    {
        key: 'hard',
        label: 'Hard',
        hint: '2',
        classes: 'mw-gradient-border text-primary hover:brightness-110',
    },
    {
        key: 'good',
        label: 'Good',
        hint: '3',
        classes: 'mw-gradient-border text-primary hover:brightness-110',
    },
    {
        key: 'easy',
        label: 'Easy',
        hint: '4',
        classes: 'mw-gradient-border text-primary hover:brightness-110',
    },
] as const;

const aiEnabled = computed(() => !!usePage<SharedData>().props.ai?.enabled);
const revealed = ref(false);
const saving = ref(false);
watch(
    () => props.card?.id,
    () => (revealed.value = false),
);

function rate(rating: string) {
    if (!props.card || saving.value || !revealed.value) return;
    saving.value = true;
    router.post('/flashcards/' + props.card.id + '/review', { rating }, { preserveScroll: true, onFinish: () => (saving.value = false) });
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
    <Head :title="'Flashcards · ' + subject.name" />
    <AppLayout
        :breadcrumbs="[
            { title: 'Flashcards', href: '/flashcards' },
            { title: subject.name, href: '/flashcards/study' + (subject.slug ? '?subject=' + subject.slug : '') },
        ]"
    >
        <div class="mx-auto w-full max-w-6xl space-y-6 p-6 md:p-10">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <p class="text-sm font-semibold text-primary">{{ exam.name }} · {{ subject.name }}</p>
                    <h1 class="mt-1 text-2xl font-semibold">Flashcards</h1>
                </div>
                <p class="flex gap-2 text-xs">
                    <span class="mw-logo-soft rounded-full px-2.5 py-1 font-semibold"
                        ><span class="text-primary">{{ counts.due }} due</span></span
                    >
                    <span class="mw-logo-soft rounded-full px-2.5 py-1 font-semibold"
                        ><span class="text-primary">{{ counts.new }} new</span></span
                    >
                    <span class="mw-logo-soft rounded-full px-2.5 py-1 font-semibold"
                        ><span class="text-primary">{{ counts.total }} cards</span></span
                    >
                </p>
            </div>

            <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_20rem]">
                <div class="min-w-0 space-y-6">
                    <div v-if="!card" class="flex flex-col items-center gap-3 rounded-2xl border bg-card p-12 text-center shadow-sm">
                        <CheckCircle2 class="size-10 text-primary" aria-hidden="true" />
                        <h2 class="text-lg font-semibold">All done for now</h2>
                        <p class="max-w-md text-sm text-muted-foreground">
                            {{
                                counts.total
                                    ? 'No card is due. The cards you studied come back when it is time to review them.'
                                    : 'This deck has no cards yet.'
                            }}
                        </p>
                        <Link href="/flashcards" class="mt-2 text-sm font-semibold text-primary hover:underline">Back to the decks</Link>
                    </div>

                    <template v-else>
                        <article class="relative overflow-hidden rounded-2xl border bg-card shadow-sm" aria-live="polite">
                            <span class="brand-rainbow absolute inset-x-0 top-0 h-1" aria-hidden="true" />
                            <div class="p-6 md:p-8">
                                <p class="mb-3 flex items-center gap-2 text-xs font-semibold uppercase tracking-wider text-muted-foreground">
                                    <Layers class="size-4 text-primary" aria-hidden="true" />Question
                                </p>
                                <RichText :html="card.front_html" class="text-base" />
                            </div>
                            <div v-if="revealed" class="border-t bg-muted/30 p-6 md:p-8">
                                <p class="mb-3 flex items-center gap-2 text-xs font-semibold uppercase tracking-wider text-muted-foreground">
                                    <RotateCcw class="size-4 text-primary" aria-hidden="true" />Answer
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
                            <Eye class="size-4" aria-hidden="true" />Show answer
                            <kbd class="ml-1 rounded border border-white/30 px-1.5 text-[10px]">Space</kbd>
                        </button>
                        <div v-else role="group" aria-label="How well did you remember it?" class="grid grid-cols-2 gap-3 sm:grid-cols-4">
                            <button
                                v-for="rating in RATINGS"
                                :key="rating.key"
                                type="button"
                                :disabled="saving"
                                class="flex flex-col items-center rounded-xl border-2 bg-card px-3 py-3 text-sm font-semibold transition disabled:opacity-50"
                                :class="rating.classes"
                                @click="rate(rating.key)"
                            >
                                <span class="text-primary">{{ rating.label }}</span>
                                <span class="mt-0.5 text-[11px] font-medium opacity-80">{{ card.previews[rating.key] }} · {{ rating.hint }}</span>
                            </button>
                        </div>
                        <p class="text-center text-[11px] text-muted-foreground">Space shows the answer; 1–4 rate it.</p>
                        <div class="flex justify-center">
                            <AdminDelete
                                :url="'/staff/flashcards/' + card.id"
                                label="Delete flashcard"
                                confirm-text="Delete this flashcard for good? This cannot be undone."
                            />
                        </div>
                    </template>
                </div>
                <aside v-if="card && aiEnabled" class="lg:sticky lg:top-24 lg:self-start">
                    <AiQuickAsk
                        title="Ask AI about this card"
                        :url="'/flashcards/' + card.id + '/ask'"
                        :suggestions="
                            revealed
                                ? ['Explain the answer', 'Give me a memory aid', 'What else is linked to this?']
                                : ['Give me a hint', 'What topic is this?']
                        "
                        :body="{ revealed }"
                        :reset-key="card.id + (revealed ? '-back' : '-front')"
                        :hint="revealed ? null : 'Before you turn the card, the AI only gives hints.'"
                    />
                </aside>
            </div>
        </div>
    </AppLayout>
</template>
