<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import { Medal, Trophy } from 'lucide-vue-next';

interface Entry {
    rank: number;
    name: string;
    correct: number;
    answered: number;
    accuracy: number;
    is_me: boolean;
}

const props = defineProps<{
    exam: { id: number; name: string };
    period: 'weekly' | 'all';
    entries: Entry[];
    me: Entry | null;
    qualified: number;
    optedIn: boolean;
    minAnswers: number;
    weekStart: string;
    refreshMinutes: number;
}>();

const tabs = [
    { key: 'weekly', label: 'This week' },
    { key: 'all', label: 'All time' },
] as const;
const weekStartLabel = new Date(props.weekStart).toLocaleDateString(undefined, { weekday: 'long', day: 'numeric', month: 'long' });
const meInTop = props.entries.some((entry) => entry.is_me);
function medal(rank: number): string {
    return rank === 1 ? 'text-amber' : rank === 2 ? 'text-slate-400' : rank === 3 ? 'text-orange-700' : '';
}
</script>
<template>
    <Head title="Leaderboard" />
    <AppLayout :breadcrumbs="[{ title: 'Leaderboard', href: '/leaderboard' }]">
        <div class="mx-auto w-full max-w-4xl space-y-6 p-6 md:p-10">
            <div>
                <p class="text-sm font-semibold text-primary">{{ exam.name }}</p>
                <h1 class="mt-2 flex items-center gap-2 text-3xl font-semibold">
                    <Trophy class="size-7 text-primary" aria-hidden="true" />Leaderboard
                </h1>
                <p class="mt-2 text-sm text-muted-foreground">
                    Ranked by correct first answers, then accuracy. Only your first answer to each question counts; review sessions and open mocks do
                    not. At least {{ minAnswers }} first answers are needed. Updated every {{ refreshMinutes }} minutes.
                </p>
            </div>

            <nav class="inline-flex rounded-lg border bg-card p-1" aria-label="Period">
                <Link
                    v-for="tab in tabs"
                    :key="tab.key"
                    :href="'/leaderboard?period=' + tab.key"
                    preserve-scroll
                    class="rounded-md px-4 py-1.5 text-sm font-medium"
                    :class="period === tab.key ? 'bg-primary text-primary-foreground' : 'text-muted-foreground hover:text-foreground'"
                    :aria-current="period === tab.key ? 'page' : undefined"
                    >{{ tab.label }}</Link
                >
            </nav>
            <p v-if="period === 'weekly'" class="text-xs text-muted-foreground">This week started on {{ weekStartLabel }} (00:00 UK time).</p>

            <div v-if="!optedIn" class="mw-logo-left mw-logo-soft flex flex-wrap items-center justify-between gap-3 rounded-xl border p-4 text-sm">
                <span>You are not on the leaderboard. Opt in with a display name to see your rank.</span>
                <Link href="/settings/profile" class="rounded-md bg-primary px-3 py-1.5 text-xs font-semibold text-primary-foreground">Opt in</Link>
            </div>
            <div v-else-if="!me" class="rounded-xl border border-dashed p-4 text-sm text-muted-foreground">
                Answer at least {{ minAnswers }} new questions {{ period === 'weekly' ? 'this week' : '' }} to appear in the ranking.
            </div>

            <div v-if="!entries.length" class="rounded-xl border border-dashed p-10 text-center text-sm text-muted-foreground">
                Nobody has qualified yet. Be the first.
            </div>
            <div v-else class="overflow-x-auto rounded-xl border bg-card">
                <table class="w-full text-sm">
                    <thead class="mw-logo-soft text-left text-xs uppercase tracking-wide text-muted-foreground">
                        <tr>
                            <th class="w-16 px-4 py-2">Rank</th>
                            <th class="px-4 py-2">Student</th>
                            <th class="px-4 py-2 text-right">Correct</th>
                            <th class="px-4 py-2 text-right">Answered</th>
                            <th class="px-4 py-2 text-right">Accuracy</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="entry in entries"
                            :key="entry.rank + entry.name"
                            class="border-t"
                            :class="entry.is_me ? 'mw-logo-soft font-semibold' : ''"
                        >
                            <td class="px-4 py-2 tabular-nums">
                                <span class="inline-flex items-center gap-1"
                                    ><Medal v-if="entry.rank <= 3" class="size-4" :class="medal(entry.rank)" aria-hidden="true" />{{
                                        entry.rank
                                    }}</span
                                >
                            </td>
                            <td class="px-4 py-2">{{ entry.name }}<span v-if="entry.is_me" class="ml-1 text-xs text-primary">(you)</span></td>
                            <td class="px-4 py-2 text-right tabular-nums">{{ entry.correct }}</td>
                            <td class="px-4 py-2 text-right tabular-nums">{{ entry.answered }}</td>
                            <td class="px-4 py-2 text-right tabular-nums">{{ entry.accuracy }}%</td>
                        </tr>
                        <tr v-if="me && !meInTop" class="mw-logo-soft border-t-2 font-semibold">
                            <td class="px-4 py-2 tabular-nums">{{ me.rank }}</td>
                            <td class="px-4 py-2">{{ me.name }} <span class="text-xs text-primary">(you)</span></td>
                            <td class="px-4 py-2 text-right tabular-nums">{{ me.correct }}</td>
                            <td class="px-4 py-2 text-right tabular-nums">{{ me.answered }}</td>
                            <td class="px-4 py-2 text-right tabular-nums">{{ me.accuracy }}%</td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <p class="text-xs text-muted-foreground">{{ qualified }} students qualified.</p>
        </div>
    </AppLayout>
</template>
