<script setup lang="ts">
import { landingCopy } from '@/content/landing';
import type { LandingHistogram } from '@/types/landing';
import { computed } from 'vue';
const props = defineProps<{ histogram: LandingHistogram }>();
const maxCount = computed(() => Math.max(1, ...props.histogram.buckets.map((bucket) => bucket.count)));
const total = computed(() => props.histogram.buckets.reduce((sum, bucket) => sum + bucket.count, 0));
const highlightedIndex = computed(() => {
    if (!total.value) return 0;
    let running = 0;
    for (let index = 0; index < props.histogram.buckets.length; index++) {
        running += props.histogram.buckets[index].count;
        if (running >= total.value / 2) return index;
    }
    return props.histogram.buckets.length - 1;
});
const highlighted = computed(() => props.histogram.buckets[highlightedIndex.value]);
const summary = computed(() => {
    const bucket = highlighted.value;
    const source = props.histogram.is_sample ? landingCopy.histogram.sample : landingCopy.histogram.real;
    return `Score distribution in ${props.histogram.buckets.length} bands from 0 to 100 percent. The middle score falls between ${bucket.start} and ${bucket.end} percent. ${source}.`;
});
function height(count: number): string {
    return Math.max(2, Math.round((count / maxCount.value) * 100)) + '%';
}
</script>
<template>
    <div class="rounded-card border bg-white p-6 shadow-sm">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h3 class="text-sm font-semibold text-ink">{{ landingCopy.histogram.title }}</h3>
            <span class="rounded-full bg-secondary px-3 py-1 text-xs font-semibold text-secondary-foreground">
                {{ histogram.is_sample ? landingCopy.histogram.sample : landingCopy.histogram.real }}
            </span>
        </div>
        <div role="img" :aria-label="summary" class="mt-6">
            <div class="flex h-44 items-end gap-1">
                <div v-for="(bucket, index) in histogram.buckets" :key="bucket.start" class="flex h-full flex-1 items-end">
                    <div
                        class="w-full rounded-t-[4px]"
                        :class="[
                            bucket.end <= 50 ? 'bg-amber-soft' : 'bg-teal',
                            index === highlightedIndex ? 'outline outline-[3px] outline-ink' : '',
                        ]"
                        :style="{ height: height(bucket.count) }"
                    />
                </div>
            </div>
            <div class="mt-3 grid grid-cols-3 text-xs font-medium text-muted-soft">
                <span>20%</span>
                <span class="text-center">{{ landingCopy.histogram.you }}</span>
                <span class="text-right">100%</span>
            </div>
        </div>
        <p v-if="histogram.is_sample" class="mt-4 text-xs text-muted-soft">{{ landingCopy.histogram.sampleNote }}</p>
    </div>
</template>
