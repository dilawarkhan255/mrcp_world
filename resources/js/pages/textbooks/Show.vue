<script setup lang="ts">
import RichText from '@/components/practice/RichText.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import { ChevronLeft, ChevronRight, Lightbulb } from 'lucide-vue-next';

defineProps<{
    exam: { id: number; name: string };
    chapter: {
        title: string;
        summary: string | null;
        subject: string | null;
        /** Server-rendered Markdown with raw HTML stripped. */
        body_html: string | null;
        key_points_html: string | null;
        updated_at: string | null;
    };
    previous: { title: string; slug: string } | null;
    next: { title: string; slug: string } | null;
}>();
</script>
<template>
    <Head :title="chapter.title" />
    <AppLayout
        :breadcrumbs="[
            { title: 'Textbook', href: '/textbooks' },
            { title: chapter.title, href: '#' },
        ]"
    >
        <article class="mx-auto w-full max-w-3xl space-y-6 p-6 md:p-10">
            <header>
                <p class="text-sm font-semibold text-primary">{{ chapter.subject ?? exam.name }}</p>
                <h1 class="mt-2 text-3xl font-semibold">{{ chapter.title }}</h1>
                <p v-if="chapter.summary" class="mt-3 text-muted-foreground">{{ chapter.summary }}</p>
            </header>
            <RichText :html="chapter.body_html" class="text-base" />
            <aside v-if="chapter.key_points_html" class="rounded-xl border-l-4 border-primary bg-secondary/60 p-5">
                <h2 class="mb-2 flex items-center gap-2 font-semibold"><Lightbulb class="size-4 text-primary" aria-hidden="true" />Key points</h2>
                <RichText :html="chapter.key_points_html" class="text-sm" />
            </aside>
            <nav class="flex items-center justify-between gap-3 border-t pt-5 text-sm" aria-label="Chapters">
                <Link v-if="previous" :href="'/textbooks/' + previous.slug" class="inline-flex items-center gap-1 text-primary"
                    ><ChevronLeft class="size-4" aria-hidden="true" />{{ previous.title }}</Link
                ><span v-else />
                <Link v-if="next" :href="'/textbooks/' + next.slug" class="inline-flex items-center gap-1 text-primary"
                    >{{ next.title }}<ChevronRight class="size-4" aria-hidden="true"
                /></Link>
            </nav>
        </article>
    </AppLayout>
</template>
