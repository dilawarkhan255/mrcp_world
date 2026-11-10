<script setup lang="ts">
import PublicLayout from '@/layouts/PublicLayout.vue';
import { Head } from '@inertiajs/vue3';
import { CreditCard, Flag, Mail, MapPin, MessageCircle } from 'lucide-vue-next';

/** How to reach the team. The details come from config/legal.php and config/services.php. */
defineProps<{ appName: string; email: string; address: string; whatsapp: string | null }>();
</script>
<template>
    <Head title="Contact">
        <meta name="description" :content="`How to reach the team behind ${appName}.`" />
    </Head>
    <PublicLayout :app-name="appName">
        <div class="mx-auto w-full max-w-3xl px-5 py-16 md:px-8 md:py-24">
            <h1 class="text-3xl font-bold tracking-tight text-ink sm:text-4xl">Contact</h1>
            <p class="mt-4 text-base leading-relaxed text-muted-strong">
                Questions about your account, a subscription or an MCQ? Get in touch and we will reply as soon as we can.
            </p>

            <div class="mt-10 grid gap-4 sm:grid-cols-2">
                <section class="rounded-card border bg-white p-5">
                    <h2 class="flex items-center gap-2 font-heading font-semibold text-ink">
                        <Mail class="size-4 text-teal" aria-hidden="true" />Email
                    </h2>
                    <a v-if="email.includes('@')" :href="'mailto:' + email" class="mt-2 inline-block font-semibold text-teal hover:underline">{{
                        email
                    }}</a>
                    <p v-else class="mt-2 text-muted-strong">{{ email }}</p>
                </section>
                <section v-if="whatsapp" class="rounded-card border bg-white p-5">
                    <h2 class="flex items-center gap-2 font-heading font-semibold text-ink">
                        <MessageCircle class="size-4 text-teal" aria-hidden="true" />WhatsApp
                    </h2>
                    <a :href="whatsapp" target="_blank" rel="noopener noreferrer" class="mt-2 inline-block font-semibold text-teal hover:underline"
                        >Join our WhatsApp group</a
                    >
                </section>
                <section class="rounded-card border bg-white p-5">
                    <h2 class="flex items-center gap-2 font-heading font-semibold text-ink">
                        <MapPin class="size-4 text-teal" aria-hidden="true" />Postal address
                    </h2>
                    <p class="mt-2 text-muted-strong">{{ address }}</p>
                </section>
                <section class="rounded-card border bg-white p-5">
                    <h2 class="flex items-center gap-2 font-heading font-semibold text-ink">
                        <CreditCard class="size-4 text-teal" aria-hidden="true" />Subscriptions
                    </h2>
                    <p class="mt-2 text-sm text-muted-strong">
                        See the <a href="/#pricing" class="font-semibold text-teal hover:underline">plans</a>, or log in and open
                        <a href="/subscription" class="font-semibold text-teal hover:underline">Subscription</a> to check or renew your access.
                    </p>
                </section>
                <section class="rounded-card border bg-white p-5 sm:col-span-2">
                    <h2 class="flex items-center gap-2 font-heading font-semibold text-ink">
                        <Flag class="size-4 text-teal" aria-hidden="true" />Found an error in an MCQ?
                    </h2>
                    <p class="mt-2 text-sm text-muted-strong">
                        Use <strong>Report MCQ</strong> above the question while you practise. The report reaches the editors together with the
                        question, so they can fix it quickly.
                    </p>
                </section>
            </div>
        </div>
    </PublicLayout>
</template>
