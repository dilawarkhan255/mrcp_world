<script setup lang="ts">
import YajraTable, { type YajraColumn } from '@/components/YajraTable.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { escapeHtml, openAction, progressColumn, startedColumn, statusColumn } from '@/lib/session-table';
import { Head, Link } from '@inertiajs/vue3';

defineProps<{ exam?: { id: number; name: string } }>();

const columns: YajraColumn[] = [
    {
        data: 'id',
        title: 'Session',
        orderable: true,
        render: (value, row) =>
            '#' + escapeHtml(value) + '<span class="mt-1 block text-xs text-muted-foreground">' + escapeHtml(row.kind) + '</span>',
    },
    startedColumn,
    progressColumn,
    {
        data: 'correct_count',
        title: 'Correct',
        orderable: true,
        render: (value, row) =>
            escapeHtml(value) +
            (row.accuracy === null ? '' : '<span class="mt-1 block text-xs text-muted-foreground">' + escapeHtml(row.accuracy) + '%</span>'),
    },
    statusColumn,
];
// Mocks open on their own timed page.
const actions = [openAction((row) => (row.kind === 'Mock' ? '/questions/mocks/' : '/questions/sessions/'))];
</script>
<template>
    <Head title="Previous sessions" />
    <AppLayout :breadcrumbs="[{ title: 'Previous sessions', href: '/questions/sessions' }]">
        <div class="mx-auto w-full max-w-6xl space-y-6 p-6 md:p-10">
            <div class="flex flex-wrap items-end justify-between gap-4">
                <div>
                    <p v-if="exam" class="text-sm font-semibold text-primary">{{ exam.name }}</p>
                    <h1 class="mt-1 text-3xl font-semibold">Previous sessions</h1>
                    <p class="mt-2 text-sm text-muted-foreground">Resume unfinished practice or review your saved answers.</p>
                </div>
                <Link href="/questions/bank" class="text-sm font-semibold text-primary">Open question bank →</Link>
            </div>
            <YajraTable
                url="/questions/sessions/data"
                :columns="columns"
                :actions="actions"
                search-placeholder="Session number…"
                empty-text="No sessions yet. Start one from the question bank."
            />
        </div>
    </AppLayout>
</template>
