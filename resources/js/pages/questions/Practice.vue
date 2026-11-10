<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import AnswerReveal from '@/components/practice/AnswerReveal.vue';
import AskAi from '@/components/practice/AskAi.vue';
import PrivateNote from '@/components/practice/PrivateNote.vue';
import QuestionComments from '@/components/practice/QuestionComments.vue';
import QuestionGrid from '@/components/practice/QuestionGrid.vue';
import QuestionLabels from '@/components/practice/QuestionLabels.vue';
import ReportMcqDialog from '@/components/practice/ReportMcqDialog.vue';
import ResourcesPanel from '@/components/practice/resources/ResourcesPanel.vue';
import RichText from '@/components/practice/RichText.vue';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import type { SharedData } from '@/types';
import type { CommentThread, NavigatorItem, PracticeQuestion, SessionSummary } from '@/types/practice';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { Bookmark, BookmarkCheck, ChevronLeft, ChevronRight, Eraser, Eye, EyeOff } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
const page = usePage<SharedData>();
const props = defineProps<{
    session: SessionSummary;
    question: PracticeQuestion;
    navigator: NavigatorItem[];
    maxQuestions?: number;
    /** Null until the question is answered: a discussion can give the answer away. */
    comments?: CommentThread[] | null;
    canModerate?: boolean;
}>();
const form = useForm({ option: props.question.selected_option || '', seconds: 0 });
const bookmark = useForm({ question_id: 0 });
const marking = ref(false);
/** When the current question was shown, to send the time spent with the answer. */
let shownAt = Date.now();
watch(
    () => props.question.position,
    () => {
        shownAt = Date.now();
    },
);
watch(
    () => props.question,
    (question) => {
        form.option = question.selected_option || '';
        form.clearErrors();
    },
);
const baseUrl = computed(() => '/questions/sessions/' + props.session.id);
const answered = computed(() => !!props.question.result || props.question.selected_option !== null);
const percentage = computed(() => Math.round((props.session.answered_count / props.session.total_questions) * 100));
function submit() {
    form.seconds = Math.round((Date.now() - shownAt) / 1000);
    form.post(baseUrl.value + '/answer/' + props.question.position, { preserveScroll: true });
}
function clearResponse() {
    form.option = '';
    form.clearErrors();
}
function toggleRead() {
    marking.value = true;
    router.post(
        baseUrl.value + '/read/' + props.question.position,
        { read: !props.question.is_read },
        { preserveScroll: true, onFinish: () => (marking.value = false) },
    );
}
function toggleBookmark() {
    if (!props.question.question_id) return;
    if (props.question.bookmark_id) {
        bookmark.delete('/revision/bookmarks/' + props.question.bookmark_id, { preserveScroll: true });
        return;
    }
    bookmark.question_id = props.question.question_id;
    bookmark.post('/revision/bookmarks', { preserveScroll: true });
}
</script>
<template>
    <Head :title="'Practice · Question ' + question.position" />
    <AppLayout
        :breadcrumbs="[
            { title: 'Sessions', href: '/questions/sessions' },
            { title: 'Practice', href: baseUrl },
        ]"
    >
        <div class="mx-auto w-full max-w-7xl space-y-6 p-4 md:p-8">
            <header class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-widest text-muted-foreground">Practice session</p>
                    <h1 class="text-xl font-semibold">Question {{ question.position }} of {{ session.total_questions }}</h1>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <Button
                        v-if="question.question_id"
                        variant="outline"
                        size="sm"
                        :disabled="bookmark.processing"
                        :aria-pressed="question.is_bookmarked ? 'true' : 'false'"
                        @click="toggleBookmark"
                        ><component :is="question.is_bookmarked ? BookmarkCheck : Bookmark" class="size-4" aria-hidden="true" />{{
                            question.is_bookmarked ? 'Bookmarked' : 'Bookmark'
                        }}</Button
                    >
                    <Button variant="outline" size="sm" :disabled="marking" :aria-pressed="question.is_read ? 'true' : 'false'" @click="toggleRead"
                        ><component :is="question.is_read ? EyeOff : Eye" class="size-4" aria-hidden="true" />{{
                            question.is_read ? 'Unmark read' : 'Mark as read'
                        }}</Button
                    >
                    <ReportMcqDialog v-if="question.question_id" :question-id="question.question_id" :code="question.code" />
                    <Link href="/questions/sessions" class="px-2 text-sm font-semibold text-primary">Leave session</Link>
                </div>
            </header>

            <div>
                <div class="mb-2 flex justify-between text-xs text-muted-foreground">
                    <span>{{ session.answered_count }} answered · {{ session.correct_count }} correct</span><span>{{ percentage }}%</span>
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
                    }}%). Use the question grid to review each saved answer.
                </p>
            </section>

            <!-- MCQ and explanation on the left (70%); the navigator and the learning resources on the right (30%). -->
            <div class="grid gap-6 lg:grid-cols-[minmax(0,7fr)_minmax(0,3fr)]">
                <div class="min-w-0 space-y-6">
                    <form class="space-y-6 rounded-xl border bg-card p-5 md:p-8" @submit.prevent="submit">
                        <QuestionLabels :labels="question.labels" :code="question.code" />
                        <RichText :html="question.body_html" :text="question.body" class="text-base" />
                        <fieldset :disabled="answered || form.processing" class="space-y-3">
                            <legend class="mb-3 text-sm font-semibold">Choose one answer</legend>
                            <label
                                v-for="(text, key) in question.options"
                                :key="key"
                                class="flex cursor-pointer gap-3 rounded-lg border p-4 transition-colors hover:border-primary/50"
                                :class="{
                                    'border-primary bg-secondary': form.option === key && !question.result,
                                    'border-green-600 bg-green-50 dark:bg-green-950/40': question.result?.correct_option === key,
                                    'border-red-500 bg-red-50 dark:bg-red-950/40':
                                        question.result && !question.result.is_correct && question.selected_option === key,
                                }"
                                ><input v-model="form.option" type="radio" name="answer" :value="key" class="mt-1 accent-primary" /><span
                                    class="font-semibold"
                                    >{{ key }}.</span
                                ><span class="whitespace-pre-wrap text-sm leading-relaxed">{{ text }}</span></label
                            >
                        </fieldset>
                        <InputError :message="form.errors.option" />
                        <div v-if="!answered" class="flex flex-wrap items-center gap-3">
                            <Button type="submit" :disabled="form.processing || !form.option">{{
                                form.processing ? 'Saving…' : 'Submit answer'
                            }}</Button>
                            <Button type="button" variant="ghost" :disabled="!form.option || form.processing" @click="clearResponse"
                                ><Eraser class="size-4" aria-hidden="true" />Clear response</Button
                            >
                        </div>
                        <AnswerReveal
                            v-if="question.result"
                            :result="question.result"
                            :selected="question.selected_option"
                            :options="question.options"
                        />
                    </form>

                    <AskAi
                        v-if="page.props.ai?.enabled && question.question_id"
                        :session-id="session.id"
                        :position="question.position"
                        :answered="!!question.result"
                    />

                    <QuestionComments
                        v-if="comments && question.question_id"
                        :key="question.question_id"
                        :question-id="question.question_id"
                        :threads="comments"
                        :can-moderate="!!canModerate"
                    />

                    <nav class="flex items-center justify-between gap-3" aria-label="Previous and next question">
                        <Link
                            v-if="question.position > 1"
                            :href="baseUrl + '?position=' + (question.position - 1)"
                            class="inline-flex items-center gap-1 rounded-lg border bg-card px-4 py-2 text-sm"
                            ><ChevronLeft class="size-4" aria-hidden="true" />Previous</Link
                        ><span v-else /><Link
                            v-if="question.position < session.total_questions"
                            :href="baseUrl + '?position=' + (question.position + 1)"
                            class="inline-flex items-center gap-1 rounded-lg bg-primary px-4 py-2 text-sm text-primary-foreground"
                            >Next<ChevronRight class="size-4" aria-hidden="true" /></Link
                        ><Link v-else href="/questions/sessions" class="rounded-lg border bg-card px-4 py-2 text-sm">Back to sessions</Link>
                    </nav>
                    <p class="text-xs text-muted-foreground">
                        Answers are saved when submitted and cannot be changed. Unsubmitted choices are not saved.
                    </p>
                </div>

                <!-- Scrolls on its own on desktop, below the 80px navbar. -->
                <aside class="mw-sidebar-scroll space-y-4 lg:sticky lg:top-24 lg:max-h-[calc(100vh-7rem)] lg:self-start lg:overflow-y-auto lg:pr-1">
                    <QuestionGrid :items="navigator" :current="question.position" :base-url="baseUrl" />
                    <ResourcesPanel :key="question.position" :result="question.result" />
                    <PrivateNote
                        v-if="question.question_id"
                        :question-id="question.question_id"
                        :bookmark-id="question.bookmark_id"
                        :note="question.note"
                    />
                </aside>
            </div>
        </div>
    </AppLayout>
</template>
