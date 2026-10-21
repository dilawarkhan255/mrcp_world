<script setup lang="ts">
import ExamGrid from '@/components/landing/ExamGrid.vue';
import FeatureSplit from '@/components/landing/FeatureSplit.vue';
import Hero from '@/components/landing/Hero.vue';
import PricingPlans from '@/components/landing/PricingPlans.vue';
import ScoreHistogram from '@/components/landing/ScoreHistogram.vue';
import TextbookCard from '@/components/landing/TextbookCard.vue';
import TopicProgressCard from '@/components/landing/TopicProgressCard.vue';
import { landingCopy, landingSeo } from '@/content/landing';
import PublicLayout from '@/layouts/PublicLayout.vue';
import type { SharedData } from '@/types';
import type { LandingExam, LandingHistogram, LandingPlanGroup } from '@/types/landing';
import { Head, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
const props = defineProps<{
    appName: string;
    exams: LandingExam[];
    plans: LandingPlanGroup[];
    histogram: LandingHistogram;
}>();
const page = usePage<SharedData>();
const demoSlug = computed(() => props.exams[0]?.slug ?? null);
const ogImage = computed(() => `${page.props.ziggy.url}/brand/mrcp-world-logo.png`);
</script>
<template>
    <Head :title="landingSeo.title">
        <meta name="description" :content="landingSeo.description" />
        <meta property="og:title" :content="landingSeo.ogTitle" />
        <meta property="og:description" :content="landingSeo.ogDescription" />
        <meta property="og:type" :content="landingSeo.ogType" />
        <meta property="og:image" :content="ogImage" />
        <meta name="twitter:card" content="summary_large_image" />
    </Head>
    <PublicLayout :app-name="appName">
        <Hero :demo-slug="demoSlug" />
        <ExamGrid :exams="exams" />
        <FeatureSplit id="features" tone="white" :title="landingCopy.features.textbook.title" :body="landingCopy.features.textbook.body">
            <TextbookCard />
        </FeatureSplit>
        <FeatureSplit tone="surface" visual="left" :title="landingCopy.features.build.title" :body="landingCopy.features.build.body">
            <TopicProgressCard />
        </FeatureSplit>
        <FeatureSplit tone="white" :title="landingCopy.features.compare.title" :body="landingCopy.features.compare.body">
            <ScoreHistogram :histogram="histogram" />
        </FeatureSplit>
        <PricingPlans :plans="plans" />
    </PublicLayout>
</template>
