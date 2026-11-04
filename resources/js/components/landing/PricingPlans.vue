<script setup lang="ts">
import { landingCopy } from '@/content/landing';
import type { LandingPlan, LandingPlanGroup } from '@/types/landing';
import { Check } from 'lucide-vue-next';
import { computed } from 'vue';
const props = defineProps<{ plans: LandingPlanGroup[] }>();
/** One card per plan (one per Qbank), in the server's order. */
const cards = computed<LandingPlan[]>(() => props.plans.flatMap((group) => group.plans));
</script>
<template>
    <section id="pricing" class="scroll-mt-24 bg-surface py-16 md:py-24">
        <div class="mx-auto w-full max-w-[1280px] px-5 md:px-8">
            <h2 class="text-3xl font-bold tracking-tight text-ink sm:text-4xl">{{ landingCopy.pricing.heading }}</h2>
            <p class="mt-3 max-w-2xl text-muted-strong">{{ landingCopy.pricing.intro }}</p>
            <p v-if="!cards.length" class="mt-10 rounded-card border border-dashed bg-white p-10 text-center text-sm text-muted-soft">
                {{ landingCopy.pricing.placeholderPrice }} · {{ landingCopy.pricing.empty }}
            </p>
            <div v-else class="mt-10 grid grid-cols-1 gap-5 md:grid-cols-2">
                <article
                    v-for="(plan, index) in cards"
                    :key="plan.id"
                    class="flex h-full flex-col rounded-card border p-6 shadow-sm"
                    :class="index === 0 ? 'border-ink bg-ink text-white' : 'bg-white text-ink'"
                >
                    <p class="text-sm font-semibold" :class="index === 0 ? 'text-light-teal' : 'text-teal'">{{ plan.qbank ?? plan.name }}</p>
                    <p class="mt-4 font-heading text-4xl font-bold tracking-tight">
                        {{ plan.price }}
                        <span class="text-base font-semibold" :class="index === 0 ? 'text-white/70' : 'text-muted-soft'"
                            >/ {{ plan.duration ?? plan.duration_days + ' days' }}</span
                        >
                    </p>
                    <ul v-if="plan.exams?.length" class="mt-5 space-y-2 text-sm">
                        <li v-for="exam in plan.exams" :key="exam" class="flex items-center gap-2">
                            <Check class="size-4 shrink-0" :class="index === 0 ? 'text-light-teal' : 'text-teal'" aria-hidden="true" />{{ exam }}
                        </li>
                    </ul>
                    <a
                        :href="'/register?plan=' + plan.id"
                        class="mt-6 inline-flex min-h-[44px] w-full items-center justify-center rounded-control px-5 text-sm font-semibold transition focus-visible:ring-2 focus-visible:ring-offset-2"
                        :class="
                            index === 0
                                ? 'hover:bg-amber/90 bg-amber text-ink focus-visible:ring-white focus-visible:ring-offset-ink'
                                : 'bg-teal text-white hover:bg-dark-teal focus-visible:ring-teal focus-visible:ring-offset-2'
                        "
                    >
                        {{ landingCopy.pricing.choose }}
                    </a>
                    <a
                        :href="'/subscription?plan=' + plan.id"
                        class="mt-3 text-center text-xs font-semibold underline-offset-2 hover:underline"
                        :class="index === 0 ? 'text-white/80' : 'text-muted-strong'"
                        >{{ landingCopy.pricing.member }}</a
                    >
                </article>
            </div>
        </div>
    </section>
</template>
