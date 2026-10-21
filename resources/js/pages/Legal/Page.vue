<script setup lang="ts">
import PublicLayout from '@/layouts/PublicLayout.vue';
import { Head } from '@inertiajs/vue3';
defineProps<{ appName: string; title: string; intro: string }>();
</script>
<template>
    <Head :title="title" />
    <PublicLayout :app-name="appName">
        <div class="mx-auto w-full max-w-3xl px-5 py-16 md:px-8 md:py-24">
            <h1 class="text-3xl font-bold tracking-tight text-ink sm:text-4xl">{{ title }}</h1>
            <p class="mt-4 text-base leading-relaxed text-muted-strong">{{ intro }}</p>
            <div class="mt-10 rounded-card border border-dashed bg-white p-10 text-center text-sm text-muted-soft">
                This page is being written. It will be reviewed before the site goes live.
            </div>
        </div>
    </PublicLayout>
</template>
