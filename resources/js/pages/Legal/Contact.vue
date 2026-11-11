<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import PublicLayout from '@/layouts/PublicLayout.vue';
import type { SharedData } from '@/types';
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { CheckCircle2, CreditCard, Flag, LoaderCircle, Mail, MapPin, MessageCircle, Phone, Send } from 'lucide-vue-next';
import { computed } from 'vue';

/**
 * How to reach the team: email, phone, a short contact form and help links.
 * The details come from config/legal.php (LEGAL_CONTACT_EMAIL, LEGAL_PHONE,
 * LEGAL_ADDRESS) and config/services.php. A detail that is not set (still a
 * "[…]" placeholder) is not shown.
 */
const props = defineProps<{ appName: string; email: string; address: string; whatsapp: string | null; phone?: string }>();

const page = usePage<SharedData & { flash?: { status?: string | null } }>();
const sent = computed(() => page.props.flash?.status ?? null);
const hasEmail = computed(() => props.email.includes('@'));
const hasAddress = computed(() => props.address !== '' && !props.address.startsWith('['));
/** "0313 4488339" → "+923134488339" for the call link (a Pakistani number starting with 0). */
const telHref = computed(() => {
    const digits = (props.phone ?? '').replace(/[^\d+]/g, '');
    return 'tel:' + (digits.startsWith('0') ? '+92' + digits.slice(1) : digits);
});

const form = useForm({ name: '', email: '', phone: '', message: '', website: '' });
const submit = () => form.post('/contact', { preserveScroll: true, onSuccess: () => form.reset() });

const field =
    'w-full rounded-lg border border-slate-200 bg-white px-3 py-2.5 text-sm text-ink placeholder:text-slate-400 focus:border-transparent focus:outline-none focus:ring-2 focus:ring-[#1e6fd9]/40';
</script>
<template>
    <Head title="Contact">
        <meta name="description" :content="`How to reach the team behind ${appName}.`" />
    </Head>
    <PublicLayout :app-name="appName">
        <div class="mx-auto w-full max-w-5xl px-5 py-16 md:px-8 md:py-24">
            <h1 class="text-3xl font-bold tracking-tight text-ink sm:text-4xl">Contact</h1>
            <p class="mt-4 max-w-2xl text-base leading-relaxed text-muted-strong">
                Questions about your account, a subscription or an MCQ? Send us a message, email or call, and we will reply as soon as we can.
            </p>

            <!-- Direct contact -->
            <div class="mt-10 grid gap-4 sm:grid-cols-2">
                <section v-if="hasEmail" class="mw-logo-left rounded-card border bg-white p-5">
                    <h2 class="flex items-center gap-2 font-heading font-semibold text-ink">
                        <Mail class="size-4 text-primary" aria-hidden="true" />Email
                    </h2>
                    <a :href="'mailto:' + email" class="mt-2 inline-block break-all font-semibold text-teal hover:underline">{{ email }}</a>
                </section>
                <section v-if="phone" class="mw-logo-left rounded-card border bg-white p-5">
                    <h2 class="flex items-center gap-2 font-heading font-semibold text-ink">
                        <Phone class="size-4 text-primary" aria-hidden="true" />Phone
                    </h2>
                    <a :href="telHref" class="mt-2 inline-block font-semibold text-teal hover:underline">{{ phone }}</a>
                </section>
                <section v-if="whatsapp" class="mw-logo-left rounded-card border bg-white p-5">
                    <h2 class="flex items-center gap-2 font-heading font-semibold text-ink">
                        <MessageCircle class="size-4 text-primary" aria-hidden="true" />WhatsApp
                    </h2>
                    <a :href="whatsapp" target="_blank" rel="noopener noreferrer" class="mt-2 inline-block font-semibold text-teal hover:underline"
                        >Join our WhatsApp group</a
                    >
                </section>
                <section v-if="hasAddress" class="mw-logo-left rounded-card border bg-white p-5">
                    <h2 class="flex items-center gap-2 font-heading font-semibold text-ink">
                        <MapPin class="size-4 text-primary" aria-hidden="true" />Postal address
                    </h2>
                    <p class="mt-2 text-muted-strong">{{ address }}</p>
                </section>
            </div>

            <div class="mt-8 grid gap-6 lg:grid-cols-[minmax(0,3fr)_minmax(0,2fr)]">
                <!-- Contact form -->
                <section class="relative overflow-hidden rounded-card border bg-white p-6" aria-labelledby="contact-form-heading">
                    <span class="mw-logo-line absolute inset-x-0 top-0 h-1" aria-hidden="true" />
                    <h2 id="contact-form-heading" class="flex items-center gap-2 font-heading text-lg font-semibold text-ink">
                        <Send class="size-4 text-primary" aria-hidden="true" />Send us a message
                    </h2>
                    <div
                        v-if="sent"
                        role="status"
                        class="mt-4 flex items-start gap-2 rounded-lg border border-green-600/30 bg-green-600/10 p-3 text-sm text-green-800"
                    >
                        <CheckCircle2 class="mt-0.5 size-4 shrink-0" aria-hidden="true" />{{ sent }}
                    </div>
                    <form class="mt-4 grid gap-4" @submit.prevent="submit">
                        <div class="grid gap-4 sm:grid-cols-2">
                            <label class="grid gap-1.5 text-sm font-medium text-ink"
                                >Name
                                <input
                                    placeholder="Your full name"
                                    v-model="form.name"
                                    :class="field"
                                    type="text"
                                    required
                                    maxlength="120"
                                    autocomplete="name"
                                />
                                <InputError :message="form.errors.name" />
                            </label>
                            <label class="grid gap-1.5 text-sm font-medium text-ink"
                                >Email
                                <input
                                    placeholder="you@example.com"
                                    v-model="form.email"
                                    :class="field"
                                    type="email"
                                    required
                                    maxlength="190"
                                    autocomplete="email"
                                />
                                <InputError :message="form.errors.email" />
                            </label>
                        </div>
                        <label class="grid gap-1.5 text-sm font-medium text-ink"
                            >Phone <span class="text-xs font-normal text-muted-strong">(optional)</span>
                            <input v-model="form.phone" :class="field" type="tel" maxlength="40" autocomplete="tel" placeholder="03xx xxxxxxx" />
                            <InputError :message="form.errors.phone" />
                        </label>
                        <label class="grid gap-1.5 text-sm font-medium text-ink"
                            >Message
                            <textarea
                                v-model="form.message"
                                :class="field"
                                rows="5"
                                required
                                minlength="10"
                                maxlength="3000"
                                placeholder="How can we help?"
                            />
                            <InputError :message="form.errors.message" />
                        </label>
                        <!-- Left empty by people; bots fill it in. -->
                        <input v-model="form.website" type="text" name="website" tabindex="-1" autocomplete="off" class="hidden" aria-hidden="true" />
                        <InputError :message="form.errors.website" />
                        <button
                            type="submit"
                            class="inline-flex h-11 items-center justify-center gap-2 rounded-lg bg-primary px-6 text-sm font-semibold text-primary-foreground disabled:opacity-60 sm:w-fit"
                            :disabled="form.processing"
                        >
                            <LoaderCircle v-if="form.processing" class="size-4 animate-spin" aria-hidden="true" /><Send
                                v-else
                                class="size-4"
                                aria-hidden="true"
                            />
                            {{ form.processing ? 'Sending…' : 'Send message' }}
                        </button>
                        <p class="text-xs text-muted-strong">We use your details only to answer this message.</p>
                    </form>
                </section>

                <!-- Help -->
                <div class="grid content-start gap-4">
                    <section class="rounded-card border bg-white p-5">
                        <h2 class="flex items-center gap-2 font-heading font-semibold text-ink">
                            <CreditCard class="size-4 text-primary" aria-hidden="true" />Subscriptions
                        </h2>
                        <p class="mt-2 text-sm text-muted-strong">
                            See the <a href="/#pricing" class="font-semibold text-teal hover:underline">plans</a>, or log in and open
                            <a href="/subscription" class="font-semibold text-teal hover:underline">Subscription</a> to check or renew your access.
                        </p>
                    </section>
                    <section class="rounded-card border bg-white p-5">
                        <h2 class="flex items-center gap-2 font-heading font-semibold text-ink">
                            <Flag class="size-4 text-primary" aria-hidden="true" />Found an error in an MCQ?
                        </h2>
                        <p class="mt-2 text-sm text-muted-strong">
                            Use <strong>Report MCQ</strong> above the question while you practise. The report reaches the editors together with the
                            question, so they can fix it quickly.
                        </p>
                    </section>
                </div>
            </div>
        </div>
    </PublicLayout>
</template>
