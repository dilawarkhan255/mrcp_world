<script setup lang="ts">
import AdminDelete from '@/components/AdminDelete.vue';
import AiQuickAsk from '@/components/practice/AiQuickAsk.vue';
import RichText from '@/components/practice/RichText.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import type { SharedData } from '@/types';
import { Head, Link, usePage } from '@inertiajs/vue3';
import { ChevronLeft, ChevronRight, Lightbulb } from 'lucide-vue-next';
import { computed } from 'vue';

defineProps<{
    exam: { id: number; name: string };
    chapter: {
        title: string;
        slug: string;
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

const aiEnabled = computed(() => !!usePage<SharedData>().props.ai?.enabled);
</script>
<template>
    <Head :title="chapter.title" />
    <AppLayout
        :breadcrumbs="[
            { title: 'Textbook', href: '/textbooks' },
            { title: chapter.title, href: '#' },
        ]"
    >
        <div class="mx-auto grid w-full max-w-6xl gap-8 p-6 md:p-10 lg:grid-cols-[minmax(0,1fr)_20rem]">
            <article class="min-w-0 space-y-6">
                <header>
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <p class="text-sm font-semibold text-primary">{{ chapter.subject ?? exam.name }}</p>
                        <AdminDelete
                            :url="'/staff/textbooks/' + chapter.slug"
                            label="Delete chapter"
                            confirm-text="Delete this chapter for good? This cannot be undone."
                        />
                    </div>
                    <h1 class="mt-2 text-3xl font-semibold">{{ chapter.title }}</h1>
                    <p v-if="chapter.summary" class="mt-3 text-muted-foreground">{{ chapter.summary }}</p>
                    <span class="mw-logo-line mt-5 block h-1 w-24 rounded-full" aria-hidden="true" />
                </header>
                <RichText :html="chapter.body_html" class="text-base" />
                <aside v-if="chapter.key_points_html" class="mw-logo-left mw-logo-soft rounded-xl p-5">
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
            <aside v-if="aiEnabled" class="lg:sticky lg:top-24 lg:self-start">
                <AiQuickAsk
                    title="Ask AI about this chapter"
                    :url="'/textbooks/' + chapter.slug + '/ask'"
                    :suggestions="['Summarise this chapter', 'What do examiners test here?', 'Give me a memory aid']"
                    :reset-key="chapter.slug"
                />
            </aside>
        </div>
    </AppLayout>
</template>
