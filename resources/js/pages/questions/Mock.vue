<script setup lang="ts">
import AdminDelete from '@/components/AdminDelete.vue';
import InputError from '@/components/InputError.vue';
import AnswerReveal from '@/components/practice/AnswerReveal.vue';
import AskAi from '@/components/practice/AskAi.vue';
import QuestionComments from '@/components/practice/QuestionComments.vue';
import QuestionGrid from '@/components/practice/QuestionGrid.vue';
import QuestionLabels from '@/components/practice/QuestionLabels.vue';
import ReportMcqDialog from '@/components/practice/ReportMcqDialog.vue';
import ResourcesPanel from '@/components/practice/resources/ResourcesPanel.vue';
import RichText from '@/components/practice/RichText.vue';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import type { SharedData } from '@/types';
import type { CommentThread, MockSession, NavigatorItem, PracticeQuestion } from '@/types/practice';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
const page = usePage<SharedData>();
const props = defineProps<{
    session: MockSession;
    question: PracticeQuestion;
    navigator: NavigatorItem[];
    serverTime: string;
    /** Null until the mock is closed. */
    comments?: CommentThread[] | null;
    canModerate?: boolean;
}>();
const form = useForm({ option: props.question.selected_option || '', seconds: 0 });
/** When the current question was shown, to send the time spent with the answer. */
let shownAt = Date.now();
watch(
    () => props.question.position,
    () => {
        shownAt = Date.now();
    },
);
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
function clearResponse() {
    form.option = '';
    form.clearErrors();
}
function save() {
    form.seconds = Math.round((Date.now() - shownAt) / 1000);
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
                        :class="remaining <= 60 ? 'border-red-500 text-red-600' : 'mw-gradient-border'"
                    >
                        {{ clock }}
                    </div>
                    <Button v-if="!isCompleted" variant="outline" class="mw-gradient-border" @click="confirming = true">Finish mock</Button>
                    <Button
                        v-if="question.question_id"
                        variant="outline"
                        size="sm"
                        class="mw-gradient-border"
                        :disabled="bookmark.processing"
                        @click="toggleBookmark"
                        >{{ question.is_bookmarked ? 'Bookmarked' : 'Bookmark' }}</Button
                    >
                    <Link href="/questions/mocks" class="mw-gradient-border rounded-lg px-4 py-2 text-sm"
                        ><span class="font-medium text-primary">Leave</span></Link
                    >
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
                    <Button size="sm" variant="outline" class="mw-gradient-border" @click="confirming = false">Keep working</Button>
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
                    <div class="mw-logo-line h-full transition-all" :style="{ width: percentage + '%' }" />
                </div>
            </div>
            <section v-if="isCompleted" class="mw-logo-left mw-logo-soft rounded-xl border p-5">
                <h2 class="font-semibold">Mock closed</h2>
                <p class="mt-2 text-sm">
                    {{ session.correct_count }} of {{ session.total_questions }} correct ({{ scorePercentage }}%). You answered {{ answered }} of
                    {{ session.total_questions }} questions. Use the navigator to review every question.
                </p>
            </section>
            <div class="grid gap-6 lg:grid-cols-[minmax(0,7fr)_minmax(0,3fr)]">
                <form class="space-y-6 rounded-xl border bg-card p-6 md:p-8" @submit.prevent="save">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <p class="text-xs font-semibold uppercase tracking-widest text-muted-foreground">Question {{ question.position }}</p>
                        <span class="flex items-center gap-2">
                            <ReportMcqDialog v-if="question.question_id && isCompleted" :question-id="question.question_id" :code="question.code" />
                            <AdminDelete
                                v-if="question.question_id"
                                :url="'/staff/questions/' + question.question_id"
                                label="Delete MCQ"
                                confirm-text="Delete this MCQ? It is archived: hidden from students everywhere. Restore it or delete it for good in the admin panel (MCQs → Archived)."
                            />
                        </span>
                    </div>
                    <QuestionLabels :labels="question.labels" :code="isCompleted ? question.code : null" />
                    <RichText :html="question.body_html" :text="question.body" class="text-base" />
                    <fieldset :disabled="alreadyAnswered || isCompleted || form.processing" class="space-y-3">
                        <legend class="mb-3 text-sm font-semibold">Choose one answer</legend>
                        <label
                            v-for="(text, key) in question.options"
                            :key="key"
                            class="flex cursor-pointer gap-3 rounded-lg border p-4"
                            :class="{
                                'mw-gradient-border': form.option === key,
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
                        <Button
                            v-if="!alreadyAnswered && !isCompleted"
                            type="button"
                            variant="ghost"
                            :disabled="!form.option || form.processing"
                            @click="clearResponse"
                            >Clear response</Button
                        >
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
                    <AnswerReveal v-if="question.result" :result="question.result" :selected="question.selected_option" :options="question.options" />
                </form>
                <aside class="mw-sidebar-scroll space-y-3 lg:sticky lg:top-24 lg:max-h-[calc(100vh-7rem)] lg:self-start lg:overflow-y-auto lg:pr-1">
                    <QuestionGrid
                        :items="navigator"
                        :current="question.position"
                        :base-url="'/questions/mocks/' + session.id"
                        :hide-correctness="!isCompleted"
                    />
                    <p class="text-xs text-muted-foreground">Saved answers are not scored until the mock is closed.</p>
                    <!-- AI is shown on every mock question but stays off until the mock closes (it is an exam). -->
                    <AskAi
                        v-if="page.props.ai?.enabled && question.question_id"
                        :session-id="session.id"
                        :position="question.position"
                        :answered="!!question.result"
                        :locked="isCompleted ? null : 'AI help is off during the mock. It opens for every question as soon as the mock closes.'"
                    />
                    <!-- No learning resources during an open mock: it is an exam. -->
                    <ResourcesPanel
                        v-if="isCompleted"
                        :key="question.position"
                        :result="question.result"
                        locked-text="You did not answer this question in the mock."
                    />
                </aside>
                <QuestionComments
                    v-if="comments && question.question_id"
                    :key="question.question_id"
                    :question-id="question.question_id"
                    :threads="comments"
                    :can-moderate="!!canModerate"
                    class="lg:col-start-1"
                />
            </div>
        </div>
    </AppLayout>
</template>
