<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import SimplePagination from '@/components/SimplePagination.vue';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BookmarkItem } from '@/types/practice';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
const props = defineProps<{
    exam: { id: number; name: string };
    bookmarks: { data: BookmarkItem[]; current_page: number; prev_page_url: string | null; next_page_url: string | null };
    stats: { total: number; withNotes: number };
    maxQuestions: number;
    notesOnly: boolean;
}>();
const pageTitle = computed(() => (props.notesOnly ? 'My notes' : 'Bookmarks and notes'));
const pageHref = computed(() => (props.notesOnly ? '/revision/notes' : '/revision/bookmarks'));
const editing = ref<number | null>(null);
const note = useForm<{ id: number; note: string }>({ id: 0, note: '' });
const practiceForm = useForm<{ question_ids: string }>({ question_ids: '' });
function startEdit(bookmark: BookmarkItem) {
    editing.value = editing.value === bookmark.id ? null : bookmark.id;
    note.id = bookmark.id;
    note.note = bookmark.note ?? '';
    note.clearErrors();
}
function saveNote() {
    note.patch('/revision/bookmarks/' + note.id);
}
function remove(bookmark: BookmarkItem) {
    useForm<{ id: number }>({ id: bookmark.id }).delete('/revision/bookmarks/' + bookmark.id);
}
</script>
<template>
    <Head :title="pageTitle" />
    <AppLayout :breadcrumbs="[{ title: pageTitle, href: pageHref }]">
        <div class="mx-auto w-full max-w-4xl space-y-6 p-6 md:p-10">
            <div>
                <p class="text-sm font-semibold text-primary">{{ exam.name }}</p>
                <h1 class="mt-2 text-3xl font-semibold">{{ pageTitle }}</h1>
                <p class="mt-3 text-sm text-muted-foreground">
                    {{ stats.total }} saved questions, {{ stats.withNotes }} with a note. Bookmark a question while you practise to keep it here.
                </p>
            </div>
            <nav class="flex gap-2 border-b" aria-label="My revision">
                <Link
                    href="/revision/notes"
                    :aria-current="notesOnly ? 'page' : undefined"
                    class="-mb-px border-b-2 px-4 py-2 text-sm font-semibold"
                    :class="notesOnly ? 'border-primary text-primary' : 'border-transparent text-muted-foreground'"
                    >Notes ({{ stats.withNotes }})</Link
                >
                <Link
                    href="/revision/bookmarks"
                    :aria-current="!notesOnly ? 'page' : undefined"
                    class="-mb-px border-b-2 px-4 py-2 text-sm font-semibold"
                    :class="!notesOnly ? 'border-primary text-primary' : 'border-transparent text-muted-foreground'"
                    >All bookmarks ({{ stats.total }})</Link
                >
            </nav>
            <div class="flex flex-wrap items-center gap-3 rounded-xl border bg-card p-5">
                <Button :disabled="practiceForm.processing || !stats.total" @click="practiceForm.post('/revision/bookmarks/practice')">
                    Practise bookmarks
                </Button>
                <p class="w-full text-xs text-muted-foreground">
                    A session of up to {{ maxQuestions }} bookmarked questions. Answers are scored as usual.
                </p>
                <InputError :message="practiceForm.errors.question_ids" />
            </div>
            <div v-if="!bookmarks.data.length && notesOnly" class="rounded-xl border border-dashed p-8 text-center">
                <h2 class="font-semibold">No notes yet</h2>
                <p class="mt-2 text-sm text-muted-foreground">Add a note to a bookmarked question and it will appear here.</p>
            </div>
            <div v-else-if="!bookmarks.data.length" class="rounded-xl border border-dashed p-8 text-center">
                <h2 class="font-semibold">No bookmarks yet</h2>
                <p class="mt-2 text-sm text-muted-foreground">Save a question from any practice session and it will appear here.</p>
            </div>
            <article v-for="bookmark in bookmarks.data" :key="bookmark.id" class="rounded-xl border bg-card p-5">
                <div class="flex flex-wrap items-center gap-2 text-xs">
                    <span v-if="bookmark.code" class="font-semibold uppercase tracking-wide text-muted-foreground">{{ bookmark.code }}</span>
                    <span
                        v-if="bookmark.category"
                        class="rounded-full bg-secondary px-2 py-0.5 text-[11px] font-semibold text-secondary-foreground"
                        >{{ bookmark.category }}</span
                    >
                    <span v-if="bookmark.created_at" class="text-muted-foreground">Saved {{ new Date(bookmark.created_at).toLocaleString() }}</span>
                </div>
                <p class="mt-3 whitespace-pre-wrap text-sm leading-relaxed">{{ bookmark.body }}</p>
                <div class="mt-4 rounded-lg bg-muted/40 p-3">
                    <p class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">Note</p>
                    <p v-if="bookmark.note && editing !== bookmark.id" class="mt-1 whitespace-pre-wrap text-sm leading-relaxed">
                        {{ bookmark.note }}
                    </p>
                    <p v-else-if="!bookmark.note && editing !== bookmark.id" class="mt-1 text-sm text-muted-foreground">No note yet.</p>
                    <form v-if="editing === bookmark.id" class="mt-2 space-y-2" @submit.prevent="saveNote">
                        <textarea
                            v-model="note.note"
                            rows="3"
                            maxlength="4000"
                            placeholder="Why does this question matter?"
                            class="w-full rounded-md border bg-background px-3 py-2 text-sm"
                        />
                        <InputError :message="note.errors.note" />
                        <div class="flex gap-2">
                            <Button type="submit" size="sm" :disabled="note.processing">{{ note.processing ? 'Saving…' : 'Save note' }}</Button>
                            <Button type="button" size="sm" variant="outline" @click="editing = null">Cancel</Button>
                        </div>
                    </form>
                </div>
                <div class="mt-4 flex flex-wrap gap-4 text-xs font-semibold">
                    <button type="button" class="text-primary" @click="startEdit(bookmark)">
                        {{ editing === bookmark.id ? 'Cancel' : 'Edit note' }}
                    </button>
                    <button type="button" class="text-destructive" @click="remove(bookmark)">Remove bookmark</button>
                </div>
            </article>
            <SimplePagination :previous="bookmarks.prev_page_url" :next="bookmarks.next_page_url" :page="bookmarks.current_page" />
        </div>
    </AppLayout>
</template>
