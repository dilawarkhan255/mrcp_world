<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import type { MockSession, NavigatorItem, PracticeQuestion } from '@/types/practice';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
const props = defineProps<{ session: MockSession; question: PracticeQuestion; navigator: NavigatorItem[]; serverTime: string }>();
const form = useForm({ option: props.question.selected_option || '' });
const bookmark = useForm({ question_id: 0 });
watch(
    () => props.question,
    (question) => {
        form.option = question.selected_option || '';
        form.clearErrors();
    },
);
const remaining = ref(0);
const autoSubmitted = ref(false);
const confirming = ref(false);
let timer: ReturnType<typeof setInterval> | null = null;
function stopTimer() {
    if (timer) {
        clearInterval(timer);
        timer = null;
    }
}
function finish() {
    stopTimer();
    router.post('/questions/mocks/' + props.session.id + '/finish', {}, { preserveScroll: true });
}
onMounted(() => {
    if (!props.session.expires_at) return;
    const diff = new Date(props.session.expires_at).getTime() - new Date(props.serverTime).getTime();
    remaining.value = Math.max(0, Math.round(diff / 1000));
    if (props.session.completed_at) return;
    timer = setInterval(() => {
        remaining.value -= 1;
        if (remaining.value <= 0) {
            remaining.value = 0;
            if (!autoSubmitted.value) {
                autoSubmitted.value = true;
                finish();
            }
        }
    }, 1000);
});
onBeforeUnmount(stopTimer);
const clock = computed(() => Math.floor(remaining.value / 60) + ':' + String(remaining.value % 60).padStart(2, '0'));
const answered = computed(() => props.session.answered_count);
const isCompleted = computed(() => !!props.session.completed_at);
const percentage = computed(() => (props.session.total_questions ? Math.round((answered.value / props.session.total_questions) * 100) : 0));
const scorePercentage = computed(() =>
    props.session.total_questions ? Math.round((props.session.correct_count / props.session.total_questions) * 100) : 0,
);
const alreadyAnswered = computed(() => props.question.selected_option !== null);
function save() {
    form.post('/questions/mocks/' + props.session.id + '/answer/' + props.question.position);
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
    <Head :title="'Mock exam #' + session.id" />
    <AppLayout
        :breadcrumbs="[
            { title: 'Mock exams', href: '/questions/mocks' },
            { title: 'Attempt', href: '/questions/mocks/' + session.id },
        ]"
    >
        <div class="mx-auto w-full max-w-6xl space-y-6 p-6 md:p-10">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h1 class="text-xl font-semibold">Mock exam #{{ session.id }}</h1>
                    <p class="mt-1 text-sm text-muted-foreground">{{ answered }} of {{ session.total_questions }} answered</p>
                </div>
                <div class="flex items-center gap-3">
                    <div
                        v-if="!isCompleted"
                        role="timer"
                        aria-live="off"
                        class="rounded-lg border px-4 py-2 text-lg font-semibold tabular-nums"
                        :class="remaining <= 60 ? 'border-red-500 text-red-600' : 'bg-card'"
                    >
                        {{ clock }}
                    </div>
                    <Button v-if="!isCompleted" variant="outline" @click="confirming = true">Finish mock</Button>
                    <Button v-if="question.question_id" variant="outline" size="sm" :disabled="bookmark.processing" @click="toggleBookmark">{{
                        question.is_bookmarked ? 'Bookmarked' : 'Bookmark'
                    }}</Button>
                    <Link href="/questions/mocks" class="rounded-lg border bg-card px-4 py-2 text-sm">Leave</Link>
                </div>
            </div>
            <div
                v-if="confirming && !isCompleted"
                role="alert"
                class="flex flex-wrap items-center justify-between gap-3 rounded-xl border bg-card p-4"
            >
                <p class="text-sm">Close this mock now? Unanswered questions stay unanswered and your score is revealed.</p>
                <div class="flex gap-2">
                    <Button size="sm" @click="finish">Close mock</Button>
                    <Button size="sm" variant="outline" @click="confirming = false">Keep working</Button>
                </div>
            </div>
            <div>
                <div class="mb-2 flex justify-between text-xs text-muted-foreground">
                    <span>{{ answered }} answered</span><span>{{ percentage }}%</span>
                </div>
                <div
                    role="progressbar"
                    aria-label="Mock progress"
                    :aria-valuenow="answered"
                    :aria-valuemax="session.total_questions"
                    aria-valuemin="0"
                    class="h-2 overflow-hidden rounded-full bg-muted"
                >
                    <div class="h-full bg-primary transition-all" :style="{ width: percentage + '%' }" />
                </div>
            </div>
            <section v-if="isCompleted" class="rounded-xl border bg-secondary p-5 text-secondary-foreground">
                <h2 class="font-semibold">Mock closed</h2>
                <p class="mt-2 text-sm">
                    {{ session.correct_count }} of {{ session.total_questions }} correct ({{ scorePercentage }}%). You answered {{ answered }} of
                    {{ session.total_questions }} questions. Use the navigator to review every question.
                </p>
            </section>
            <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_16rem]">
                <form class="space-y-6 rounded-xl border bg-card p-6 md:p-8" @submit.prevent="save">
                    <p class="text-xs font-semibold uppercase tracking-widest text-muted-foreground">Question {{ question.position }}</p>
                    <p class="whitespace-pre-wrap text-base leading-relaxed">{{ question.body }}</p>
                    <fieldset :disabled="alreadyAnswered || isCompleted || form.processing" class="space-y-3">
                        <legend class="mb-3 text-sm font-semibold">Choose one answer</legend>
                        <label
                            v-for="(text, key) in question.options"
                            :key="key"
                            class="flex cursor-pointer gap-3 rounded-lg border p-4"
                            :class="{
                                'border-primary bg-secondary': form.option === key,
                                'border-green-600': question.result?.correct_option === key,
                                'border-red-500': question.result && !question.result.is_correct && question.selected_option === key,
                            }"
                            ><input v-model="form.option" type="radio" name="answer" :value="key" class="mt-1 accent-primary" /><span
                                class="font-semibold"
                                >{{ key }}.</span
                            ><span class="whitespace-pre-wrap text-sm leading-relaxed">{{ text }}</span></label
                        >
                    </fieldset>
                    <InputError :message="form.errors.option" />
                    <div class="flex flex-wrap items-center gap-3">
                        <Button v-if="!alreadyAnswered && !isCompleted" type="submit" :disabled="form.processing || !form.option">{{
                            form.processing ? 'Saving…' : 'Save answer'
                        }}</Button>
                        <Link
                            v-else-if="question.position < session.total_questions"
                            :href="'/questions/mocks/' + session.id + '?position=' + (question.position + 1)"
                            class="rounded-lg bg-primary px-4 py-2 text-sm text-primary-foreground"
                            >Next question</Link
                        >
                        <Link
                            v-else-if="!isCompleted"
                            :href="'/questions/mocks/' + session.id + '?position=1'"
                            class="rounded-lg bg-primary px-4 py-2 text-sm text-primary-foreground"
                            >Back to first question</Link
                        >
                    </div>
                    <section v-if="question.result" role="status" class="rounded-lg border bg-muted/40 p-5">
                        <h2 class="font-semibold" :class="question.result.is_correct ? 'text-green-700 dark:text-green-400' : 'text-destructive'">
                            {{ question.result.is_correct ? 'Correct answer' : 'Incorrect answer' }}
                        </h2>
                        <p class="mt-2 text-sm">Correct option: {{ question.result.correct_option }}</p>
                        <p v-if="question.result.explanation" class="mt-4 whitespace-pre-wrap text-sm leading-relaxed">
                            {{ question.result.explanation }}
                        </p>
                    </section>
                </form>
                <aside class="space-y-3">
                    <h2 class="text-sm font-semibold">Navigator</h2>
                    <nav aria-label="Question navigator" class="grid grid-cols-6 gap-2">
                        <Link
                            v-for="item in navigator"
                            :key="item.position"
                            :href="'/questions/mocks/' + session.id + '?position=' + item.position"
                            class="flex h-9 items-center justify-center rounded-md border text-sm tabular-nums"
                            :class="[
                                item.position === question.position
                                    ? 'border-primary bg-primary text-primary-foreground'
                                    : item.answered
                                      ? 'border-green-600 bg-green-50 text-green-800 dark:bg-green-950 dark:text-green-300'
                                      : 'bg-card',
                            ]"
                            >{{ item.position }}</Link
                        >
                    </nav>
                    <p class="text-xs text-muted-foreground">Green questions are saved. Nothing is scored until the mock is closed.</p>
                </aside>
            </div>
        </div>
    </AppLayout>
</template>
