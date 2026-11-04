<script setup lang="ts">
import RichText from '@/components/practice/RichText.vue';
import type { QuestionResult } from '@/types/practice';
import { CheckCircle2, Clock, ExternalLink, Image as ImageIcon, Lightbulb, Search, Sparkles, Users, XCircle } from 'lucide-vue-next';
import { computed } from 'vue';
const props = defineProps<{ result: QuestionResult; selected: string | null; options: Record<string, string> }>();
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
                            :class="key === result.correct_option ? 'bg-green-600' : 'bg-primary/40'"
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

        <div v-if="result.key_points_html" class="rounded-xl border-l-4 border-primary bg-secondary/60 p-4">
            <h3 class="mb-2 flex items-center gap-2 text-sm font-semibold"><Lightbulb class="size-4 text-primary" aria-hidden="true" />Key points</h3>
            <RichText :html="result.key_points_html" class="text-sm" />
        </div>

        <div v-if="result.mnemonics_html" class="rounded-xl border-l-4 p-4" style="border-color: var(--amber); background: var(--tint-amber-bg)">
            <h3 class="mb-2 flex items-center gap-2 text-sm font-semibold" style="color: var(--tint-amber-fg)">
                <Sparkles class="size-4" aria-hidden="true" />Mnemonic
            </h3>
            <RichText :html="result.mnemonics_html" class="text-sm" />
        </div>

        <div v-if="result.youtube_id" class="space-y-2">
            <h3 class="text-sm font-semibold">Video</h3>
            <div class="aspect-video overflow-hidden rounded-xl border bg-black">
                <iframe
                    class="h-full w-full"
                    :src="'https://www.youtube-nocookie.com/embed/' + result.youtube_id"
                    title="Explanation video"
                    loading="lazy"
                    referrerpolicy="strict-origin-when-cross-origin"
                    allow="accelerometer; encrypted-media; gyroscope; picture-in-picture"
                    allowfullscreen
                />
            </div>
        </div>

        <div v-if="result.image_searches?.length" class="space-y-2">
            <h3 class="flex items-center gap-2 text-sm font-semibold">
                <Search class="size-4 text-primary" aria-hidden="true" />Search images for “{{ result.image_search }}”
            </h3>
            <ul class="flex flex-wrap gap-2">
                <li v-for="link in result.image_searches" :key="link.site">
                    <a
                        :href="link.url"
                        target="_blank"
                        rel="noopener noreferrer nofollow"
                        class="inline-flex items-center gap-1 rounded-full border bg-card px-3 py-1 text-xs font-medium hover:border-primary"
                        >{{ link.label }}<ExternalLink class="size-3" aria-hidden="true"
                    /></a>
                </li>
            </ul>
        </div>

        <div v-if="result.image_links?.length" class="space-y-2">
            <h3 class="flex items-center gap-2 text-sm font-semibold"><ImageIcon class="size-4 text-primary" aria-hidden="true" />Image links</h3>
            <ul class="flex flex-wrap gap-2">
                <li v-for="link in result.image_links" :key="link.url">
                    <a
                        :href="link.url"
                        target="_blank"
                        rel="noopener noreferrer nofollow"
                        class="inline-flex items-center gap-1 rounded-full border px-3 py-1 text-xs font-medium hover:border-primary"
                        >{{ link.label }}<ExternalLink class="size-3" aria-hidden="true"
                    /></a>
                </li>
            </ul>
        </div>

        <div v-if="result.references?.length" class="space-y-2">
            <h3 class="text-sm font-semibold">References</h3>
            <ol class="space-y-2 text-sm">
                <li v-for="(reference, index) in result.references" :key="index" class="flex gap-2">
                    <span
                        class="h-fit shrink-0 rounded px-1.5 py-0.5 text-[10px] font-bold uppercase"
                        :class="
                            reference.source === 'NICE'
                                ? 'bg-primary text-primary-foreground'
                                : reference.source === 'WHO'
                                  ? 'bg-sky-600 text-white'
                                  : 'bg-muted text-muted-foreground'
                        "
                        >{{ reference.source }}</span
                    >
                    <span class="min-w-0">
                        <a
                            v-if="reference.url"
                            :href="reference.url"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="font-medium text-primary underline-offset-2 hover:underline"
                            >{{ reference.title }}</a
                        ><span v-else>{{ reference.title }}</span>
                        <span v-if="reference.year" class="text-muted-foreground"> ({{ reference.year }})</span>
                    </span>
                </li>
            </ol>
        </div>
    </section>
</template>
