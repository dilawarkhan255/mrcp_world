<script setup lang="ts">
import RichText from '@/components/practice/RichText.vue';
import type { QuestionResult } from '@/types/practice';
import { CheckCircle2, Clock, Lightbulb, Sparkles, Users, XCircle } from 'lucide-vue-next';
import { computed } from 'vue';
const props = defineProps<{
    result: QuestionResult;
    selected: string | null;
    options: Record<string, string>;
}>();
const candidates = computed(() => props.result.candidates ?? null);
function seconds(value: number | null | undefined): string {
    if (value === null || value === undefined) return '—';
    if (value < 60) return value + 's';
    return Math.floor(value / 60) + 'm ' + String(value % 60).padStart(2, '0') + 's';
}
</script>
<template>
    <section role="status" class="space-y-5">
        <div
            class="flex items-start gap-3 rounded-xl border p-4"
            :class="result.is_correct ? 'border-green-600/40 bg-green-50 dark:bg-green-950/40' : 'border-red-500/40 bg-red-50 dark:bg-red-950/40'"
        >
            <component
                :is="result.is_correct ? CheckCircle2 : XCircle"
                class="mt-0.5 size-5 shrink-0"
                :class="result.is_correct ? 'text-green-700 dark:text-green-400' : 'text-destructive'"
                aria-hidden="true"
            />
            <div class="min-w-0">
                <h2 class="font-semibold" :class="result.is_correct ? 'text-green-800 dark:text-green-300' : 'text-destructive'">
                    {{ result.is_correct ? 'Correct answer' : 'Incorrect answer' }}
                </h2>
                <p class="mt-1 text-sm">
                    Correct option: <strong>{{ result.correct_option }}</strong>
                    <span v-if="selected && !result.is_correct"> · You chose {{ selected }}</span>
                    <span v-if="result.time_seconds !== null && result.time_seconds !== undefined" class="text-muted-foreground">
                        · Your time {{ seconds(result.time_seconds) }}</span
                    >
                </p>
            </div>
        </div>

        <div v-if="candidates" class="rounded-xl border bg-card p-4">
            <h3 class="flex items-center gap-2 text-sm font-semibold">
                <Users class="size-4 text-primary" aria-hidden="true" />Candidate performance
            </h3>
            <dl class="mt-3 grid grid-cols-3 gap-3 text-center">
                <div class="rounded-lg bg-muted/60 p-3">
                    <dt class="text-[11px] uppercase tracking-wide text-muted-foreground">Candidates correct</dt>
                    <dd class="text-xl font-semibold tabular-nums">{{ candidates.correct_percent }}%</dd>
                </div>
                <div class="rounded-lg bg-muted/60 p-3">
                    <dt class="text-[11px] uppercase tracking-wide text-muted-foreground">Topic average</dt>
                    <dd class="text-xl font-semibold tabular-nums">{{ candidates.topic_percent === null ? '—' : candidates.topic_percent + '%' }}</dd>
                </div>
                <div class="rounded-lg bg-muted/60 p-3">
                    <dt class="flex items-center justify-center gap-1 text-[11px] uppercase tracking-wide text-muted-foreground">
                        <Clock class="size-3" aria-hidden="true" />Average time
                    </dt>
                    <dd class="text-xl font-semibold tabular-nums">{{ seconds(candidates.average_seconds) }}</dd>
                </div>
            </dl>
            <ul class="mt-4 space-y-1.5" aria-label="How candidates answered">
                <li v-for="(_, key) in options" :key="key" class="flex items-center gap-2 text-xs">
                    <span class="w-4 font-semibold">{{ key }}</span>
                    <span class="h-2 flex-1 overflow-hidden rounded-full bg-muted">
                        <span
                            class="block h-full rounded-full"
                            :class="key === result.correct_option ? 'bg-green-600' : 'mw-logo-line opacity-50'"
                            :style="{ width: (candidates.options[key] ?? 0) + '%' }"
                        />
                    </span>
                    <span class="w-9 text-right tabular-nums text-muted-foreground">{{ candidates.options[key] ?? 0 }}%</span>
                </li>
            </ul>
            <p class="mt-3 text-[11px] text-muted-foreground">
                Based on {{ candidates.attempts.toLocaleString() }} answers. Refreshed every few minutes.
            </p>
        </div>

        <div v-if="result.explanation_html || result.explanation" class="space-y-2">
            <h3 class="text-sm font-semibold">Explanation</h3>
            <RichText :html="result.explanation_html" :text="result.explanation" class="text-sm" />
        </div>

        <div v-if="result.key_points_html" class="mw-logo-left rounded-xl bg-secondary/60 p-4">
            <h3 class="mb-2 flex items-center gap-2 text-sm font-semibold"><Lightbulb class="size-4 text-primary" aria-hidden="true" />Key points</h3>
            <RichText :html="result.key_points_html" class="text-sm" />
        </div>

        <div v-if="result.mnemonics_html" class="rounded-xl border-l-4 p-4" style="border-color: var(--amber); background: var(--tint-amber-bg)">
            <h3 class="mb-2 flex items-center gap-2 text-sm font-semibold" style="color: var(--tint-amber-fg)">
                <Sparkles class="size-4" aria-hidden="true" />Mnemonic
            </h3>
            <RichText :html="result.mnemonics_html" class="text-sm" />
        </div>
    </section>
</template>
