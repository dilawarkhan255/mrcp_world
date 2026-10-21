<script setup lang="ts">
import { landingCopy } from '@/content/landing';
import type { LandingPlan, LandingPlanGroup } from '@/types/landing';
import { computed } from 'vue';
const props = defineProps<{ plans: LandingPlanGroup[] }>();
const durations = [
    { days: 30, label: landingCopy.pricing.month },
    { days: 90, label: landingCopy.pricing.quarter, popular: true },
    { days: 365, label: landingCopy.pricing.year },
];
function planFor(days: number): LandingPlan | null {
    const group = props.plans.find((entry) => entry.duration_days === days);
    return group?.plans[0] ?? null;
}
const cards = computed(() =>
    durations.map((duration) => ({
        ...duration,
        plan: planFor(duration.days),
        price: planFor(duration.days)?.price ?? landingCopy.pricing.placeholderPrice,
    })),
);
</script>
<template>
    <section id="pricing" class="scroll-mt-24 bg-surface py-16 md:py-24">
        <div class="mx-auto w-full max-w-[1280px] px-5 md:px-8">
            <h2 class="text-3xl font-bold tracking-tight text-ink sm:text-4xl">{{ landingCopy.pricing.heading }}</h2>
            <p class="mt-3 max-w-2xl text-muted-strong">{{ landingCopy.pricing.intro }}</p>
            <div class="mt-10 grid grid-cols-1 gap-5 md:grid-cols-3">
                <article
                    v-for="card in cards"
                    :key="card.days"
                    class="flex h-full flex-col rounded-card border p-6 shadow-sm"
                    :class="card.popular ? 'border-ink bg-ink text-white' : 'bg-white text-ink'"
                >
                    <p class="text-sm font-semibold" :class="card.popular ? 'text-light-teal' : 'text-teal'">{{ card.label }}</p>
                    <p v-if="card.popular" class="mt-1 text-xs font-semibold uppercase tracking-wide text-amber">{{ landingCopy.pricing.popular }}</p>
                    <p class="mt-4 font-heading text-4xl font-bold tracking-tight" :class="card.popular ? 'text-white' : 'text-ink'">
                        {{ card.price }}
                    </p>
                    <p class="mt-1 text-sm" :class="card.popular ? 'text-white/70' : 'text-muted-soft'">
                        {{ card.plan ? card.plan.name : landingCopy.pricing.empty }}
                    </p>
                    <a
                        :href="card.plan ? '/register?plan=' + card.plan.id : '/register'"
                        class="mt-6 inline-flex min-h-[44px] w-full items-center justify-center rounded-control px-5 text-sm font-semibold transition focus-visible:ring-2 focus-visible:ring-offset-2"
                        :class="
                            card.popular
                                ? 'hover:bg-amber/90 bg-amber text-ink focus-visible:ring-white focus-visible:ring-offset-ink'
                                : 'bg-teal text-white hover:bg-dark-teal focus-visible:ring-teal focus-visible:ring-offset-2'
                        "
                    >
                        {{ landingCopy.pricing.choose }}
                    </a>
                </article>
            </div>
        </div>
    </section>
</template>
