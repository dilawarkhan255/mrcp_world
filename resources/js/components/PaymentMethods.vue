<script setup lang="ts">
import { CreditCard, Lock } from 'lucide-vue-next';

/**
 * What a student can pay with on Stripe Checkout. Cards work by default;
 * Apple Pay and Google Pay appear on Stripe's page on devices that support
 * them, when they are switched on in the Stripe dashboard.
 */
const METHODS = ['Visa', 'Mastercard', 'American Express', 'Apple Pay', 'Google Pay'];
</script>
<template>
    <div class="space-y-2">
        <ul class="flex flex-wrap gap-1.5" aria-label="Ways to pay">
            <li
                v-for="method in METHODS"
                :key="method"
                class="inline-flex items-center gap-1 rounded-md border bg-background px-2 py-0.5 text-[11px] font-semibold text-foreground"
            >
                <CreditCard v-if="method === 'Visa'" class="size-3 text-primary" aria-hidden="true" />{{ method }}
            </li>
        </ul>
        <p class="flex items-center gap-1 text-[11px] text-muted-foreground">
            <Lock class="size-3" aria-hidden="true" />Secure payment by Stripe. Cards from every country, Pakistan included. We never see your card
            details.
        </p>
    </div>
</template>
