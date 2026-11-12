<script setup lang="ts">
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import {
    ArrowRight,
    BookOpenCheck,
    Brain,
    CalendarClock,
    ChevronDown,
    Flame,
    History,
    Info,
    Play,
    Sparkles,
    Target,
    Trophy,
    Zap,
} from 'lucide-vue-next';
import { computed, onBeforeUnmount, reactive, ref, watch } from 'vue';

/**
 * The Knowledge Tutor: pick subjects, a minimum high-yield rating, a
 * difficulty range and how facts are chosen, then answer one fact at a time.
 * The counts at the top are the student's own progress.
 */
interface Subject {
    id: number;
    name: string;
    total: number;
    seen: number;
    recalled: number;
    mastered: number;
    weak: number;
    due: number;
    new: number;
}
interface Filters {
    categories: number[];
    rating: string;
    difficulty: [number, number];
    mode: string;
}
const props = defineProps<{
    exam: { id: number; name: string };
    overview: {
        subjects: Subject[];
        totals: {
            total: number;
            seen: number;
            recalled: number;
            mastered: number;
            weak: number;
            due: number;
            new: number;
            top_total: number;
            top_seen: number;
            top_recalled: number;
            today: number;
        };
    };
    filters: Filters;
    count: { total: number; ready: number };
    ratings: Record<string, string>;
    modes: Record<string, string>;
    runs: { id: number; label: string; answered: number; updated: string | null }[];
}>();

const form = reactive<Filters>({
    categories: [...props.filters.categories],
    rating: props.filters.rating,
    difficulty: [props.filters.difficulty[0], props.filters.difficulty[1]],
    mode: props.filters.mode,
});
const count = ref(props.count);
const counting = ref(false);
const starting = ref(false);
const showHow = ref(false);

const totals = computed(() => props.overview.totals);
const percent = (part: number, whole: number) => (whole > 0 ? Math.round((part / whole) * 100) : 0);
const tested = computed(() => percent(totals.value.top_seen, totals.value.top_total));
const recall = computed(() => percent(totals.value.top_recalled, totals.value.top_total));
const allSubjects = computed(() => form.categories.length === 0);

function toggleAll() {
    form.categories = [];
}
function toggleSubject(id: number) {
    form.categories = form.categories.includes(id) ? form.categories.filter((value) => value !== id) : [...form.categories, id];
}
function setLow(event: Event) {
    const value = Number((event.target as HTMLInputElement).value);
    form.difficulty = [Math.min(value, form.difficulty[1]), form.difficulty[1]];
}
function setHigh(event: Event) {
    const value = Number((event.target as HTMLInputElement).value);
    form.difficulty = [form.difficulty[0], Math.max(value, form.difficulty[0])];
}
const difficultyLabel = computed(() => {
    const [low, high] = form.difficulty;
    if (low === 0 && high === 100) return 'Any difficulty';
    return `${low}–${high}% forgotten`;
});

// "N facts found" follows the filters (debounced).
let timer: ReturnType<typeof setTimeout> | undefined;
let controller: AbortController | undefined;
watch(
    form,
    () => {
        clearTimeout(timer);
        timer = setTimeout(refreshCount, 300);
    },
    { deep: true },
);
onBeforeUnmount(() => {
    clearTimeout(timer);
    controller?.abort();
});
async function refreshCount() {
    controller?.abort();
    controller = new AbortController();
    const params = new URLSearchParams({ rating: form.rating, mode: form.mode });
    form.categories.forEach((id) => params.append('categories[]', String(id)));
    form.difficulty.forEach((value) => params.append('difficulty[]', String(value)));
    counting.value = true;
    try {
        const response = await fetch('/tutor/count?' + params.toString(), {
            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            signal: controller.signal,
        });
        if (response.ok) count.value = await response.json();
    } catch {
        // A newer request replaced this one, or the network failed: keep the last count.
    } finally {
        counting.value = false;
    }
}

function start() {
    if (starting.value || count.value.ready === 0) return;
    starting.value = true;
    router.post('/tutor/start', { ...form }, { onFinish: () => (starting.value = false) });
}

const MODE_ICONS: Record<string, typeof Zap> = { spaced: Brain, new: Sparkles, due: CalendarClock, weak: Target };
const RATING_HELP = 'A++ facts come up again and again in the exam; C facts are good to know. The tutor always asks the most high-yield facts first.';
</script>

<template>
    <Head title="Knowledge Tutor" />
    <AppLayout :breadcrumbs="[{ title: 'Knowledge Tutor', href: '/tutor' }]">
        <div class="mx-auto flex w-full max-w-7xl flex-col gap-6 p-4 md:p-8">
            <!-- Intro and progress -->
            <section class="relative overflow-hidden rounded-2xl border bg-card shadow-sm">
                <span class="brand-rainbow absolute inset-x-0 top-0 h-1" aria-hidden="true" />
                <div class="grid gap-6 p-6 md:p-8 lg:grid-cols-[minmax(0,1.3fr)_minmax(0,1fr)]">
                    <div>
                        <p class="text-sm font-semibold text-primary">{{ exam.name }}</p>
                        <h1 class="mt-1 flex items-center gap-2 text-3xl font-semibold">
                            <Zap class="size-7 text-primary" aria-hidden="true" />Knowledge Tutor
                        </h1>
                        <p class="mt-3 max-w-2xl text-sm leading-relaxed text-muted-foreground">
                            High-yield exam facts, asked one at a time. After each answer you say how well you remembered it, and the tutor schedules
                            the fact again just before you would forget it (spaced repetition). The most important facts always come first.
                        </p>
                        <button
                            type="button"
                            class="mt-3 inline-flex items-center gap-1 text-sm font-semibold text-primary hover:underline"
                            :aria-expanded="showHow"
                            @click="showHow = !showHow"
                        >
                            <Info class="size-4" aria-hidden="true" />How it works
                            <ChevronDown class="size-4 transition" :class="showHow ? 'rotate-180' : ''" aria-hidden="true" />
                        </button>
                        <ol v-if="showHow" class="mt-3 grid gap-2 text-sm text-muted-foreground sm:grid-cols-2">
                            <li class="rounded-xl bg-muted/50 p-3">
                                <strong class="text-foreground">1. Choose</strong> subjects, how high-yield and how hard the facts should be.
                            </li>
                            <li class="rounded-xl bg-muted/50 p-3">
                                <strong class="text-foreground">2. Think</strong> of the answer, then press <kbd>Space</kbd> to see the fact.
                            </li>
                            <li class="rounded-xl bg-muted/50 p-3">
                                <strong class="text-foreground">3. Rate</strong> yourself: Again, Hard, Good or Easy (keys 1–4).
                            </li>
                            <li class="rounded-xl bg-muted/50 p-3">
                                <strong class="text-foreground">4. Come back</strong> daily: due facts return on time; the AI Tutor explains anything
                                unclear.
                            </li>
                        </ol>
                    </div>
                    <div class="grid content-start gap-3 sm:grid-cols-2">
                        <div class="rounded-xl border bg-background/60 p-4 sm:col-span-2">
                            <p class="text-sm text-muted-foreground">
                                You have been tested on <strong class="text-foreground">{{ tested }}%</strong> ({{ totals.top_seen }}/{{
                                    totals.top_total
                                }}) of the A++ to A facts.
                            </p>
                            <div class="mt-2 h-2 overflow-hidden rounded-full bg-muted" aria-hidden="true">
                                <div class="brand-rainbow h-full rounded-full transition-all" :style="{ width: tested + '%' }" />
                            </div>
                            <p class="mt-3 text-sm text-muted-foreground">
                                Acceptable recall (remembered twice in a row) in <strong class="text-foreground">{{ recall }}%</strong> of them.
                            </p>
                            <div class="mt-2 h-2 overflow-hidden rounded-full bg-muted" aria-hidden="true">
                                <div class="h-full rounded-full bg-emerald-500 transition-all" :style="{ width: recall + '%' }" />
                            </div>
                        </div>
                        <div class="flex items-center gap-3 rounded-xl border bg-background/60 p-3">
                            <CalendarClock class="size-5 text-primary" aria-hidden="true" />
                            <div>
                                <p class="text-lg font-semibold leading-none">{{ totals.due }}</p>
                                <p class="text-xs text-muted-foreground">due for review</p>
                            </div>
                        </div>
                        <div class="flex items-center gap-3 rounded-xl border bg-background/60 p-3">
                            <Flame class="size-5 text-orange-500" aria-hidden="true" />
                            <div>
                                <p class="text-lg font-semibold leading-none">{{ totals.today }}</p>
                                <p class="text-xs text-muted-foreground">answered today</p>
                            </div>
                        </div>
                        <Link
                            href="/tutor/facts?status=weak"
                            class="flex items-center gap-3 rounded-xl border bg-background/60 p-3 transition hover:border-primary/50"
                        >
                            <Target class="size-5 text-rose-500" aria-hidden="true" />
                            <div>
                                <p class="text-lg font-semibold leading-none">{{ totals.weak }}</p>
                                <p class="text-xs text-muted-foreground">weak facts</p>
                            </div>
                        </Link>
                        <Link
                            href="/tutor/facts?status=mastered"
                            class="flex items-center gap-3 rounded-xl border bg-background/60 p-3 transition hover:border-primary/50"
                        >
                            <Trophy class="text-amber-500 size-5" aria-hidden="true" />
                            <div>
                                <p class="text-lg font-semibold leading-none">{{ totals.mastered }}</p>
                                <p class="text-xs text-muted-foreground">mastered</p>
                            </div>
                        </Link>
                    </div>
                </div>
            </section>

            <div v-if="totals.total === 0" class="rounded-2xl border border-dashed p-10 text-center">
                <BookOpenCheck class="mx-auto size-10 text-primary" aria-hidden="true" />
                <h2 class="mt-3 text-lg font-semibold">Facts are on their way</h2>
                <p class="mx-auto mt-1 max-w-md text-sm text-muted-foreground">
                    The MRCPWorld team is adding the facts for {{ exam.name }}. Meanwhile the AI Tutor can teach you any topic.
                </p>
                <Link href="/tutor/ai" class="mt-4 inline-flex items-center gap-1 text-sm font-semibold text-primary hover:underline"
                    >Open the AI Tutor <ArrowRight class="size-4" aria-hidden="true"
                /></Link>
            </div>

            <template v-else>
                <!-- Start bar -->
                <section class="sticky top-2 z-10 flex flex-wrap items-center gap-4 rounded-2xl border bg-card/95 p-4 shadow-sm backdrop-blur">
                    <Button size="lg" :disabled="starting || count.ready === 0" @click="start">
                        <Play class="size-4" aria-hidden="true" />Start the Knowledge Tutor
                    </Button>
                    <p class="text-sm" aria-live="polite">
                        <strong class="text-base">{{ count.total.toLocaleString() }}</strong> {{ count.total === 1 ? 'fact' : 'facts' }} found
                        <span class="text-muted-foreground"> · {{ count.ready.toLocaleString() }} ready now</span>
                        <span v-if="counting" class="ml-1 text-xs text-muted-foreground">(updating…)</span>
                    </p>
                    <p v-if="count.total > 0 && count.ready === 0" class="text-sm text-muted-foreground">
                        Nothing to ask with these filters right now: choose “Spaced repetition” or more subjects.
                    </p>
                </section>

                <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_22rem]">
                    <div class="min-w-0 space-y-6">
                        <!-- Subjects -->
                        <section class="overflow-hidden rounded-2xl border bg-card shadow-sm" aria-labelledby="subjects-title">
                            <header class="flex items-center justify-between border-b px-5 py-3">
                                <h2 id="subjects-title" class="text-sm font-semibold uppercase tracking-wider">Subjects</h2>
                                <span class="text-xs text-muted-foreground">Bar = facts you recall</span>
                            </header>
                            <ul class="divide-y">
                                <li>
                                    <label class="flex cursor-pointer items-center gap-3 px-5 py-3 hover:bg-muted/40">
                                        <input type="checkbox" class="size-4 accent-primary" :checked="allSubjects" @change="toggleAll" />
                                        <span class="flex-1 font-medium">All subjects</span>
                                        <span class="text-xs text-muted-foreground">{{ totals.total }} facts</span>
                                    </label>
                                </li>
                                <li v-for="subject in overview.subjects" :key="subject.id">
                                    <label class="flex cursor-pointer items-center gap-3 px-5 py-3 hover:bg-muted/40">
                                        <input
                                            type="checkbox"
                                            class="size-4 accent-primary"
                                            :checked="form.categories.includes(subject.id)"
                                            @change="toggleSubject(subject.id)"
                                        />
                                        <span class="min-w-0 flex-1">
                                            <span class="block truncate">{{ subject.name }}</span>
                                            <span class="mt-1 block h-1.5 max-w-xs overflow-hidden rounded-full bg-muted" aria-hidden="true">
                                                <span
                                                    class="block h-full rounded-full bg-emerald-500"
                                                    :style="{ width: percent(subject.recalled, subject.total) + '%' }"
                                                />
                                            </span>
                                        </span>
                                        <span class="hidden text-right text-xs text-muted-foreground sm:block">
                                            {{ subject.recalled }}/{{ subject.total }} recalled
                                            <span v-if="subject.due" class="ml-2 font-semibold text-primary">{{ subject.due }} due</span>
                                            <span v-if="subject.weak" class="ml-2 font-semibold text-rose-500">{{ subject.weak }} weak</span>
                                        </span>
                                    </label>
                                </li>
                            </ul>
                        </section>

                        <div class="grid gap-6 md:grid-cols-2">
                            <!-- High-yield -->
                            <section class="rounded-2xl border bg-card p-5 shadow-sm">
                                <label for="tutor-rating" class="text-sm font-semibold uppercase tracking-wider">High-yield rating</label>
                                <select
                                    id="tutor-rating"
                                    v-model="form.rating"
                                    class="mt-3 w-full rounded-lg border bg-background px-3 py-2 text-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                                >
                                    <option v-for="(label, key) in ratings" :key="key" :value="key">{{ label }}</option>
                                </select>
                                <p class="mt-2 text-xs text-muted-foreground">{{ RATING_HELP }}</p>
                            </section>

                            <!-- Difficulty -->
                            <section class="rounded-2xl border bg-card p-5 shadow-sm">
                                <p class="text-sm font-semibold uppercase tracking-wider" id="difficulty-title">Difficulty</p>
                                <p class="mt-3 text-sm font-medium">{{ difficultyLabel }}</p>
                                <div class="mt-2 space-y-2" role="group" aria-labelledby="difficulty-title">
                                    <label class="flex items-center gap-3 text-xs text-muted-foreground">
                                        <span class="w-10">Easiest</span>
                                        <input
                                            type="range"
                                            min="0"
                                            max="100"
                                            step="5"
                                            :value="form.difficulty[0]"
                                            class="flex-1 accent-primary"
                                            aria-label="Lowest difficulty"
                                            @input="setLow"
                                        />
                                        <span class="w-9 text-right">{{ form.difficulty[0] }}%</span>
                                    </label>
                                    <label class="flex items-center gap-3 text-xs text-muted-foreground">
                                        <span class="w-10">Hardest</span>
                                        <input
                                            type="range"
                                            min="0"
                                            max="100"
                                            step="5"
                                            :value="form.difficulty[1]"
                                            class="flex-1 accent-primary"
                                            aria-label="Highest difficulty"
                                            @input="setHigh"
                                        />
                                        <span class="w-9 text-right">{{ form.difficulty[1] }}%</span>
                                    </label>
                                </div>
                                <p class="mt-2 text-xs text-muted-foreground">
                                    How often students forget a fact. New facts without enough answers count as 50%.
                                </p>
                            </section>
                        </div>

                        <!-- Fact selection -->
                        <section class="rounded-2xl border bg-card p-5 shadow-sm">
                            <p class="text-sm font-semibold uppercase tracking-wider" id="mode-title">Fact selection</p>
                            <div class="mt-3 grid gap-3 sm:grid-cols-2" role="radiogroup" aria-labelledby="mode-title">
                                <label
                                    v-for="(label, key) in modes"
                                    :key="key"
                                    class="flex cursor-pointer items-start gap-3 rounded-xl border p-3 transition hover:border-primary/50"
                                    :class="form.mode === key ? 'border-primary bg-primary/5 ring-1 ring-primary/40' : ''"
                                >
                                    <input v-model="form.mode" type="radio" name="mode" :value="key" class="mt-1 accent-primary" />
                                    <component :is="MODE_ICONS[key] ?? Brain" class="mt-0.5 size-4 shrink-0 text-primary" aria-hidden="true" />
                                    <span class="text-sm">{{ label }}</span>
                                </label>
                            </div>
                        </section>
                    </div>

                    <!-- Last activity -->
                    <aside class="space-y-6 lg:sticky lg:top-24 lg:self-start">
                        <section class="overflow-hidden rounded-2xl border bg-card shadow-sm">
                            <header class="flex items-center gap-2 border-b px-5 py-3">
                                <History class="size-4 text-primary" aria-hidden="true" />
                                <h2 class="text-sm font-semibold uppercase tracking-wider">Last activity</h2>
                            </header>
                            <ul v-if="runs.length" class="divide-y">
                                <li v-for="run in runs" :key="run.id" class="flex items-center gap-3 px-5 py-3">
                                    <div class="min-w-0 flex-1">
                                        <p class="truncate text-sm font-medium" :title="run.label">{{ run.label }}</p>
                                        <p class="text-xs text-muted-foreground">{{ run.answered }} answered · {{ run.updated }}</p>
                                    </div>
                                    <Button as-child size="sm" variant="outline">
                                        <Link :href="'/tutor/study/' + run.id">Continue <ArrowRight class="size-3.5" aria-hidden="true" /></Link>
                                    </Button>
                                </li>
                            </ul>
                            <p v-else class="px-5 py-6 text-sm text-muted-foreground">Your sessions will appear here so you can carry on.</p>
                        </section>
                        <section class="rounded-2xl border bg-card p-5 shadow-sm">
                            <h2 class="text-sm font-semibold uppercase tracking-wider">More</h2>
                            <div class="mt-3 grid gap-2 text-sm">
                                <Link href="/tutor/facts" class="flex items-center justify-between rounded-lg px-3 py-2 hover:bg-muted"
                                    >Review the facts you answered <ArrowRight class="size-4 text-primary" aria-hidden="true"
                                /></Link>
                                <Link href="/tutor/ai" class="flex items-center justify-between rounded-lg px-3 py-2 hover:bg-muted"
                                    >Ask the AI Tutor <ArrowRight class="size-4 text-primary" aria-hidden="true"
                                /></Link>
                            </div>
                        </section>
                    </aside>
                </div>
            </template>
        </div>
    </AppLayout>
</template>
