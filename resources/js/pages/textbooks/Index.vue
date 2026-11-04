<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import { BookOpen, ChevronRight } from 'lucide-vue-next';

defineProps<{
    exam: { id: number; name: string };
    subjects: { name: string; chapters: { id: number; title: string; slug: string; summary: string | null }[] }[];
}>();
</script>
<template>
    <Head title="Textbook" />
    <AppLayout :breadcrumbs="[{ title: 'Textbook', href: '/textbooks' }]">
        <div class="mx-auto w-full max-w-5xl space-y-8 p-6 md:p-10">
            <div>
                <p class="text-sm font-semibold text-primary">{{ exam.name }}</p>
                <h1 class="mt-2 text-3xl font-semibold">Extended textbook</h1>
                <p class="mt-2 text-sm text-muted-foreground">Chapters by subject, with tables, images and key points.</p>
            </div>
            <div v-if="!subjects.length" class="flex flex-col items-center gap-3 rounded-xl border border-dashed p-12 text-center">
                <BookOpen class="size-8 text-muted-foreground" aria-hidden="true" />
                <p class="text-sm text-muted-foreground">No chapters have been published for this exam yet.</p>
            </div>
            <section v-for="subject in subjects" :key="subject.name" class="space-y-3">
                <h2 class="text-lg font-semibold">{{ subject.name }}</h2>
                <ul class="grid gap-3 sm:grid-cols-2">
                    <li v-for="chapter in subject.chapters" :key="chapter.id">
                        <Link
                            :href="'/textbooks/' + chapter.slug"
                            class="group flex h-full items-start justify-between gap-3 rounded-xl border bg-card p-4 transition hover:border-primary/60 hover:shadow-sm"
                        >
                            <span>
                                <span class="block font-medium">{{ chapter.title }}</span>
                                <span v-if="chapter.summary" class="mt-1 line-clamp-2 block text-sm text-muted-foreground">{{
                                    chapter.summary
                                }}</span>
                            </span>
                            <ChevronRight class="mt-0.5 size-4 shrink-0 text-muted-foreground group-hover:text-primary" aria-hidden="true" />
                        </Link>
                    </li>
                </ul>
            </section>
        </div>
    </AppLayout>
</template>
