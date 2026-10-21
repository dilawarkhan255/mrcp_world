<script setup lang="ts">
import PublicLayout from '@/layouts/PublicLayout.vue';
import { Head } from '@inertiajs/vue3';
defineProps<{
    appName: string;
    summary: string;
    sections: { title: string; body: string }[];
}>();
</script>
<template>
    <Head title="Privacy notice">
        <meta name="description" :content="`How ${appName} stores and uses your data.`" />
    </Head>
    <PublicLayout :app-name="appName">
        <div class="mx-auto w-full max-w-3xl px-5 py-16 md:px-8 md:py-24">
            <h1 class="text-3xl font-bold tracking-tight text-ink sm:text-4xl">Privacy notice</h1>
            <p class="mt-4 text-base leading-relaxed text-muted-strong">{{ summary }}</p>
            <div class="mt-10 space-y-8">
                <section v-for="section in sections" :key="section.title">
                    <h2 class="font-heading text-lg font-semibold text-ink">{{ section.title }}</h2>
                    <p class="mt-2 text-base leading-relaxed text-muted-strong">{{ section.body }}</p>
                </section>
            </div>
        </div>
    </PublicLayout>
</template>
