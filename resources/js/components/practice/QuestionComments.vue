<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import type { CommentThread } from '@/types/practice';
import { useForm } from '@inertiajs/vue3';
import { MessagesSquare } from 'lucide-vue-next';
import { ref } from 'vue';
/**
 * The discussion of one question, shown under its explanation. The server
 * only sends it once the answer is revealed, and applies comment visibility
 * in the query.
 */
const props = defineProps<{ questionId: number; threads: CommentThread[]; canModerate: boolean }>();
const commentForm = useForm({ question_id: props.questionId, body: '' });
const replyForm = useForm({ body: '' });
const moderateForm = useForm({ action: '' });
const replyingTo = ref<number | null>(null);
const options = { preserveScroll: true };
function submitComment() {
    commentForm.question_id = props.questionId;
    commentForm.post('/questions/comments', { ...options, onSuccess: () => commentForm.reset('body') });
}
function startReply(threadId: number) {
    replyingTo.value = replyingTo.value === threadId ? null : threadId;
    replyForm.reset();
    replyForm.clearErrors();
}
function submitReply(threadId: number) {
    replyForm.post('/questions/comments/' + threadId + '/reply', {
        ...options,
        onSuccess: () => {
            replyingTo.value = null;
            replyForm.reset();
        },
    });
}
function moderate(threadId: number, action: string) {
    moderateForm.action = action;
    moderateForm.post('/questions/comments/' + threadId + '/moderate', options);
}
function when(value: string | null): string {
    return value ? new Date(value).toLocaleDateString(undefined, { day: 'numeric', month: 'short', year: 'numeric' }) : '';
}
</script>
<template>
    <section class="space-y-4 rounded-xl border bg-card p-5 md:p-6" aria-labelledby="discussion-heading">
        <h2 id="discussion-heading" class="flex items-center gap-2 font-semibold">
            <MessagesSquare class="size-5 text-primary" aria-hidden="true" />Discussion
            <span class="text-sm font-normal text-muted-foreground">({{ threads.length }})</span>
        </h2>

        <form class="space-y-2" @submit.prevent="submitComment">
            <label :for="'comment-' + questionId" class="sr-only">Add a comment</label>
            <textarea
                :id="'comment-' + questionId"
                v-model="commentForm.body"
                rows="3"
                minlength="3"
                maxlength="4000"
                required
                placeholder="Ask a question, share a source or explain your reasoning."
                class="w-full rounded-md border bg-background p-3 text-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
            />
            <InputError :message="commentForm.errors.body || commentForm.errors.question_id" />
            <Button type="submit" size="sm" :disabled="commentForm.processing || commentForm.body.trim().length < 3">{{
                commentForm.processing ? 'Posting…' : 'Post comment'
            }}</Button>
        </form>

        <p v-if="!threads.length" class="rounded-lg border border-dashed p-4 text-center text-sm text-muted-foreground">
            No comments yet. Start the discussion.
        </p>
        <article v-for="thread in threads" :key="thread.id" class="rounded-lg border p-4" :class="{ 'opacity-70': thread.is_hidden }">
            <div class="flex flex-wrap items-center gap-2 text-xs">
                <span class="font-semibold">{{ thread.author }}</span>
                <span
                    v-if="thread.author_role !== 'Student'"
                    class="rounded-full bg-primary px-2 py-0.5 text-[11px] font-semibold text-primary-foreground"
                    >{{ thread.author_role }}</span
                >
                <span class="text-muted-foreground">{{ when(thread.created_at) }}</span>
                <span v-if="thread.is_pinned" class="rounded-full bg-secondary px-2 py-0.5 text-[11px] font-semibold text-secondary-foreground"
                    >Pinned</span
                >
                <span v-if="thread.is_hidden" class="rounded-full bg-destructive px-2 py-0.5 text-[11px] font-semibold text-white">Hidden</span>
            </div>
            <p class="mt-2 whitespace-pre-wrap text-sm leading-relaxed">{{ thread.body }}</p>
            <div class="mt-3 flex flex-wrap gap-3 text-xs font-semibold">
                <button type="button" class="text-primary" @click="startReply(thread.id)">{{ replyingTo === thread.id ? 'Cancel' : 'Reply' }}</button>
                <template v-if="canModerate">
                    <button type="button" class="text-muted-foreground" @click="moderate(thread.id, thread.is_hidden ? 'unhide' : 'hide')">
                        {{ thread.is_hidden ? 'Unhide' : 'Hide' }}
                    </button>
                    <button type="button" class="text-muted-foreground" @click="moderate(thread.id, thread.is_pinned ? 'unpin' : 'pin')">
                        {{ thread.is_pinned ? 'Unpin' : 'Pin' }}
                    </button>
                </template>
            </div>
            <form v-if="replyingTo === thread.id" class="mt-3 space-y-2" @submit.prevent="submitReply(thread.id)">
                <textarea
                    v-model="replyForm.body"
                    rows="2"
                    minlength="3"
                    maxlength="4000"
                    required
                    aria-label="Reply"
                    placeholder="Write a reply"
                    class="w-full rounded-md border bg-background p-3 text-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                />
                <InputError :message="replyForm.errors.body" />
                <Button type="submit" size="sm" :disabled="replyForm.processing">{{ replyForm.processing ? 'Posting…' : 'Post reply' }}</Button>
            </form>
            <ul v-if="thread.replies.length" class="mt-3 space-y-3 border-l-2 border-muted pl-4">
                <li v-for="reply in thread.replies" :key="reply.id" :class="{ 'opacity-70': reply.is_hidden }">
                    <div class="flex flex-wrap items-center gap-2 text-xs">
                        <span class="font-semibold">{{ reply.author }}</span>
                        <span
                            v-if="reply.author_role !== 'Student'"
                            class="rounded-full bg-primary px-2 py-0.5 text-[11px] font-semibold text-primary-foreground"
                            >{{ reply.author_role }}</span
                        >
                        <span class="text-muted-foreground">{{ when(reply.created_at) }}</span>
                        <span v-if="reply.is_hidden" class="rounded-full bg-destructive px-2 py-0.5 text-[11px] font-semibold text-white"
                            >Hidden</span
                        >
                    </div>
                    <p class="mt-1 whitespace-pre-wrap text-sm leading-relaxed">{{ reply.body }}</p>
                </li>
            </ul>
        </article>
    </section>
</template>
