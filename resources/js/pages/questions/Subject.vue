<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps<{
    exam: { id: number; name: string };
    subject: { id: number; name: string; slug: string };
    source: 'main' | 'past';
    counts: { main: number; past: number };
}>();

const mainUrl = computed(() => `/questions/subjects/${props.subject.slug}`);
const pastUrl = computed(() => `/questions/subjects/${props.subject.slug}/past-papers`);
const available = computed(() => (props.source === 'main' ? props.counts.main : props.counts.past));
const sourceLabel = computed(() => (props.source === 'main' ? 'Main MCQ Bank' : 'Past Paper MCQs'));

const form = useForm<{ count: number; category_id: number; source: 'main' | 'past' }>({
    count: 20,
    category_id: props.subject.id,
    source: props.source,
});

function start() {
    form.category_id = props.subject.id;
    form.source = props.source;
    form.post('/questions/sessions');
}
</script>
<template>
    <Head :title="subject.name" />
    <AppLayout
        :breadcrumbs="[
            { title: 'Question bank', href: '/questions/bank' },
            { title: subject.name, href: mainUrl },
        ]"
    >
        <div class="mx-auto w-full max-w-6xl space-y-6 p-6 md:p-10">
            <div>
                <p class="text-sm font-semibold text-primary">{{ exam.name }}</p>
                <h1 class="mt-2 text-3xl font-semibold">{{ subject.name }}</h1>
                <p class="mt-3 text-sm text-muted-foreground">Practise {{ sourceLabel.toLowerCase() }} questions in this subject.</p>
            </div>

            <nav class="flex gap-2 border-b" aria-label="Question sources">
                <Link
                    :href="mainUrl"
                    :aria-current="source === 'main' ? 'page' : undefined"
                    class="-mb-px border-b-2 px-4 py-2 text-sm font-semibold"
                    :class="source === 'main' ? 'border-primary text-primary' : 'border-transparent text-muted-foreground'"
                    >Main MCQ Bank ({{ counts.main }})</Link
                >
                <Link
                    :href="pastUrl"
                    :aria-current="source === 'past' ? 'page' : undefined"
                    class="-mb-px border-b-2 px-4 py-2 text-sm font-semibold"
                    :class="source === 'past' ? 'border-primary text-primary' : 'border-transparent text-muted-foreground'"
                    >Past Paper MCQs ({{ counts.past }})</Link
                >
            </nav>

            <div v-if="!available" class="rounded-xl border border-dashed p-10 text-center text-muted-foreground">
                No published {{ sourceLabel.toLowerCase() }} questions in this subject yet.
            </div>

            <div v-else class="flex flex-wrap items-end gap-4 rounded-xl border bg-card p-5">
                <label class="grid gap-2 text-sm font-medium"
                    >Questions per session<input
                        v-model.number="form.count"
                        type="number"
                        min="1"
                        max="100"
                        class="w-36 rounded-md border bg-background px-3 py-2"
                /></label>
                <Button :disabled="form.processing" @click="start">Start practice</Button>
                <p class="w-full text-xs text-muted-foreground">
                    Up to 100 questions per session. If fewer are available, all matching questions are included.
                </p>
                <InputError :message="form.errors.count || form.errors.category_id || form.errors.source" />
            </div>
        </div>
    </AppLayout>
</template>
