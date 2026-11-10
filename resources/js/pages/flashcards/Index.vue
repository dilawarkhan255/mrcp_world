<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import { Layers, Play } from 'lucide-vue-next';
import { computed } from 'vue';

interface Deck {
    category_id: number | null;
    subject: string;
    slug: string;
    total: number;
    new: number;
    due: number;
}
const props = defineProps<{ exam: { id: number; name: string }; decks: Deck[] }>();

const totals = computed(() =>
    props.decks.reduce((sum, deck) => ({ due: sum.due + deck.due, new: sum.new + deck.new, total: sum.total + deck.total }), {
        due: 0,
        new: 0,
        total: 0,
    }),
);
</script>

<template>
    <Head title="Flashcards" />
    <AppLayout :breadcrumbs="[{ title: 'Flashcards', href: '/flashcards' }]">
        <div class="mx-auto w-full max-w-5xl space-y-8 p-6 md:p-10">
            <div class="flex flex-wrap items-end justify-between gap-4">
                <div>
                    <p class="text-sm font-semibold text-primary">{{ exam.name }}</p>
                    <h1 class="mt-2 text-3xl font-semibold">Flashcards</h1>
                    <p class="mt-2 max-w-2xl text-sm text-muted-foreground">
                        Quick recall cards by subject. Rate each card and it comes back just before you would forget it: hard cards soon, easy ones
                        later.
                    </p>
                </div>
                <Link
                    v-if="totals.due + totals.new > 0"
                    href="/flashcards/study"
                    class="inline-flex items-center gap-2 rounded-lg bg-primary px-4 py-2.5 text-sm font-semibold text-primary-foreground shadow-sm hover:opacity-90"
                    ><Play class="size-4" aria-hidden="true" />Study all ({{ totals.due }} due · {{ totals.new }} new)</Link
                >
            </div>

            <div v-if="!decks.length" class="flex flex-col items-center gap-3 rounded-xl border border-dashed p-12 text-center">
                <Layers class="size-8 text-muted-foreground" aria-hidden="true" />
                <p class="text-sm text-muted-foreground">No flashcards have been published for this exam yet.</p>
            </div>

            <ul v-else class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <li v-for="deck in decks" :key="deck.subject">
                    <Link
                        :href="'/flashcards/study?subject=' + deck.slug"
                        class="group relative flex h-full flex-col overflow-hidden rounded-xl border bg-card p-5 shadow-sm transition hover:-translate-y-0.5 hover:shadow-md"
                    >
                        <span class="mw-logo-line absolute inset-x-0 top-0 h-1" aria-hidden="true" />
                        <span class="flex items-center gap-2">
                            <span class="flex size-9 items-center justify-center rounded-lg bg-primary/10 text-primary"
                                ><Layers class="size-5" aria-hidden="true"
                            /></span>
                            <span class="font-semibold group-hover:text-primary">{{ deck.subject }}</span>
                        </span>
                        <span class="mt-4 grid grid-cols-3 gap-2 text-center text-xs">
                            <span class="rounded-lg bg-muted/60 p-2"
                                ><strong class="block text-lg tabular-nums" :class="deck.due ? 'text-[#e2761b]' : ''">{{ deck.due }}</strong
                                >Due</span
                            >
                            <span class="rounded-lg bg-muted/60 p-2"
                                ><strong class="block text-lg tabular-nums text-[#16a34a]">{{ deck.new }}</strong
                                >New</span
                            >
                            <span class="rounded-lg bg-muted/60 p-2"
                                ><strong class="block text-lg tabular-nums">{{ deck.total }}</strong
                                >Cards</span
                            >
                        </span>
                    </Link>
                </li>
            </ul>
        </div>
    </AppLayout>
</template>
