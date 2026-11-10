<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import PaymentMethods from '@/components/PaymentMethods.vue';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import type { SharedData } from '@/types';
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import { CheckCircle2, Clock, CreditCard, LoaderCircle, PoundSterling } from 'lucide-vue-next';
import { computed, nextTick, onMounted, ref } from 'vue';

interface QbankPlan {
    id: number;
    name: string;
    price: string;
    duration_days: number;
    duration: string;
}
interface QbankCard {
    id: number;
    name: string;
    description: string | null;
    exams: string[];
    is_selected: boolean;
    has_access: boolean;
    ends_at: string | null;
    pending: boolean;
    plans: QbankPlan[];
}
interface HistoryRow {
    id: number;
    qbank: string | null;
    state: string;
    status: string;
    starts_at: string | null;
    ends_at: string | null;
    created_at: string | null;
}

const props = defineProps<{
    qbanks: QbankCard[];
    history: HistoryRow[];
    paymentInstructions: string;
    selectedPlan?: number | null;
    checkoutCancelled?: boolean;
}>();
/** Card payments on Stripe Checkout; the manual request stays for other ways of paying. */
const card = computed(() => !!usePage<SharedData>().props.payments?.card);
const paying = ref<number | null>(null);
const cardErrors = ref<Record<number, string>>({});
function payByCard(plan: QbankPlan) {
    paying.value = plan.id;
    cardErrors.value = {};
    router.post(
        '/subscription/checkout',
        { plan_id: plan.id },
        {
            onError: (errors) => (cardErrors.value = { [plan.id]: errors.plan_id ?? 'The card payment could not be started.' }),
            onFinish: () => (paying.value = null),
        },
    );
}

const requesting = ref<number | null>(null);
const form = useForm({ plan_id: 0, payment_reference: '', note: '' });
function open(plan: QbankPlan) {
    requesting.value = plan.id;
    form.plan_id = plan.id;
    form.clearErrors();
}
function submit() {
    form.post('/subscription', {
        preserveScroll: true,
        onSuccess: () => {
            requesting.value = null;
            form.reset();
        },
    });
}
// A plan chosen on the landing page opens its request form (manual payment) straight away.
onMounted(() => {
    const plan = props.qbanks.flatMap((qbank) => (qbank.pending ? [] : qbank.plans)).find((item) => item.id === props.selectedPlan);
    if (plan) {
        if (!card.value) open(plan);
        nextTick(() => document.getElementById('plan-' + plan.id)?.scrollIntoView({ block: 'center' }));
    }
});
function date(value: string | null): string {
    return value ? new Date(value).toLocaleDateString(undefined, { day: 'numeric', month: 'long', year: 'numeric' }) : '—';
}
</script>
<template>
    <Head title="Subscription" />
    <AppLayout :breadcrumbs="[{ title: 'Subscription', href: '/subscription' }]">
        <div class="mx-auto w-full max-w-5xl space-y-8 p-6 md:p-10">
            <div>
                <p class="text-xs font-semibold uppercase tracking-widest text-primary">Subscription</p>
                <h1 class="mt-2 text-3xl font-semibold">Choose your Qbank</h1>
                <p class="mt-3 max-w-2xl text-sm text-muted-foreground">
                    Each Qbank includes its main MCQ bank and every past paper MCQ, the extended textbook and the Image Atlas.
                </p>
                <div v-if="card" class="mt-3 max-w-2xl rounded-lg border bg-card p-3">
                    <PaymentMethods />
                </div>
            </div>
            <p v-if="checkoutCancelled" role="status" class="bg-amber/10 rounded-lg border border-amber p-3 text-sm">
                The card payment was cancelled. You have not been charged; you can try again below.
            </p>

            <div v-if="!qbanks.length" class="rounded-xl border border-dashed p-10 text-center text-muted-foreground">
                No Qbanks are available at the moment. Please check back later.
            </div>

            <div class="grid gap-5 md:grid-cols-2">
                <section
                    v-for="qbank in qbanks"
                    :key="qbank.id"
                    class="flex flex-col rounded-xl border bg-card p-6"
                    :class="qbank.is_selected ? 'border-primary ring-1 ring-primary/30' : ''"
                >
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <h2 class="text-lg font-semibold">{{ qbank.name }}</h2>
                            <p class="mt-1 text-sm text-muted-foreground">For {{ qbank.exams.join(', ') }}</p>
                        </div>
                        <span
                            v-if="qbank.has_access"
                            class="inline-flex items-center gap-1 rounded-full bg-green-50 px-2.5 py-1 text-xs font-semibold text-green-800 dark:bg-green-950 dark:text-green-300"
                            ><CheckCircle2 class="size-3.5" aria-hidden="true" />Active</span
                        >
                        <span
                            v-else-if="qbank.pending"
                            class="inline-flex items-center gap-1 rounded-full px-2.5 py-1 text-xs font-semibold"
                            style="background: var(--tint-amber-bg); color: var(--tint-amber-fg)"
                            ><Clock class="size-3.5" aria-hidden="true" />Waiting for activation</span
                        >
                    </div>
                    <p v-if="qbank.has_access && qbank.ends_at" class="mt-3 text-sm">Access until {{ date(qbank.ends_at) }}.</p>

                    <ul class="mt-5 space-y-3">
                        <li v-for="plan in qbank.plans" :id="'plan-' + plan.id" :key="plan.id" class="rounded-lg border p-4">
                            <div class="flex flex-wrap items-baseline justify-between gap-2">
                                <p>
                                    <span class="block text-sm font-medium text-muted-foreground">{{ plan.duration }}</span>
                                    <span class="inline-flex items-center gap-0.5 text-2xl font-semibold text-primary"
                                        ><PoundSterling v-if="plan.price.startsWith('£')" class="size-5" aria-label="GBP" />{{
                                            plan.price.replace(/^£/, '')
                                        }}</span
                                    >
                                </p>
                                <div v-if="card" class="flex flex-wrap items-center gap-2">
                                    <Button size="sm" :disabled="qbank.pending || paying !== null" @click="payByCard(plan)"
                                        ><LoaderCircle v-if="paying === plan.id" class="size-4 animate-spin" aria-hidden="true" /><CreditCard
                                            v-else
                                            class="size-4"
                                            aria-hidden="true"
                                        />{{ qbank.has_access ? 'Renew by card' : 'Pay by card' }}</Button
                                    >
                                    <button
                                        v-if="requesting !== plan.id"
                                        type="button"
                                        class="text-xs font-medium text-muted-foreground underline-offset-2 hover:underline disabled:opacity-50"
                                        :disabled="qbank.pending"
                                        @click="open(plan)"
                                    >
                                        Paid another way?
                                    </button>
                                </div>
                                <Button v-else-if="requesting !== plan.id" size="sm" :disabled="qbank.pending" @click="open(plan)"
                                    ><CreditCard class="size-4" aria-hidden="true" />{{ qbank.has_access ? 'Renew' : 'Subscribe' }}</Button
                                >
                            </div>
                            <InputError v-if="cardErrors[plan.id]" class="mt-2" :message="cardErrors[plan.id]" />
                            <form v-if="requesting === plan.id" class="mt-4 space-y-3" @submit.prevent="submit">
                                <p class="rounded-md bg-muted/60 p-3 text-xs leading-relaxed">{{ paymentInstructions }}</p>
                                <label class="grid gap-1.5 text-sm font-medium"
                                    >Payment reference
                                    <input
                                        v-model="form.payment_reference"
                                        required
                                        minlength="3"
                                        maxlength="255"
                                        placeholder="Transaction ID or receipt number"
                                        class="rounded-md border bg-background px-3 py-2 text-sm"
                                    />
                                </label>
                                <InputError :message="form.errors.payment_reference || form.errors.plan_id" />
                                <label class="grid gap-1.5 text-sm font-medium"
                                    >Note for the team (optional)
                                    <textarea
                                        v-model="form.note"
                                        rows="2"
                                        maxlength="1000"
                                        class="rounded-md border bg-background px-3 py-2 text-sm"
                                    />
                                </label>
                                <div class="flex gap-2">
                                    <Button type="submit" size="sm" :disabled="form.processing">{{
                                        form.processing ? 'Sending…' : 'Send request'
                                    }}</Button>
                                    <Button type="button" size="sm" variant="ghost" @click="requesting = null">Cancel</Button>
                                </div>
                            </form>
                        </li>
                        <li v-if="!qbank.plans.length" class="text-sm text-muted-foreground">No plan is on sale for this Qbank yet.</li>
                    </ul>
                </section>
            </div>

            <section v-if="history.length" class="space-y-3">
                <h2 class="text-lg font-semibold">Your requests and subscriptions</h2>
                <div class="overflow-x-auto rounded-xl border bg-card">
                    <table class="w-full text-sm">
                        <thead class="bg-muted/50 text-left text-xs uppercase tracking-wide text-muted-foreground">
                            <tr>
                                <th class="px-4 py-2">Qbank</th>
                                <th class="px-4 py-2">State</th>
                                <th class="px-4 py-2">From</th>
                                <th class="px-4 py-2">Until</th>
                                <th class="px-4 py-2">Requested</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="row in history" :key="row.id" class="border-t">
                                <td class="px-4 py-2 font-medium">{{ row.qbank }}</td>
                                <td class="px-4 py-2">{{ row.state }}</td>
                                <td class="px-4 py-2">{{ date(row.starts_at) }}</td>
                                <td class="px-4 py-2">{{ date(row.ends_at) }}</td>
                                <td class="px-4 py-2">{{ date(row.created_at) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>
        </div>
    </AppLayout>
</template>
