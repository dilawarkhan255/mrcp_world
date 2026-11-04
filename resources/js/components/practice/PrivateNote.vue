<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { useForm } from '@inertiajs/vue3';
import { NotebookPen } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
/**
 * A private note on a question. Notes live on the bookmark of the question,
 * so saving a note also bookmarks it; both appear under My Notes.
 */
const props = defineProps<{ questionId: number; bookmarkId?: number | null; note?: string | null }>();
const form = useForm({ question_id: props.questionId, note: props.note ?? '' });
const saved = ref(false);
watch(
    () => [props.questionId, props.note] as const,
    ([questionId, note]) => {
        form.question_id = questionId;
        form.note = note ?? '';
        form.clearErrors();
        saved.value = false;
    },
);
const dirty = computed(() => form.note !== (props.note ?? ''));
function save() {
    const options = { preserveScroll: true, onSuccess: () => (saved.value = true) };
    const payload = form.transform((data) => ({ ...data, note: data.note.trim() === '' ? null : data.note }));
    if (props.bookmarkId) {
        payload.patch('/revision/bookmarks/' + props.bookmarkId, options);
    } else {
        payload.post('/revision/bookmarks', options);
    }
}
</script>
<template>
    <section class="space-y-2 rounded-xl border bg-card p-4">
        <label :for="'note-' + questionId" class="flex items-center gap-2 text-sm font-semibold"
            ><NotebookPen class="size-4 text-primary" aria-hidden="true" />My private note</label
        >
        <textarea
            :id="'note-' + questionId"
            v-model="form.note"
            rows="4"
            maxlength="4000"
            class="w-full rounded-md border bg-background p-3 text-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
            placeholder="Only you can see this note. It is saved with your bookmarks."
            @input="saved = false"
        />
        <InputError :message="form.errors.note" />
        <div class="flex items-center justify-between gap-2">
            <span class="text-xs text-muted-foreground" role="status">{{ saved ? 'Saved' : dirty ? 'Unsaved changes' : '' }}</span>
            <Button size="sm" variant="outline" :disabled="form.processing || !dirty" @click="save">{{
                form.processing ? 'Saving…' : 'Save note'
            }}</Button>
        </div>
    </section>
</template>
