<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head } from '@inertiajs/vue3';

defineProps<{ plans: { id: number; name: string; price: string; duration_days: number }[] }>();
</script>
<template>
    <Head title="Subscription" />
    <AppLayout :breadcrumbs="[{ title: 'Subscription', href: '/subscription' }]">
        <div class="mx-auto w-full max-w-4xl space-y-6 p-6 md:p-10">
            <div>
                <p class="text-xs font-semibold uppercase tracking-widest text-primary">Subscription</p>
                <h1 class="mt-2 text-3xl font-semibold">Choose a plan</h1>
                <p class="mt-3 text-sm text-muted-foreground">Online payment is coming soon. Until then, contact the MRCPWorld team to subscribe.</p>
            </div>

            <div v-if="!plans.length" class="rounded-xl border border-dashed p-10 text-center text-muted-foreground">
                No plans are available at the moment. Please check back later.
            </div>

            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <section v-for="plan in plans" :key="plan.id" class="rounded-xl border bg-card p-6">
                    <h2 class="text-lg font-semibold">{{ plan.name }}</h2>
                    <p class="mt-3 text-2xl font-semibold text-primary">{{ plan.price }}</p>
                    <p class="mt-1 text-sm text-muted-foreground">{{ plan.duration_days }} days of access</p>
                </section>
            </div>
        </div>
    </AppLayout>
</template>
