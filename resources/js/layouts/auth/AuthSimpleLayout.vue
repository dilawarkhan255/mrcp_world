<script setup lang="ts">
import { APP_NAME } from '@/lib/brand';
import { Link } from '@inertiajs/vue3';
import { BookOpen, CheckCircle2, ClipboardList, GraduationCap, ShieldCheck, Sparkles } from 'lucide-vue-next';

defineProps<{
    title?: string;
    description?: string;
    /** A wider form card (the sign-up page has two columns); the brand panel gets narrower. */
    wide?: boolean;
}>();

/** What a subscription gives, shown next to every auth form (true to what the app does). */
const features = [
    { icon: ClipboardList, text: 'Exam-style MCQs by subject and past paper' },
    { icon: CheckCircle2, text: 'Detailed explanations with NICE and WHO references' },
    { icon: GraduationCap, text: 'Timed mock exams and a performance tracker' },
    { icon: BookOpen, text: 'Extended textbook, image atlas and flashcards' },
    { icon: Sparkles, text: 'AI Knowledge Tutor and a personal study plan' },
];
</script>

<template>
    <div class="relative flex min-h-svh bg-background">
        <!-- Brand panel (large screens): the logo's colours, what students get and the prices. -->
        <aside
            class="relative hidden max-w-2xl flex-col justify-between overflow-hidden bg-[#0b1224] p-10 text-white lg:flex xl:p-14"
            :class="wide ? 'w-[34%] xl:p-10' : 'w-[46%]'"
        >
            <div class="mw-auth-glow" aria-hidden="true" />
            <span class="mw-logo-line absolute inset-x-0 top-0 h-1" aria-hidden="true" />
            <Link :href="route('home')" class="relative inline-flex w-fit" :aria-label="APP_NAME + ', home page'">
                <img src="/brand/mrcp-world-logo.dark.png" :alt="APP_NAME" class="h-14 w-auto object-contain" height="56" />
            </Link>

            <div class="relative space-y-8">
                <div class="space-y-3">
                    <p class="text-sm font-semibold uppercase tracking-widest text-white/60">MRCP Part 1 · MRCP Part 2</p>
                    <h2 class="text-3xl font-semibold leading-tight xl:text-4xl">
                        Revise smarter.<br />
                        <span class="mw-auth-gradient-text">Walk into the exam ready.</span>
                    </h2>
                </div>
                <ul class="space-y-3.5">
                    <li v-for="feature in features" :key="feature.text" class="flex items-center gap-3 text-sm text-white/85">
                        <span class="flex size-8 shrink-0 items-center justify-center rounded-lg bg-white/10 ring-1 ring-white/15">
                            <component :is="feature.icon" class="mw-auth-icon size-4" aria-hidden="true" />
                        </span>
                        {{ feature.text }}
                    </li>
                </ul>
                <div class="flex flex-wrap gap-3">
                    <div class="rounded-xl bg-white/10 px-4 py-3 ring-1 ring-white/15">
                        <p class="text-xs text-white/60">3 months</p>
                        <p class="text-xl font-semibold">£8</p>
                    </div>
                    <div class="rounded-xl bg-white/10 px-4 py-3 ring-1 ring-white/15">
                        <p class="text-xs text-white/60">5 months</p>
                        <p class="text-xl font-semibold">£12</p>
                    </div>
                    <p class="self-end pb-1 text-xs text-white/60">per Qbank</p>
                </div>
            </div>

            <p class="relative flex items-center gap-2 text-xs text-white/60">
                <ShieldCheck class="size-4" aria-hidden="true" />Secure card payments by Stripe. We never see your card details.
            </p>
        </aside>

        <!-- The form. -->
        <main class="relative flex flex-1 items-center justify-center overflow-hidden px-4 py-10 sm:px-6">
            <div class="mw-auth-backdrop" aria-hidden="true" />
            <div class="relative w-full" :class="wide ? 'max-w-4xl' : 'max-w-md'">
                <Link :href="route('home')" class="mb-6 flex justify-center lg:hidden" :aria-label="APP_NAME + ', home page'">
                    <img src="/brand/mrcp-world-logo.trim.png" :alt="APP_NAME" class="h-16 w-auto object-contain dark:hidden" height="64" />
                    <img src="/brand/mrcp-world-logo.dark.png" :alt="APP_NAME" class="hidden h-16 w-auto object-contain dark:block" height="64" />
                </Link>
                <div class="relative overflow-hidden rounded-2xl border bg-card p-6 shadow-xl shadow-slate-900/5 sm:p-8">
                    <span class="mw-logo-line absolute inset-x-0 top-0 h-1" aria-hidden="true" />
                    <div class="mb-6 space-y-1.5">
                        <h1 class="text-2xl font-semibold tracking-tight">{{ title }}</h1>
                        <p class="text-sm text-muted-foreground">{{ description }}</p>
                    </div>
                    <slot />
                </div>
                <p class="mt-6 text-center text-xs text-muted-foreground">
                    <Link :href="route('home')" class="hover:text-foreground">← Back to the home page</Link>
                    <span class="mx-2">·</span>
                    <Link :href="route('legal.terms')" class="hover:text-foreground">Terms</Link>
                    <span class="mx-2">·</span>
                    <Link :href="route('legal.privacy')" class="hover:text-foreground">Privacy</Link>
                </p>
            </div>
        </main>
    </div>
</template>
