<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import SimplePagination from '@/components/SimplePagination.vue';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import type { MockAttempt, MockLimits, MockPreset, PageSlice } from '@/types/practice';
import { Head, useForm } from '@inertiajs/vue3';
defineProps<{
    exam: { id: number; name: string };
    presets: MockPreset[];
    availableQuestions: number;
    limits: MockLimits;
    attempts: PageSlice<MockAttempt>;
}>();
const form = useForm<{ count: number; minutes: number }>({ count: 20, minutes: 30 });
function start(count: number, minutes: number) {
    form.count = count;
    form.minutes = minutes;
    form.post('/questions/mocks');
}
function score(attempt: MockAttempt): number {
    if (!attempt.total_questions) return 0;
    return Math.round((attempt.correct_count / attempt.total_questions) * 100);
}
function minutesUsed(attempt: MockAttempt): number {
    if (!attempt.started_at) return 0;
    const end = attempt.completed_at ? new Date(attempt.completed_at).getTime() : Date.now();
    return Math.max(0, Math.round((end - new Date(attempt.started_at).getTime()) / 60000));
}
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
                <article v-for="preset in presets" :key="preset.title" class="flex flex-col rounded-xl border bg-card p-5">
                    <h2 class="font-semibold">{{ preset.title }}</h2>
                    <p class="mt-2 text-sm text-muted-foreground">{{ preset.questions }} questions · {{ preset.minutes }} minutes</p>
                    <Button class="mt-4 w-full" :disabled="form.processing || !availableQuestions" @click="start(preset.questions, preset.minutes)"
                        >Start mock</Button
                    >
                </article>
            </section>
            <section class="rounded-xl border bg-card p-5">
                <h2 class="font-semibold">Custom mock</h2>
                <div class="mt-4 flex flex-wrap items-end gap-4">
                    <label class="grid gap-2 text-sm font-medium"
                        >Questions<input
                            v-model.number="form.count"
                            type="number"
                            :min="1"
                            :max="limits.maxQuestions"
                            class="w-36 rounded-md border bg-background px-3 py-2"
                    /></label>
                    <label class="grid gap-2 text-sm font-medium"
                        >Minutes<input
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
                <h2 class="text-lg font-semibold">Recent attempts</h2>
                <div v-if="!attempts.data.length" class="rounded-xl border border-dashed p-8 text-center">
                    <p class="text-sm text-muted-foreground">No mock exams attempted yet.</p>
                </div>
                <div v-else class="overflow-x-auto rounded-xl border bg-card">
                    <table class="w-full text-left text-sm">
                        <thead class="border-b bg-muted/40">
                            <tr>
                                <th class="p-4">Mock</th>
                                <th class="p-4">Started</th>
                                <th class="p-4">Score</th>
                                <th class="p-4">Time used</th>
                                <th class="p-4">Status</th>
                                <th class="p-4">Open</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="attempt in attempts.data" :key="attempt.id" class="border-b last:border-0">
                                <td class="p-4">
                                    #{{ attempt.id
                                    }}<span class="mt-1 block text-xs text-muted-foreground">{{ attempt.total_questions }} questions</span>
                                </td>
                                <td class="p-4">{{ attempt.started_at ? new Date(attempt.started_at).toLocaleString() : '' }}</td>
                                <td class="p-4">
                                    {{ attempt.correct_count }} / {{ attempt.total_questions }}
                                    <span class="mt-1 block text-xs text-muted-foreground">{{ score(attempt) }}%</span>
                                </td>
                                <td class="p-4">{{ minutesUsed(attempt) }} min</td>
                                <td class="p-4">{{ attempt.completed_at ? 'Completed' : 'In progress' }}</td>
                                <td class="p-4">
                                    <a :href="'/questions/mocks/' + attempt.id" class="font-semibold text-primary">{{
                                        attempt.completed_at ? 'Review' : 'Resume'
                                    }}</a>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <SimplePagination :previous="attempts.prev_page_url" :next="attempts.next_page_url" :page="attempts.current_page" />
            </section>
        </div>
    </AppLayout>
</template>
