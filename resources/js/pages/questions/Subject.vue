<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import SimplePagination from '@/components/SimplePagination.vue';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { FileClock, ListChecks, Play } from 'lucide-vue-next';
import { computed, ref } from 'vue';

interface SubjectQuestion {
    id: number;
    code: string;
    paper: string | null;
    excerpt: string;
    prompt: string | null;
}

const props = defineProps<{
    exam: { id: number; name: string };
    subject: { id: number; name: string; slug: string };
    source: 'main' | 'past';
    counts: { main: number; past: number };
    /** The MCQs of this tab, 20 a page (stem only, never the answer). */
    questions?: { data: SubjectQuestion[]; current_page: number; prev_page_url: string | null; next_page_url: string | null };
}>();

const opening = ref<number | null>(null);
/** Practise one MCQ from the list. */
function practiseOne(question: SubjectQuestion) {
    opening.value = question.id;
    router.post(
        `/questions/subjects/${props.subject.slug}/practise-one`,
        { question_id: question.id, source: props.source },
        { onFinish: () => (opening.value = null) },
    );
}

const mainUrl = computed(() => `/questions/subjects/${props.subject.slug}`);
const pastUrl = computed(() => `/questions/subjects/${props.subject.slug}/past-papers`);
const available = computed(() => (props.source === 'main' ? props.counts.main : props.counts.past));
const sourceLabel = computed(() => (props.source === 'main' ? 'Main MCQ Bank' : 'Past Paper MCQs'));

const form = useForm<{ count: number; category_id: number; source: 'main' | 'past' }>({
    count: 20,
    category_id: props.subject.id,
    source: props.source,
});

function start() {
    form.category_id = props.subject.id;
    form.source = props.source;
    form.post('/questions/sessions');
}
</script>
<template>
    <Head :title="subject.name" />
    <AppLayout
        :breadcrumbs="[
            { title: 'Question bank', href: '/questions/bank' },
            { title: subject.name, href: mainUrl },
        ]"
    >
        <div class="mx-auto w-full max-w-6xl space-y-6 p-6 md:p-10">
            <div>
                <p class="text-sm font-semibold text-primary">{{ exam.name }}</p>
                <h1 class="mt-2 text-3xl font-semibold">{{ subject.name }}</h1>
                <p class="mt-3 text-sm text-muted-foreground">Practise {{ sourceLabel.toLowerCase() }} questions in this subject.</p>
            </div>

            <nav class="flex gap-2 border-b" aria-label="Question sources">
                <Link
                    :href="mainUrl"
                    :aria-current="source === 'main' ? 'page' : undefined"
                    class="-mb-px border-b-2 px-4 py-2 text-sm font-semibold"
                    :class="source === 'main' ? 'mw-logo-underline text-primary' : 'border-transparent text-muted-foreground'"
                    >Main MCQ Bank ({{ counts.main }})</Link
                >
                <Link
                    :href="pastUrl"
                    :aria-current="source === 'past' ? 'page' : undefined"
                    class="-mb-px border-b-2 px-4 py-2 text-sm font-semibold"
                    :class="source === 'past' ? 'mw-logo-underline text-primary' : 'border-transparent text-muted-foreground'"
                    >Past Paper MCQs ({{ counts.past }})</Link
                >
            </nav>

            <div v-if="!available" class="rounded-xl border border-dashed p-10 text-center text-muted-foreground">
                No published {{ sourceLabel.toLowerCase() }} questions in this subject yet.
            </div>

            <div v-else class="flex flex-wrap items-end gap-4 rounded-xl border bg-card p-5">
                <label class="grid gap-2 text-sm font-medium"
                    >Questions per session<input
                        placeholder="e.g. 20"
                        required
                        v-model.number="form.count"
                        type="number"
                        min="1"
                        max="100"
                        class="w-36 rounded-md border bg-background px-3 py-2"
                /></label>
                <Button :disabled="form.processing" @click="start">Start practice</Button>
                <p class="w-full text-xs text-muted-foreground">
                    Up to 100 questions per session. If fewer are available, all matching questions are included.
                </p>
                <InputError :message="form.errors.count || form.errors.category_id || form.errors.source" />
            </div>

            <!-- The MCQs of this tab -->
            <section v-if="available && questions && questions.data.length" class="space-y-3" aria-labelledby="subject-mcqs">
                <h2 id="subject-mcqs" class="flex items-center gap-2 text-lg font-semibold">
                    <span class="mw-logo-bar h-5 w-1 rounded-full" aria-hidden="true" />MCQs in {{ subject.name }}
                    <span class="text-sm font-normal text-muted-foreground">({{ available }})</span>
                </h2>
                <ol class="grid gap-3">
                    <li
                        v-for="question in questions.data"
                        :key="question.id"
                        class="mw-logo-left group flex flex-col gap-3 rounded-xl border bg-card p-4 transition hover:shadow-md sm:flex-row sm:items-start sm:justify-between"
                    >
                        <div class="min-w-0 flex-1 space-y-1.5">
                            <p class="flex flex-wrap items-center gap-2 text-xs">
                                <span class="rounded-md border bg-muted/60 px-1.5 py-0.5 font-mono text-[11px] text-muted-foreground">{{
                                    question.code
                                }}</span>
                                <span v-if="question.paper" class="inline-flex items-center gap-1 text-muted-foreground"
                                    ><FileClock class="size-3.5 text-primary" aria-hidden="true" />{{ question.paper }}</span
                                >
                            </p>
                            <p class="text-sm leading-relaxed">{{ question.excerpt }}</p>
                            <p v-if="question.prompt" class="text-sm font-semibold">{{ question.prompt }}</p>
                        </div>
                        <Button
                            size="sm"
                            variant="outline"
                            class="mw-gradient-border shrink-0"
                            :disabled="opening !== null"
                            @click="practiseOne(question)"
                        >
                            <Play class="size-3.5 text-primary" aria-hidden="true" />{{ opening === question.id ? 'Opening…' : 'Practise' }}
                        </Button>
                    </li>
                </ol>
                <SimplePagination :previous="questions.prev_page_url" :next="questions.next_page_url" :page="questions.current_page" />
                <p class="flex items-center gap-1.5 text-xs text-muted-foreground">
                    <ListChecks class="size-3.5 text-primary" aria-hidden="true" />The options and answers open when you practise an MCQ.
                </p>
            </section>
        </div>
    </AppLayout>
</template>
