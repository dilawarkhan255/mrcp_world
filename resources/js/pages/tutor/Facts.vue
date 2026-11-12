<script setup lang="ts">
import RichText from '@/components/practice/RichText.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { ChevronLeft, ChevronRight, Eye, EyeOff, History, RotateCcw, Search } from 'lucide-vue-next';
import { reactive, ref } from 'vue';

/** Review facts: every fact the student has answered, with its status, to read again or reset. */
interface Fact {
    id: number;
    front_html: string;
    back_html: string;
    subject: string;
    high_yield: string;
    status: string;
    answers: number;
    last_rating: string | null;
    due: string | null;
}
const props = defineProps<{
    exam: { id: number; name: string };
    filters: { status: string | null; subject: number | null; search: string | null };
    subjects: { id: number; name: string }[];
    facts: { data: Fact[]; current_page: number; last_page: number; total: number; prev: string | null; next: string | null };
}>();

const TABS = [
    { key: null, label: 'All' },
    { key: 'due', label: 'Due now' },
    { key: 'weak', label: 'Weak' },
    { key: 'learning', label: 'Learning' },
    { key: 'recalled', label: 'Recalled' },
    { key: 'mastered', label: 'Mastered' },
] as const;
const STATUS: Record<string, { label: string; classes: string }> = {
    learning: { label: 'Learning', classes: 'bg-amber-500/10 text-amber-700 dark:text-amber-300' },
    weak: { label: 'Weak', classes: 'bg-rose-500/10 text-rose-700 dark:text-rose-300' },
    recalled: { label: 'Recalled', classes: 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-300' },
    mastered: { label: 'Mastered', classes: 'bg-violet-500/10 text-violet-700 dark:text-violet-300' },
};
const RATING: Record<string, string> = { again: 'Forgot', hard: 'Hard', good: 'Good', easy: 'Easy' };

const form = reactive({ status: props.filters.status, subject: props.filters.subject, search: props.filters.search ?? '' });
const open = ref<Record<number, boolean>>({});

function apply(changes: Partial<typeof form> = {}) {
    Object.assign(form, changes);
    const query: Record<string, string> = {};
    if (form.status) query.status = form.status;
    if (form.subject !== null) query.subject = String(form.subject);
    if (form.search.trim()) query.search = form.search.trim();
    router.get('/tutor/facts', query, { preserveState: true, preserveScroll: true, replace: true });
}
function reset(fact: Fact) {
    if (!window.confirm('Ask this fact again as a new one? Its progress is cleared.')) return;
    router.delete('/tutor/facts/' + fact.id, { preserveScroll: true });
}
</script>

<template>
    <Head title="Review facts" />
    <AppLayout
        :breadcrumbs="[
            { title: 'Knowledge Tutor', href: '/tutor' },
            { title: 'Review facts', href: '/tutor/facts' },
        ]"
    >
        <div class="mx-auto w-full max-w-5xl space-y-6 p-4 md:p-8">
            <div>
                <p class="text-sm font-semibold text-primary">{{ exam.name }}</p>
                <h1 class="mt-1 flex items-center gap-2 text-3xl font-semibold">
                    <History class="size-7 text-primary" aria-hidden="true" />Review facts
                </h1>
                <p class="mt-2 max-w-2xl text-sm text-muted-foreground">
                    Every fact you have answered, newest first. Read the weak ones again, or reset a fact to have it asked as new.
                </p>
            </div>

            <div class="space-y-3 rounded-2xl border bg-card p-4 shadow-sm">
                <div class="flex flex-wrap gap-2" role="tablist" aria-label="Fact status">
                    <button
                        v-for="tab in TABS"
                        :key="tab.label"
                        type="button"
                        role="tab"
                        :aria-selected="form.status === tab.key"
                        class="rounded-full border px-3 py-1.5 text-sm font-medium transition"
                        :class="form.status === tab.key ? 'border-primary bg-primary text-primary-foreground' : 'hover:border-primary/50'"
                        @click="apply({ status: tab.key })"
                    >
                        {{ tab.label }}
                    </button>
                </div>
                <div class="grid gap-3 sm:grid-cols-[14rem_minmax(0,1fr)]">
                    <label class="sr-only" for="facts-subject">Subject</label>
                    <select
                        id="facts-subject"
                        :value="form.subject ?? ''"
                        class="rounded-lg border bg-background px-3 py-2 text-sm"
                        @change="
                            apply({
                                subject:
                                    ($event.target as HTMLSelectElement).value === '' ? null : Number(($event.target as HTMLSelectElement).value),
                            })
                        "
                    >
                        <option value="">All subjects</option>
                        <option v-for="subject in subjects" :key="subject.id" :value="subject.id">{{ subject.name }}</option>
                    </select>
                    <form class="relative" role="search" @submit.prevent="apply()">
                        <label class="sr-only" for="facts-search">Search the facts</label>
                        <Search class="absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" aria-hidden="true" />
                        <input
                            id="facts-search"
                            v-model="form.search"
                            type="search"
                            maxlength="100"
                            placeholder="Search the facts you answered…"
                            class="w-full rounded-lg border bg-background py-2 pl-9 pr-3 text-sm"
                        />
                    </form>
                </div>
            </div>

            <p class="text-sm text-muted-foreground">{{ facts.total }} facts</p>

            <div v-if="!facts.data.length" class="rounded-2xl border border-dashed p-10 text-center text-sm text-muted-foreground">
                No facts here yet.
                <Link href="/tutor" class="font-semibold text-primary hover:underline">Start the Knowledge Tutor</Link>
            </div>

            <ul v-else class="space-y-3">
                <li v-for="fact in facts.data" :key="fact.id" class="overflow-hidden rounded-2xl border bg-card shadow-sm">
                    <div class="flex flex-wrap items-center gap-2 border-b px-5 py-2.5 text-xs">
                        <span class="rounded-full bg-muted px-2.5 py-1 font-semibold">{{ fact.subject }}</span>
                        <span class="rounded-full bg-primary/10 px-2.5 py-1 font-semibold text-primary">{{ fact.high_yield }}</span>
                        <span class="rounded-full px-2.5 py-1 font-semibold" :class="STATUS[fact.status]?.classes">{{
                            STATUS[fact.status]?.label
                        }}</span>
                        <span class="ml-auto text-muted-foreground">
                            Answered {{ fact.answers }}×<template v-if="fact.last_rating"> · last: {{ RATING[fact.last_rating] }}</template
                            ><template v-if="fact.due"> · next review {{ fact.due }}</template>
                        </span>
                    </div>
                    <div class="p-5">
                        <RichText :html="fact.front_html" class="text-sm" />
                        <div v-if="open[fact.id]" class="mt-4 rounded-xl bg-muted/40 p-4">
                            <RichText :html="fact.back_html" class="text-sm" />
                        </div>
                        <div class="mt-4 flex flex-wrap gap-4 text-sm font-semibold">
                            <button
                                type="button"
                                class="inline-flex items-center gap-1 text-primary hover:underline"
                                @click="open[fact.id] = !open[fact.id]"
                            >
                                <component :is="open[fact.id] ? EyeOff : Eye" class="size-4" aria-hidden="true" />{{
                                    open[fact.id] ? 'Hide the fact' : 'Show the fact'
                                }}
                            </button>
                            <button
                                type="button"
                                class="inline-flex items-center gap-1 text-muted-foreground hover:text-foreground hover:underline"
                                @click="reset(fact)"
                            >
                                <RotateCcw class="size-4" aria-hidden="true" />Ask again as new
                            </button>
                        </div>
                    </div>
                </li>
            </ul>

            <nav v-if="facts.last_page > 1" class="flex items-center justify-between text-sm" aria-label="Pages">
                <Link
                    v-if="facts.prev"
                    :href="facts.prev"
                    preserve-scroll
                    class="inline-flex items-center gap-1 rounded-lg border px-3 py-1.5 font-medium hover:border-primary/50"
                    ><ChevronLeft class="size-4" aria-hidden="true" />Previous</Link
                >
                <span v-else />
                <span class="text-muted-foreground">Page {{ facts.current_page }} of {{ facts.last_page }}</span>
                <Link
                    v-if="facts.next"
                    :href="facts.next"
                    preserve-scroll
                    class="inline-flex items-center gap-1 rounded-lg border px-3 py-1.5 font-medium hover:border-primary/50"
                    >Next<ChevronRight class="size-4" aria-hidden="true"
                /></Link>
                <span v-else />
            </nav>
        </div>
    </AppLayout>
</template>
