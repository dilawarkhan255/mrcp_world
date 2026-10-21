<script setup lang="ts">
import { landingCopy } from '@/content/landing';
defineProps<{ demoSlug: string | null }>();
</script>
<template>
    <section class="bg-gradient-to-b from-light-teal via-surface to-surface">
        <div class="mx-auto w-full max-w-[1280px] px-5 py-16 text-center md:px-8 md:py-24">
            <p
                class="border-teal/20 inline-flex items-center rounded-full border bg-white px-4 py-2 text-xs font-semibold tracking-wide text-dark-teal shadow-sm sm:text-sm"
            >
                {{ landingCopy.hero.badge }}
            </p>
            <h1 class="mx-auto mt-8 max-w-4xl text-4xl font-bold leading-[1.08] tracking-tight text-ink sm:text-[44px] lg:text-[56px]">
                {{ landingCopy.hero.title }}
            </h1>
            <p class="mx-auto mt-6 max-w-[620px] text-base text-muted-strong sm:text-lg">{{ landingCopy.hero.body }}</p>
            <div class="mt-10 flex flex-wrap items-center justify-center gap-4">
                <a
                    href="#exams"
                    class="inline-flex min-h-[44px] items-center justify-center rounded-control bg-teal px-7 py-3 text-base font-semibold text-white shadow-sm transition hover:bg-dark-teal focus-visible:ring-2 focus-visible:ring-teal focus-visible:ring-offset-2"
                >
                    {{ landingCopy.hero.primary }}
                </a>
                <a
                    v-if="demoSlug"
                    :href="'/exams/' + demoSlug + '/demo'"
                    class="hover:border-teal/40 inline-flex min-h-[44px] items-center justify-center rounded-control border border-border bg-white px-7 py-3 text-base font-semibold text-ink shadow-sm transition focus-visible:ring-2 focus-visible:ring-teal focus-visible:ring-offset-2"
                >
                    {{ landingCopy.hero.secondary }}
                </a>
            </div>
        </div>
    </section>
</template>
