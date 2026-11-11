<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import YajraTable, { type YajraColumn } from '@/components/YajraTable.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { escapeHtml, openAction, progressColumn, startedColumn, statusColumn } from '@/lib/session-table';
import type { MockLimits, MockPreset } from '@/types/practice';
import { Head, useForm } from '@inertiajs/vue3';
defineProps<{
    exam: { id: number; name: string };
    presets: MockPreset[];
    availableQuestions: number;
    limits: MockLimits;
}>();
const form = useForm<{ count: number; minutes: number }>({ count: 20, minutes: 30 });
function start(count: number, minutes: number) {
    form.count = count;
    form.minutes = minutes;
    form.post('/questions/mocks');
}
const attemptColumns: YajraColumn[] = [
    {
        data: 'id',
        title: 'Mock',
        orderable: true,
        render: (value, row) =>
            '#' +
            escapeHtml(value) +
            '<span class="mt-1 block text-xs text-muted-foreground">' +
            escapeHtml(row.total_questions) +
            ' questions</span>',
    },
    startedColumn,
    progressColumn,
    {
        data: 'correct_count',
        title: 'Score',
        orderable: true,
        render: (value, row) =>
            escapeHtml(value) +
            ' / ' +
            escapeHtml(row.total_questions) +
            '<span class="mt-1 block text-xs text-muted-foreground">' +
            escapeHtml(row.score) +
            '%</span>',
    },
    { data: 'minutes', title: 'Time used', render: (value) => (value === null ? '—' : escapeHtml(value) + ' min') },
    statusColumn,
];
const attemptActions = [openAction(() => '/questions/mocks/')];
</script>
<template>
    <Head title="Mock exams" />
    <AppLayout :breadcrumbs="[{ title: 'Mock exams', href: '/questions/mocks' }]">
        <div class="mx-auto w-full max-w-6xl space-y-6 p-6 md:p-10">
            <div>
                <p class="text-sm font-semibold text-primary">{{ exam.name }}</p>
                <h1 class="mt-2 text-3xl font-semibold">Mock exams</h1>
                <p class="mt-3 text-sm text-muted-foreground">
                    Timed papers drawn from the published question bank. The countdown is enforced by the server, and answers are only revealed once
                    the mock is closed.
                </p>
            </div>
            <section class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <article
                    v-for="preset in presets"
                    :key="preset.title"
                    class="relative flex flex-col overflow-hidden rounded-xl border bg-card p-5 transition hover:-translate-y-0.5 hover:shadow-md"
                >
                    <span class="mw-logo-line absolute inset-x-0 top-0 h-1" aria-hidden="true" />
                    <h2 class="font-semibold">{{ preset.title }}</h2>
                    <p class="mt-2 text-sm text-muted-foreground">{{ preset.questions }} questions · {{ preset.minutes }} minutes</p>
                    <Button class="mt-4 w-full" :disabled="form.processing || !availableQuestions" @click="start(preset.questions, preset.minutes)"
                        >Start mock</Button
                    >
                </article>
            </section>
            <section class="mw-logo-left rounded-xl border bg-card p-5">
                <h2 class="font-semibold">Custom mock</h2>
                <div class="mt-4 flex flex-wrap items-end gap-4">
                    <label class="grid gap-2 text-sm font-medium"
                        >Questions<input
                            placeholder="e.g. 40"
                            required
                            v-model.number="form.count"
                            type="number"
                            :min="1"
                            :max="limits.maxQuestions"
                            class="w-36 rounded-md border bg-background px-3 py-2"
                    /></label>
                    <label class="grid gap-2 text-sm font-medium"
                        >Minutes<input
                            placeholder="e.g. 90"
                            required
                            v-model.number="form.minutes"
                            type="number"
                            :min="1"
                            :max="limits.maxMinutes"
                            class="w-36 rounded-md border bg-background px-3 py-2"
                    /></label>
                    <Button :disabled="form.processing || !availableQuestions" @click="start(form.count, form.minutes)">Start custom mock</Button>
                </div>
                <p class="mt-4 text-xs text-muted-foreground">
                    Up to {{ limits.maxQuestions }} questions and {{ limits.maxMinutes }} minutes per mock. If fewer questions are published, the mock
                    contains the available ones.
                </p>
                <InputError class="mt-2" :message="form.errors.count || form.errors.minutes" />
            </section>
            <section class="space-y-3">
                <h2 class="flex items-center gap-2 text-lg font-semibold">
                    <span class="mw-logo-bar h-5 w-1 rounded-full" aria-hidden="true" />Recent attempts
                </h2>
                <YajraTable
                    url="/questions/mocks/data"
                    :columns="attemptColumns"
                    :actions="attemptActions"
                    search-placeholder="Mock number…"
                    empty-text="No mock exams attempted yet."
                />
            </section>
        </div>
    </AppLayout>
</template>
