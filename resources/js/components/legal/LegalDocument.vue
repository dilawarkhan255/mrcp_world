<script setup lang="ts">
import PublicLayout from '@/layouts/PublicLayout.vue';
import { Head } from '@inertiajs/vue3';

/** A legal text (Terms of use, Privacy notice) from config, with its sections and the date of the latest version. */
defineProps<{
    appName: string;
    title: string;
    description: string;
    summary: string;
    sections: { title: string; body: string }[];
    updated: string;
}>();
</script>
<template>
    <Head :title="title">
        <meta name="description" :content="description" />
    </Head>
    <PublicLayout :app-name="appName">
        <article class="mx-auto w-full max-w-3xl px-5 py-16 md:px-8 md:py-24">
            <h1 class="text-3xl font-bold tracking-tight text-ink sm:text-4xl">{{ title }}</h1>
            <p class="mt-2 text-sm text-muted-soft">Last updated: {{ updated }}</p>
            <p class="mt-6 text-base leading-relaxed text-muted-strong">{{ summary }}</p>
            <nav aria-label="Contents" class="mt-8 rounded-card border bg-white p-5">
                <p class="text-sm font-semibold text-ink">Contents</p>
                <ol class="mt-3 grid gap-1.5 text-sm sm:grid-cols-2">
                    <li v-for="(section, index) in sections" :key="section.title">
                        <a :href="'#section-' + (index + 1)" class="text-muted-strong hover:text-[#d9620b]">{{ index + 1 }}. {{ section.title }}</a>
                    </li>
                </ol>
            </nav>
            <div class="mt-10 space-y-8">
                <section v-for="(section, index) in sections" :id="'section-' + (index + 1)" :key="section.title" class="scroll-mt-24">
                    <h2 class="font-heading text-lg font-semibold text-ink">{{ index + 1 }}. {{ section.title }}</h2>
                    <p class="mt-2 text-base leading-relaxed text-muted-strong">{{ section.body }}</p>
                </section>
            </div>
        </article>
    </PublicLayout>
</template>
