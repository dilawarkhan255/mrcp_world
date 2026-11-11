<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { useForm } from '@inertiajs/vue3';
import { Flag } from 'lucide-vue-next';
import { ref } from 'vue';
const props = defineProps<{ questionId: number; code?: string | null }>();
const open = ref(false);
const sent = ref(false);
const form = useForm({ type: 'content', message: '', question_id: props.questionId });
function submit() {
    form.question_id = props.questionId;
    form.post('/feedback', {
        preserveScroll: true,
        onSuccess: () => {
            sent.value = true;
            form.reset('message');
        },
    });
}
function toggle(value: boolean) {
    open.value = value;
    if (value) sent.value = false;
}
</script>
<template>
    <Dialog :open="open" @update:open="toggle">
        <DialogTrigger as-child>
            <Button variant="ghost" size="sm" class="text-muted-foreground"><Flag class="size-4 text-primary" aria-hidden="true" />Report MCQ</Button>
        </DialogTrigger>
        <DialogContent class="sm:max-w-lg">
            <DialogHeader>
                <DialogTitle>Report this MCQ</DialogTitle>
                <DialogDescription
                    >Tell the editors what is wrong{{ code ? ' with ' + code : '' }}: a wrong answer, an outdated guideline, a typo or a broken
                    image.</DialogDescription
                >
            </DialogHeader>
            <p v-if="sent" role="status" class="rounded-lg bg-secondary p-3 text-sm text-secondary-foreground">
                Thank you. The report has been sent to the editors.
            </p>
            <form v-else id="report-mcq" class="space-y-2" @submit.prevent="submit">
                <label for="report-message" class="text-sm font-medium">What needs fixing?</label>
                <textarea
                    id="report-message"
                    v-model="form.message"
                    rows="5"
                    maxlength="4000"
                    required
                    class="w-full rounded-md border bg-background p-3 text-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                    placeholder="For example: NICE NG136 changed the target blood pressure in 2023…"
                />
                <InputError :message="form.errors.message || form.errors.question_id" />
            </form>
            <DialogFooter>
                <Button v-if="!sent" type="submit" form="report-mcq" :disabled="form.processing || form.message.trim().length < 10">{{
                    form.processing ? 'Sending…' : 'Send report'
                }}</Button>
                <Button v-else variant="outline" @click="toggle(false)">Close</Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
