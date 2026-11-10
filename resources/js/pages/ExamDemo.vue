<script setup lang="ts">
import RichText from '@/components/practice/RichText.vue';
import { landingCopy, landingSeo } from '@/content/landing';
import PublicLayout from '@/layouts/PublicLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
defineProps<{
    appName: string;
    exam: { name: string; slug: string; description: string | null };
    questions: {
        id: number;
        body: string;
        body_html?: string | null;
        options: Record<string, string>;
        correct_option: string;
        explanation: string | null;
        explanation_html?: string | null;
    }[];
}>();
</script>
<template>
    <Head :title="`${landingCopy.demo.title} · ${exam.name}`">
        <meta name="description" :content="landingSeo.description" />
        <meta property="og:title" :content="landingSeo.ogTitle" />
        <meta property="og:description" :content="landingSeo.ogDescription" />
        <meta property="og:type" :content="landingSeo.ogType" />
    </Head>
    <PublicLayout :app-name="appName">
        <div class="mx-auto w-full max-w-3xl px-5 py-16 md:px-8 md:py-20">
            <p class="text-sm font-semibold uppercase tracking-widest text-teal">{{ exam.name }}</p>
            <h1 class="mt-3 text-3xl font-bold tracking-tight text-ink sm:text-4xl">{{ landingCopy.demo.title }}</h1>
            <p class="mt-4 text-base leading-relaxed text-muted-strong">{{ landingCopy.demo.intro }}</p>
            <p v-if="!questions.length" class="mt-10 rounded-card border border-dashed bg-white p-10 text-center text-sm text-muted-soft">
                {{ landingCopy.demo.empty }}
            </p>
            <ol v-else class="mt-10 space-y-6">
                <li v-for="(question, index) in questions" :key="question.id" class="rounded-card border bg-white p-6 shadow-sm">
                    <p class="text-xs font-semibold uppercase tracking-wide text-muted-soft">Question {{ index + 1 }}</p>
                    <RichText :html="question.body_html" :text="question.body" class="mt-2 text-base text-ink" />
                    <ul class="mt-4 space-y-2">
                        <li
                            v-for="(text, key) in question.options"
                            :key="key"
                            class="rounded-control border px-4 py-3 text-sm"
                            :class="key === question.correct_option ? 'border-teal bg-light-teal text-dark-teal' : 'border-border text-muted-strong'"
                        >
                            <span class="font-semibold">{{ key }}.</span> {{ text }}
                        </li>
                    </ul>
                    <RichText
                        v-if="question.explanation"
                        :html="question.explanation_html"
                        :text="question.explanation"
                        class="mt-4 border-l-2 border-teal pl-3 text-sm text-muted-strong"
                    />
                </li>
            </ol>
            <div class="mt-12 flex flex-wrap gap-3">
                <Link
                    :href="'/register?exam=' + exam.slug"
                    class="inline-flex min-h-[44px] items-center justify-center rounded-control bg-primary px-6 text-sm font-semibold text-primary-foreground transition focus-visible:ring-2 focus-visible:ring-teal focus-visible:ring-offset-2"
                    >{{ landingCopy.demo.signUp }}</Link
                >
                <Link
                    href="/"
                    class="inline-flex min-h-[44px] items-center justify-center rounded-control border border-border bg-white px-6 text-sm font-semibold text-ink transition hover:border-teal focus-visible:ring-2 focus-visible:ring-teal focus-visible:ring-offset-2"
                    >{{ landingCopy.demo.back }}</Link
                >
            </div>
        </div>
    </PublicLayout>
</template>
