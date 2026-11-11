<script setup lang="ts">
import AdminDelete from '@/components/AdminDelete.vue';
import InputError from '@/components/InputError.vue';
import SimplePagination from '@/components/SimplePagination.vue';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import type { CommentQuestion, CommentThread } from '@/types/practice';
import { Head, router, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
const props = defineProps<{
    exam: { id: number; name: string };
    threads: { data: CommentThread[]; current_page: number; prev_page_url: string | null; next_page_url: string | null };
    questions: CommentQuestion[];
    search: string;
    canModerate: boolean;
}>();
// The search box starts from the search the page was opened with.
const searchTerm = ref(props.search ?? '');
const commentingOn = ref<number | null>(null);
const replyingTo = ref<number | null>(null);
const commentForm = useForm<{ question_id: number; body: string }>({ question_id: 0, body: '' });
const replyForm = useForm<{ body: string }>({ body: '' });
const moderateForm = useForm<{ action: string }>({ action: '' });
function findQuestions() {
    router.get('/questions/comments', { q: searchTerm.value }, { preserveState: true });
}
function startComment(questionId: number) {
    commentingOn.value = commentingOn.value === questionId ? null : questionId;
    commentForm.question_id = questionId;
    commentForm.body = '';
    commentForm.clearErrors();
}
function submitComment() {
    commentForm.post('/questions/comments', {
        onSuccess: () => {
            commentingOn.value = null;
            commentForm.reset();
        },
    });
}
function startReply(commentId: number) {
    replyingTo.value = replyingTo.value === commentId ? null : commentId;
    replyForm.body = '';
    replyForm.clearErrors();
}
function submitReply(commentId: number) {
    replyForm.post('/questions/comments/' + commentId + '/reply', {
        onSuccess: () => {
            replyingTo.value = null;
            replyForm.reset();
        },
    });
}
function moderate(commentId: number, action: string) {
    moderateForm.action = action;
    moderateForm.post('/questions/comments/' + commentId + '/moderate');
}
</script>
<template>
    <Head title="Comment threads" />
    <AppLayout :breadcrumbs="[{ title: 'Comment threads', href: '/questions/comments' }]">
        <div class="mx-auto w-full max-w-4xl space-y-6 p-6 md:p-10">
            <div>
                <p class="text-sm font-semibold text-primary">{{ exam.name }}</p>
                <h1 class="mt-2 text-3xl font-semibold">Comment threads</h1>
                <p class="mt-3 text-sm text-muted-foreground">Ask about a question or discuss an explanation. Editors can hide or pin a thread.</p>
            </div>
            <section class="rounded-xl border bg-card p-5">
                <h2 class="font-semibold">Start a thread</h2>
                <p class="mt-1 text-xs text-muted-foreground">Search by question code or wording.</p>
                <form class="mt-4 flex flex-wrap items-end gap-3" @submit.prevent="findQuestions">
                    <label class="grid flex-1 gap-2 text-sm font-medium"
                        >Question
                        <input
                            v-model="searchTerm"
                            type="search"
                            placeholder="e.g. CARD-014 or hypoxia"
                            class="rounded-md border bg-background px-3 py-2"
                    /></label>
                    <Button type="submit" :disabled="!searchTerm.trim()">Search</Button>
                </form>
                <ul v-if="questions.length" class="mt-4 space-y-3">
                    <li v-for="question in questions" :key="question.id" class="rounded-lg border p-4">
                        <p class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">{{ question.code }}</p>
                        <p class="mt-1 line-clamp-3 text-sm leading-relaxed">{{ question.body }}</p>
                        <Button size="sm" variant="outline" class="mt-3" @click="startComment(question.id)">
                            {{ commentingOn === question.id ? 'Cancel' : 'Comment' }}
                        </Button>
                        <form v-if="commentingOn === question.id" class="mt-3 space-y-2" @submit.prevent="submitComment">
                            <textarea
                                v-model="commentForm.body"
                                rows="3"
                                required
                                minlength="3"
                                maxlength="4000"
                                placeholder="Share a thought, a source or a question about this item."
                                class="w-full rounded-md border bg-background px-3 py-2 text-sm"
                            />
                            <InputError :message="commentForm.errors.body" />
                            <Button type="submit" size="sm" :disabled="commentForm.processing">
                                {{ commentForm.processing ? 'Posting…' : 'Post comment' }}
                            </Button>
                        </form>
                    </li>
                </ul>
                <p v-else-if="props.search" class="mt-4 rounded-lg border border-dashed p-5 text-center text-sm text-muted-foreground">
                    No published question matched that search in this exam.
                </p>
            </section>
            <section class="space-y-4">
                <h2 class="text-lg font-semibold">Threads</h2>
                <div v-if="!threads.data.length" class="rounded-xl border border-dashed p-8 text-center">
                    <h3 class="font-semibold">No threads yet</h3>
                    <p class="mt-2 text-sm text-muted-foreground">Search for a question above to start the first discussion.</p>
                </div>
                <article v-for="thread in threads.data" :key="thread.id" class="rounded-xl border bg-card p-5">
                    <div v-if="thread.question" class="rounded-lg bg-muted/40 p-3">
                        <p class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">{{ thread.question.code }}</p>
                        <p class="mt-1 line-clamp-2 text-sm leading-relaxed">{{ thread.question.body }}</p>
                    </div>
                    <div class="mt-4 flex flex-wrap items-center gap-2 text-xs">
                        <span class="font-semibold">{{ thread.author }}</span>
                        <span class="rounded-full bg-secondary px-2 py-0.5 text-[11px] font-semibold text-secondary-foreground">{{
                            thread.author_role
                        }}</span>
                        <span v-if="thread.created_at" class="text-muted-foreground">{{ new Date(thread.created_at).toLocaleString() }}</span>
                        <span v-if="thread.is_pinned" class="rounded-full bg-primary px-2 py-0.5 text-[11px] font-semibold text-primary-foreground"
                            >Pinned</span
                        >
                        <span v-if="thread.is_hidden" class="rounded-full bg-destructive px-2 py-0.5 text-[11px] font-semibold text-white"
                            >Hidden</span
                        >
                    </div>
                    <p class="mt-3 whitespace-pre-wrap text-sm leading-relaxed">{{ thread.body }}</p>
                    <div class="mt-4 flex flex-wrap gap-3 text-xs font-semibold">
                        <button type="button" class="text-primary" @click="startReply(thread.id)">
                            {{ replyingTo === thread.id ? 'Cancel reply' : 'Reply' }}
                        </button>
                        <template v-if="canModerate">
                            <button type="button" class="text-muted-foreground" @click="moderate(thread.id, thread.is_hidden ? 'unhide' : 'hide')">
                                {{ thread.is_hidden ? 'Unhide' : 'Hide' }}
                            </button>
                            <button type="button" class="text-muted-foreground" @click="moderate(thread.id, thread.is_pinned ? 'unpin' : 'pin')">
                                {{ thread.is_pinned ? 'Unpin' : 'Pin' }}
                            </button>
                        </template>
                        <AdminDelete
                            :url="'/staff/comments/' + thread.id"
                            icon-only
                            label="Delete comment"
                            confirm-text="Delete this comment and its replies for good?"
                        />
                    </div>
                    <form v-if="replyingTo === thread.id" class="mt-3 space-y-2" @submit.prevent="submitReply(thread.id)">
                        <textarea
                            v-model="replyForm.body"
                            rows="2"
                            required
                            minlength="3"
                            maxlength="4000"
                            placeholder="Write a reply"
                            class="w-full rounded-md border bg-background px-3 py-2 text-sm"
                        />
                        <InputError :message="replyForm.errors.body" />
                        <Button type="submit" size="sm" :disabled="replyForm.processing">{{
                            replyForm.processing ? 'Posting…' : 'Post reply'
                        }}</Button>
                    </form>
                    <ul v-if="thread.replies.length" class="mt-4 space-y-3 border-l-2 border-muted pl-4">
                        <li v-for="reply in thread.replies" :key="reply.id">
                            <div class="flex flex-wrap items-center gap-2 text-xs">
                                <span class="font-semibold">{{ reply.author }}</span>
                                <span class="rounded-full bg-secondary px-2 py-0.5 text-[11px] font-semibold text-secondary-foreground">{{
                                    reply.author_role
                                }}</span>
                                <span v-if="reply.created_at" class="text-muted-foreground">{{ new Date(reply.created_at).toLocaleString() }}</span>
                                <span v-if="reply.is_hidden" class="rounded-full bg-destructive px-2 py-0.5 text-[11px] font-semibold text-white"
                                    >Hidden</span
                                >
                            </div>
                            <p class="mt-2 whitespace-pre-wrap text-sm leading-relaxed">{{ reply.body }}</p>
                        </li>
                    </ul>
                </article>
            </section>
            <SimplePagination :previous="threads.prev_page_url" :next="threads.next_page_url" :page="threads.current_page" />
        </div>
    </AppLayout>
</template>
