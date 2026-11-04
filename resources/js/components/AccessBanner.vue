<script setup lang="ts">
import type { SharedData } from '@/types';
import { Link, usePage } from '@inertiajs/vue3';
import { Info, Lock } from 'lucide-vue-next';
import { computed } from 'vue';
/**
 * The flash message of the last action, and a reminder that a student without
 * a subscription is using the free demo questions.
 */
const page = usePage<SharedData>();
const status = computed(() => page.props.flash?.status ?? null);
const access = computed(() => page.props.access ?? null);
const onSubscription = computed(() => page.url.startsWith('/subscription'));
</script>
<template>
    <div v-if="status || (access && !access.has_access && !onSubscription)" class="space-y-2 px-4 pt-4 md:px-8">
        <p v-if="status" role="status" class="flex items-start gap-2 rounded-lg border bg-secondary px-4 py-3 text-sm text-secondary-foreground">
            <Info class="mt-0.5 size-4 shrink-0" aria-hidden="true" />{{ status }}
        </p>
        <div
            v-if="access && !access.has_access && !onSubscription"
            class="flex flex-wrap items-center justify-between gap-3 rounded-lg border px-4 py-3 text-sm"
            style="background: var(--tint-amber-bg); color: var(--tint-amber-fg)"
        >
            <span class="flex items-center gap-2"
                ><Lock class="size-4" aria-hidden="true" />Free demo mode. Subscribe to {{ access.qbank ?? 'this Qbank' }} for every MCQ, past paper,
                mock, the textbook and the Image Atlas.</span
            >
            <Link href="/subscription" class="rounded-md bg-primary px-3 py-1.5 text-xs font-semibold text-primary-foreground">Subscribe</Link>
        </div>
    </div>
</template>
