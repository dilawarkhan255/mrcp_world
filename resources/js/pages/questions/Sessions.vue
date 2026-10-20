<script setup lang="ts">
import SimplePagination from '@/components/SimplePagination.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import type { PageSlice, SessionSummary } from '@/types/practice';
import { Head, Link } from '@inertiajs/vue3';
defineProps<{ sessions: PageSlice<SessionSummary> }>();
</script>
<template>
    <Head title="Previous sessions" />
    <AppLayout :breadcrumbs="[{ title: 'Previous sessions', href: '/questions/sessions' }]">
        <div class="mx-auto w-full max-w-6xl space-y-6 p-6 md:p-10">
            <h1 class="text-3xl font-semibold">Previous sessions</h1>
            <p class="text-sm text-muted-foreground">Resume unfinished practice or review your saved answers.</p>
            <div v-if="!sessions.data.length" class="rounded-xl border border-dashed p-10 text-center">
                <p class="text-muted-foreground">No sessions yet.</p>
                <Link href="/questions/bank" class="mt-4 inline-block font-semibold text-primary">Open question bank →</Link>
            </div>
            <div class="overflow-x-auto rounded-xl border bg-card">
                <table v-if="sessions.data.length" class="w-full text-left text-sm">
                    <thead class="border-b bg-muted/40">
                        <tr>
                            <th class="p-4">Session</th>
                            <th class="p-4">Progress</th>
                            <th class="p-4">Correct</th>
                            <th class="p-4">Status</th>
                            <th class="p-4">Open</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="session in sessions.data" :key="session.id" class="border-b last:border-0">
                            <td class="p-4">
                                #{{ session.id
                                }}<span class="mt-1 block text-xs text-muted-foreground">{{
                                    session.created_at ? new Date(session.created_at).toLocaleString() : ''
                                }}</span>
                            </td>
                            <td class="p-4">{{ session.answered_count }} / {{ session.total_questions }}</td>
                            <td class="p-4">{{ session.correct_count }}</td>
                            <td class="p-4">{{ session.completed_at ? 'Completed' : 'In progress' }}</td>
                            <td class="p-4">
                                <Link :href="'/questions/sessions/' + session.id" class="font-semibold text-primary">{{
                                    session.completed_at ? 'Review' : 'Resume'
                                }}</Link>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <SimplePagination :previous="sessions.prev_page_url" :next="sessions.next_page_url" :page="sessions.current_page" />
        </div>
    </AppLayout>
</template>
