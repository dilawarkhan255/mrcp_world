<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import SimplePagination from '@/components/SimplePagination.vue';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

interface SearchResult {
    id: number;
    code: string;
    excerpt: string;
    subject: string | null;
    source: string;
}

const props = defineProps<{
    exam: { id: number; name: string };
    q: string;
    total: number;
    practiceCount: number;
    results: null | { data: SearchResult[]; current_page: number; prev_page_url: string | null; next_page_url: string | null };
}>();

const page = usePage();
const serverErrors = computed(() => (page.props.errors ?? {}) as Record<string, string>);
const term = ref(props.q);
const practice = useForm<{ q: string }>({ q: props.q });

function search() {
    router.get('/questions/search', { q: term.value }, { preserveState: true, preserveScroll: true, replace: true });
}

function startPractice() {
    practice.q = props.q;
    practice.post('/questions/search/practice');
}
</script>
<template>
    <Head title="Search MCQs" />
    <AppLayout :breadcrumbs="[{ title: 'Search MCQs', href: '/questions/search' }]">
        <div class="mx-auto w-full max-w-4xl space-y-6 p-6 md:p-10">
            <div>
                <p class="text-sm font-semibold text-primary">{{ exam.name }}</p>
                <h1 class="mt-2 text-3xl font-semibold">Search MCQs</h1>
                <p class="mt-3 text-sm text-muted-foreground">Find published questions by their code or by words in the question.</p>
            </div>

            <form class="flex flex-wrap items-start gap-3 rounded-xl border bg-card p-5" role="search" @submit.prevent="search">
                <label class="grid flex-1 gap-2 text-sm font-medium"
                    >Search
                    <input
                        v-model="term"
                        type="search"
                        minlength="2"
                        maxlength="100"
                        placeholder="Code or words, at least 2 characters"
                        class="w-full rounded-md border bg-background px-3 py-2 text-sm"
                /></label>
                <Button type="submit" class="mt-7">Search</Button>
                <InputError class="w-full" :message="serverErrors.q" />
            </form>

            <template v-if="results">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <p class="text-sm text-muted-foreground">
                        {{ total.toLocaleString() }} matching {{ total === 1 ? 'question' : 'questions' }} for "{{ q }}".
                    </p>
                    <Button v-if="total > 0" :disabled="practice.processing" @click="startPractice">
                        Practise {{ practiceCount }} matching questions
                    </Button>
                </div>
                <InputError :message="practice.errors.q" />

                <div v-if="!results.data.length" class="rounded-xl border border-dashed p-10 text-center text-muted-foreground">
                    No published questions match "{{ q }}". Try fewer or different words.
                </div>

                <article v-for="item in results.data" :key="item.id" class="rounded-xl border bg-card p-5">
                    <div class="flex flex-wrap items-center gap-2 text-xs">
                        <span class="font-semibold uppercase tracking-wide text-muted-foreground">{{ item.code }}</span>
                        <span v-if="item.subject" class="rounded-full bg-secondary px-2 py-0.5 text-[11px] font-semibold text-secondary-foreground">{{
                            item.subject
                        }}</span>
                        <span class="rounded-full border px-2 py-0.5 text-[11px] font-semibold">{{ item.source }}</span>
                    </div>
                    <p class="mt-3 text-sm leading-relaxed">{{ item.excerpt }}</p>
                </article>

                <SimplePagination :previous="results.prev_page_url" :next="results.next_page_url" :page="results.current_page" />
            </template>
            <div v-else class="rounded-xl border border-dashed p-10 text-center text-muted-foreground">
                Enter at least two characters to search the published questions of {{ exam.name }}.
            </div>
        </div>
    </AppLayout>
</template>
