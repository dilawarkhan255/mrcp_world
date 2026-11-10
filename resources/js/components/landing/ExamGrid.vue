<script setup lang="ts">
import { landingCopy } from '@/content/landing';
import type { LandingExam } from '@/types/landing';
const props = defineProps<{ exams: LandingExam[] }>();
const tints = ['teal', 'amber', 'indigo', 'plum'];
function tintFor(index: number): string {
    return tints[index % tints.length];
}
function tileStyle(index: number): Record<string, string> {
    const tint = tintFor(index);
    return { backgroundColor: `var(--tint-${tint}-bg)`, color: `var(--tint-${tint}-fg)` };
}
</script>
<template>
    <section id="exams" class="scroll-mt-24 bg-surface">
        <div class="mx-auto w-full max-w-[1280px] px-5 py-16 md:px-8 md:py-20">
            <h2 class="text-3xl font-bold tracking-tight text-ink sm:text-4xl">{{ landingCopy.exams.heading }}</h2>
            <p class="mt-3 max-w-2xl text-muted-strong">{{ landingCopy.exams.intro }}</p>
            <p v-if="!props.exams.length" class="mt-10 rounded-card border border-dashed bg-white p-10 text-center text-muted-soft">
                {{ landingCopy.exams.empty }}
            </p>
            <div v-else class="mt-10 grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-3">
                <article v-for="(exam, index) in props.exams" :key="exam.id" class="flex h-full flex-col rounded-card border bg-white p-6 shadow-sm">
                    <span
                        class="flex size-12 items-center justify-center rounded-xl font-heading text-base font-bold"
                        :style="tileStyle(index)"
                        aria-hidden="true"
                        >{{ exam.code }}</span
                    >
                    <h3 class="mt-5 font-heading text-lg font-semibold text-ink">{{ exam.name }}</h3>
                    <p class="mt-2 flex-1 text-sm leading-relaxed text-muted-soft">
                        <template v-if="exam.description">{{ exam.description }} </template>
                        <template v-if="exam.question_count > 0">{{ landingCopy.exams.questions(exam.question_count) }}</template>
                    </p>
                    <div class="mt-6 flex flex-wrap gap-3">
                        <a
                            :href="'/exams/' + exam.slug + '/demo'"
                            class="inline-flex min-h-[44px] flex-1 items-center justify-center whitespace-nowrap rounded-control border border-border bg-white px-4 text-sm font-semibold text-ink transition hover:border-amber hover:text-[#d9620b] focus-visible:ring-2 focus-visible:ring-teal focus-visible:ring-offset-2"
                        >
                            {{ landingCopy.exams.demo }}
                        </a>
                        <a
                            :href="'/login?exam=' + exam.slug"
                            class="inline-flex min-h-[44px] flex-1 items-center justify-center whitespace-nowrap rounded-control border border-border bg-white px-4 text-sm font-semibold text-ink transition hover:border-amber hover:text-[#d9620b] focus-visible:ring-2 focus-visible:ring-teal focus-visible:ring-offset-2"
                        >
                            {{ landingCopy.exams.logIn }}
                        </a>
                        <a
                            :href="'/register?exam=' + exam.slug"
                            class="inline-flex min-h-[44px] flex-1 items-center justify-center whitespace-nowrap rounded-control bg-primary px-4 text-sm font-semibold text-primary-foreground transition focus-visible:ring-2 focus-visible:ring-teal focus-visible:ring-offset-2"
                        >
                            {{ landingCopy.exams.signUp }}
                        </a>
                    </div>
                </article>
            </div>
        </div>
    </section>
</template>
