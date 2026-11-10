<script setup lang="ts">
import { landingCopy } from '@/content/landing';
import type { LandingPlanGroup } from '@/types/landing';
import { Check, PoundSterling } from 'lucide-vue-next';
/** One card per Qbank, with one row per plan (for example 3 months and 5 months). */
defineProps<{ plans: LandingPlanGroup[] }>();
</script>
<template>
    <section id="pricing" class="scroll-mt-24 bg-surface py-16 md:py-24">
        <div class="mx-auto w-full max-w-[1280px] px-5 md:px-8">
            <h2 class="text-3xl font-bold tracking-tight text-ink sm:text-4xl">{{ landingCopy.pricing.heading }}</h2>
            <p class="mt-3 max-w-2xl text-muted-strong">{{ landingCopy.pricing.intro }}</p>
            <p v-if="!plans.length" class="mt-10 rounded-card border border-dashed bg-white p-10 text-center text-sm text-muted-soft">
                {{ landingCopy.pricing.placeholderPrice }} · {{ landingCopy.pricing.empty }}
            </p>
            <div v-else class="mt-10 grid grid-cols-1 gap-5 md:grid-cols-2">
                <article
                    v-for="(group, index) in plans"
                    :key="group.qbank ?? group.plans[0]?.id"
                    class="relative flex h-full flex-col overflow-hidden rounded-card border bg-white p-6 text-ink shadow-sm"
                    :class="index === 0 ? 'border-amber shadow-md' : ''"
                >
                    <span v-if="index === 0" class="brand-rainbow absolute inset-x-0 top-0 h-1" aria-hidden="true" />
                    <h3 class="font-heading text-lg font-semibold text-ink">{{ group.qbank ?? group.plans[0]?.name }}</h3>
                    <ul v-if="group.exams.length" class="mt-3 flex flex-wrap gap-x-5 gap-y-1 text-sm">
                        <li v-for="exam in group.exams" :key="exam" class="flex items-center gap-1.5">
                            <Check class="size-4 shrink-0 text-teal" aria-hidden="true" />{{ exam }}
                        </li>
                    </ul>
                    <ul class="mt-5 flex-1 space-y-3">
                        <li
                            v-for="plan in group.plans"
                            :key="plan.id"
                            class="flex flex-wrap items-center justify-between gap-3 rounded-control border p-4"
                        >
                            <div>
                                <p class="text-sm font-semibold text-muted-strong">{{ plan.duration }}</p>
                                <p class="flex items-center font-heading text-3xl font-bold tracking-tight">
                                    <PoundSterling v-if="plan.currency === 'GBP'" class="size-6" aria-label="GBP" />{{
                                        plan.currency === 'GBP' ? plan.amount : plan.price
                                    }}
                                </p>
                            </div>
                            <a
                                :href="'/register?plan=' + plan.id"
                                class="inline-flex min-h-[44px] items-center justify-center rounded-control bg-primary px-5 text-sm font-semibold text-primary-foreground shadow-sm transition focus-visible:ring-2 focus-visible:ring-teal focus-visible:ring-offset-2"
                                :aria-label="landingCopy.pricing.choose + ': ' + (group.qbank ?? plan.name) + ', ' + plan.duration"
                            >
                                {{ landingCopy.pricing.choose }}
                            </a>
                        </li>
                    </ul>
                    <a
                        :href="'/subscription?plan=' + group.plans[0]?.id"
                        class="mt-4 text-center text-xs font-semibold text-muted-strong underline-offset-2 hover:underline"
                        >{{ landingCopy.pricing.member }}</a
                    >
                </article>
            </div>
        </div>
    </section>
</template>
