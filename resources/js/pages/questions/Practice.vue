<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import type { PracticeQuestion, SessionSummary } from '@/types/practice';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed, watch } from 'vue';
const props = defineProps<{ session: SessionSummary; question: PracticeQuestion }>();
const form = useForm({ option: props.question.selected_option || '' });
const bookmark = useForm({ question_id: 0 });
watch(
    () => props.question,
    (question) => {
        form.option = question.selected_option || '';
        form.clearErrors();
    },
);
const percentage = computed(() => Math.round((props.session.answered_count / props.session.total_questions) * 100));
function submit() {
    form.post('/questions/sessions/' + props.session.id + '/answer/' + props.question.position, { preserveScroll: true });
}
function toggleBookmark() {
    if (!props.question.question_id) return;
    if (props.question.bookmark_id) {
        bookmark.delete('/revision/bookmarks/' + props.question.bookmark_id);
        return;
    }
    bookmark.question_id = props.question.question_id;
    bookmark.post('/revision/bookmarks');
}
</script>
<template>
    <Head :title="'Practice · Question ' + question.position" />
    <AppLayout
        :breadcrumbs="[
            { title: 'Sessions', href: '/questions/sessions' },
            { title: 'Practice', href: '/questions/sessions/' + session.id },
        ]"
    >
        <div class="mx-auto w-full max-w-4xl space-y-6 p-6 md:p-10">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <h1 class="text-xl font-semibold">Question {{ question.position }} of {{ session.total_questions }}</h1>
                <div class="flex items-center gap-4">
                    <Button v-if="question.question_id" variant="outline" size="sm" :disabled="bookmark.processing" @click="toggleBookmark">{{
                        question.is_bookmarked ? 'Bookmarked' : 'Bookmark'
                    }}</Button>
                    <Link href="/questions/sessions" class="text-sm font-semibold text-primary">Leave session</Link>
                </div>
            </div>
            <div>
                <div class="mb-2 flex justify-between text-xs text-muted-foreground">
                    <span>{{ session.answered_count }} answered</span><span>{{ percentage }}%</span>
                </div>
                <div
                    role="progressbar"
                    aria-label="Session progress"
                    :aria-valuenow="session.answered_count"
                    :aria-valuemax="session.total_questions"
                    aria-valuemin="0"
                    class="h-2 overflow-hidden rounded-full bg-muted"
                >
                    <div class="h-full bg-primary transition-all" :style="{ width: percentage + '%' }" />
                </div>
            </div>
            <section v-if="session.completed_at" class="rounded-xl border bg-secondary p-5 text-secondary-foreground">
                <h2 class="font-semibold">Session completed</h2>
                <p class="mt-2 text-sm">
                    {{ session.correct_count }} of {{ session.total_questions }} correct ({{
                        Math.round((session.correct_count / session.total_questions) * 100)
                    }}%). You can review each saved answer below.
                </p>
            </section>
            <form class="space-y-6 rounded-xl border bg-card p-6 md:p-8" @submit.prevent="submit">
                <p class="whitespace-pre-wrap text-base leading-relaxed">{{ question.body }}</p>
                <fieldset :disabled="!!question.result || form.processing" class="space-y-3">
                    <legend class="mb-3 text-sm font-semibold">Choose one answer</legend>
                    <label
                        v-for="(text, key) in question.options"
                        :key="key"
                        class="flex cursor-pointer gap-3 rounded-lg border p-4"
                        :class="{ 'border-primary bg-secondary': form.option === key, 'border-green-600': question.result?.correct_option === key }"
                        ><input v-model="form.option" type="radio" name="answer" :value="key" class="mt-1 accent-primary" /><span
                            class="font-semibold"
                            >{{ key }}.</span
                        ><span class="whitespace-pre-wrap text-sm leading-relaxed">{{ text }}</span></label
                    >
                </fieldset>
                <InputError :message="form.errors.option" />
                <Button v-if="!question.result" type="submit" :disabled="form.processing || !form.option">{{
                    form.processing ? 'Saving…' : 'Submit answer'
                }}</Button>
                <section v-else role="status" class="rounded-lg border bg-muted/40 p-5">
                    <h2 class="font-semibold" :class="question.result.is_correct ? 'text-green-700 dark:text-green-400' : 'text-destructive'">
                        {{ question.result.is_correct ? 'Correct answer' : 'Incorrect answer' }}
                    </h2>
                    <p class="mt-2 text-sm">Correct option: {{ question.result.correct_option }}</p>
                    <p v-if="question.result.explanation" class="mt-4 whitespace-pre-wrap text-sm leading-relaxed">
                        {{ question.result.explanation }}
                    </p>
                </section>
            </form>
            <nav class="flex items-center justify-between gap-3" aria-label="Questions">
                <Link
                    v-if="question.position > 1"
                    :href="'/questions/sessions/' + session.id + '?position=' + (question.position - 1)"
                    class="rounded-lg border bg-card px-4 py-2 text-sm"
                    >Previous question</Link
                ><span v-else /><Link
                    v-if="question.position < session.total_questions"
                    :href="'/questions/sessions/' + session.id + '?position=' + (question.position + 1)"
                    class="rounded-lg bg-primary px-4 py-2 text-sm text-primary-foreground"
                    >Next question</Link
                ><Link v-else href="/questions/sessions" class="rounded-lg border bg-card px-4 py-2 text-sm">Back to sessions</Link>
            </nav>
            <p class="text-xs text-muted-foreground">Answers are saved when submitted. Unsubmitted choices are not saved.</p>
        </div>
    </AppLayout>
</template>
