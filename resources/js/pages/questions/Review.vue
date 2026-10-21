<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import SimplePagination from '@/components/SimplePagination.vue';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import type { ReviewItem } from '@/types/practice';
import { Head, useForm } from '@inertiajs/vue3';
defineProps<{
    exam: { id: number; name: string };
    items: { data: ReviewItem[]; current_page: number; prev_page_url: string | null; next_page_url: string | null };
    stats: { queue: number; answered: number };
    maxQuestions: number;
}>();
const form = useForm<{ question_ids: number[] }>({ question_ids: [] });
function practise(ids: number[]) {
    form.question_ids = ids;
    form.post('/questions/review/practice');
}
</script>
<template>
    <Head title="Review questions" />
    <AppLayout :breadcrumbs="[{ title: 'Review questions', href: '/questions/review' }]">
        <div class="mx-auto w-full max-w-5xl space-y-6 p-6 md:p-10">
            <div>
                <p class="text-sm font-semibold text-primary">{{ exam.name }}</p>
                <h1 class="mt-2 text-3xl font-semibold">Review questions</h1>
                <p class="mt-3 text-sm text-muted-foreground">
                    Questions you answered wrongly stay here until you answer them correctly in a later session. {{ stats.queue }} in the queue from
                    {{ stats.answered }} questions answered.
                </p>
            </div>
            <div class="flex flex-wrap items-center gap-3 rounded-xl border bg-card p-5">
                <Button :disabled="form.processing || !stats.queue" @click="practise([])">Practise the whole queue</Button>
                <Button variant="outline" :disabled="form.processing || !items.data.length" @click="practise(items.data.map((item) => item.id))"
                    >Practise these {{ items.data.length }} questions</Button
                >
                <p class="w-full text-xs text-muted-foreground">
                    A review session holds up to {{ maxQuestions }} questions and behaves like normal practice.
                </p>
                <InputError :message="form.errors.question_ids" />
            </div>
            <div v-if="!items.data.length" class="rounded-xl border border-dashed p-8 text-center">
                <h2 class="font-semibold">Nothing to review</h2>
                <p class="mt-2 text-sm text-muted-foreground">Questions you get wrong will collect here automatically.</p>
            </div>
            <div v-else class="space-y-4">
                <article v-for="item in items.data" :key="item.id" class="rounded-xl border bg-card p-5">
                    <p class="whitespace-pre-wrap text-sm leading-relaxed">{{ item.body }}</p>
                    <ul class="mt-4 space-y-2">
                        <li
                            v-for="(text, key) in item.options"
                            :key="key"
                            class="rounded-lg border p-3 text-sm"
                            :class="{
                                'border-red-500 bg-red-50 text-red-800 dark:bg-red-950 dark:text-red-300': key === item.student_option,
                                'border-green-600 bg-green-50 text-green-800 dark:bg-green-950 dark:text-green-300': key === item.correct_option,
                            }"
                        >
                            <span class="font-semibold">{{ key }}.</span> {{ text }}
                            <span v-if="key === item.student_option" class="ml-2 text-xs font-semibold uppercase tracking-wide">Your answer</span>
                            <span v-if="key === item.correct_option" class="ml-2 text-xs font-semibold uppercase tracking-wide">Correct answer</span>
                        </li>
                    </ul>
                    <p v-if="item.explanation" class="mt-4 whitespace-pre-wrap border-l-2 border-primary pl-3 text-sm leading-relaxed">
                        {{ item.explanation }}
                    </p>
                    <p class="mt-4 text-xs text-muted-foreground">
                        Answered wrongly {{ item.wrong_count }} time{{ item.wrong_count === 1 ? '' : 's' }}
                        <span v-if="item.last_answered_at"> · last attempt {{ new Date(item.last_answered_at).toLocaleString() }}</span>
                    </p>
                </article>
            </div>
            <SimplePagination :previous="items.prev_page_url" :next="items.next_page_url" :page="items.current_page" />
        </div>
    </AppLayout>
</template>
