<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import ThemeToggle from '@/components/ThemeToggle.vue';
import { APP_NAME } from '@/lib/brand';
</script>
<template>
    <Head title="Welcome" />
    <main class="mx-auto flex min-h-screen max-w-6xl flex-col px-6 py-8">
        <header class="flex items-center gap-3"><AppLogoIcon class="size-9 text-primary" /><span class="font-heading text-xl font-semibold">{{ APP_NAME }}</span><ThemeToggle class="ml-auto" /></header>
        <section class="my-auto max-w-2xl py-20">
            <p class="mb-5 text-sm font-semibold uppercase tracking-widest text-primary">Your revision, organised</p>
            <h1 class="text-4xl font-semibold leading-tight md:text-6xl">A clear space to prepare for your next exam.</h1>
            <p class="mt-6 text-lg text-muted-foreground">Question practice, textbooks and revision tools, together in one place.</p>
            <div class="mt-9 flex flex-wrap gap-3"><Link href="/register" class="rounded-lg bg-primary px-6 py-3 font-semibold text-primary-foreground">Create account</Link><Link href="/login" class="rounded-lg border bg-card px-6 py-3 font-semibold">Log in</Link></div>
            <p class="mt-6 text-xs text-muted-foreground">Foundation preview · Learning content is coming soon.</p>
        </section>
    </main>
</template>
