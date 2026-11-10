<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import PaymentMethods from '@/components/PaymentMethods.vue';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AuthBase from '@/layouts/AuthLayout.vue';
import type { SharedData } from '@/types';
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { CheckCircle2, CreditCard, LoaderCircle, Lock, PoundSterling } from 'lucide-vue-next';
import { computed } from 'vue';

interface PlanOption {
    id: number;
    qbank: string;
    exams: string[];
    price: string;
    duration: string;
}

const props = defineProps<{
    selectedExam?: { name: string; slug: string } | null;
    plans: PlanOption[];
    selectedPlan?: number | null;
    paymentInstructions: string;
}>();

/** With card payments on, the account is created and the student goes straight to Stripe Checkout. */
const card = computed(() => !!usePage<SharedData>().props.payments?.card);

// The account is created together with the subscription request.
const form = useForm({
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
    plan_id: props.selectedPlan ?? (props.plans.length === 1 ? props.plans[0].id : null),
    payment_reference: '',
    note: '',
});

const submit = () => {
    form.post(route('register'), {
        onFinish: () => form.reset('password', 'password_confirmation'),
    });
};
</script>

<template>
    <AuthBase title="Subscribe and create your account" description="Your account is created with your subscription. Choose your Qbank first.">
        <Head title="Subscribe" />

        <form @submit.prevent="submit" class="flex flex-col gap-6">
            <fieldset class="grid gap-3">
                <legend class="mb-2 text-sm font-medium">1. Choose your Qbank</legend>
                <p v-if="!props.plans.length" class="rounded-lg border border-dashed p-4 text-center text-sm text-muted-foreground">
                    No Qbank is on sale at the moment. Please check back later.
                </p>
                <label
                    v-for="plan in props.plans"
                    :key="plan.id"
                    class="flex cursor-pointer items-start gap-3 rounded-lg border p-3 transition-colors"
                    :class="form.plan_id === plan.id ? 'border-primary bg-secondary' : 'hover:border-primary/50'"
                >
                    <input v-model="form.plan_id" type="radio" name="plan" :value="plan.id" class="mt-1 accent-primary" required />
                    <span class="min-w-0 flex-1">
                        <span class="flex items-baseline justify-between gap-2">
                            <span class="font-semibold"
                                >{{ plan.qbank }} <span class="font-normal text-muted-foreground">· {{ plan.duration }}</span></span
                            >
                            <span class="text-sm"
                                ><strong class="inline-flex items-center text-primary"
                                    ><PoundSterling v-if="plan.price.startsWith('£')" class="size-4" aria-label="GBP" />{{
                                        plan.price.replace(/^£/, '')
                                    }}</strong
                                ></span
                            >
                        </span>
                        <span class="mt-1 block text-xs text-muted-foreground">For {{ plan.exams.join(', ') }}</span>
                    </span>
                    <CheckCircle2 v-if="form.plan_id === plan.id" class="mt-0.5 size-4 shrink-0 text-primary" aria-hidden="true" />
                </label>
                <InputError :message="form.errors.plan_id" />
                <p v-if="selectedExam" class="text-xs text-muted-foreground">You came from {{ selectedExam.name }}.</p>
            </fieldset>

            <div v-if="card" class="grid gap-2 rounded-md border bg-muted/40 p-3">
                <p class="flex items-center gap-2 text-sm font-medium"><CreditCard class="size-4 text-primary" aria-hidden="true" />2. Pay by card</p>
                <p class="text-xs leading-relaxed text-muted-foreground">
                    After you create your account you go to Stripe's secure payment page. Cards from every country Stripe accepts, Pakistan included,
                    work there. Your Qbank opens as soon as the payment is confirmed.
                </p>
                <PaymentMethods />
            </div>
            <fieldset v-else class="grid gap-2">
                <legend class="mb-2 text-sm font-medium">2. Pay and enter your payment reference</legend>
                <p class="rounded-md bg-muted/60 p-3 text-xs leading-relaxed">{{ paymentInstructions }}</p>
                <Label for="payment_reference">Payment reference</Label>
                <Input
                    id="payment_reference"
                    v-model="form.payment_reference"
                    required
                    minlength="3"
                    maxlength="255"
                    placeholder="Transaction ID or receipt number"
                />
                <InputError :message="form.errors.payment_reference" />
                <Label for="note">Note for the team (optional)</Label>
                <textarea id="note" v-model="form.note" rows="2" maxlength="1000" class="rounded-md border bg-background px-3 py-2 text-sm" />
            </fieldset>

            <p class="-mb-2 text-sm font-medium">3. Your account</p>
            <div class="grid gap-6">
                <div class="grid gap-2">
                    <Label for="name">Name</Label>
                    <Input id="name" type="text" required autocomplete="name" v-model="form.name" placeholder="Full name" />
                    <InputError :message="form.errors.name" />
                </div>

                <div class="grid gap-2">
                    <Label for="email">Email address</Label>
                    <Input id="email" type="email" required autocomplete="email" v-model="form.email" placeholder="email@example.com" />
                    <InputError :message="form.errors.email" />
                </div>

                <div class="grid gap-2">
                    <Label for="password">Password</Label>
                    <Input id="password" type="password" required autocomplete="new-password" v-model="form.password" placeholder="Password" />
                    <InputError :message="form.errors.password" />
                </div>

                <div class="grid gap-2">
                    <Label for="password_confirmation">Confirm password</Label>
                    <Input
                        id="password_confirmation"
                        type="password"
                        required
                        autocomplete="new-password"
                        v-model="form.password_confirmation"
                        placeholder="Confirm password"
                    />
                    <InputError :message="form.errors.password_confirmation" />
                </div>

                <Button type="submit" class="mt-2 w-full" :disabled="form.processing">
                    <LoaderCircle v-if="form.processing" class="h-4 w-4 animate-spin" />
                    {{ card ? 'Create account and pay by card' : 'Subscribe and create account' }}
                </Button>
                <p v-if="card" class="flex items-center justify-center gap-1 text-center text-xs text-muted-foreground">
                    <Lock class="size-3" aria-hidden="true" />Payments are handled by Stripe. We never see your card details.
                </p>
                <p v-else class="text-center text-xs text-muted-foreground">
                    You can log in straight away. Your Qbank opens once the team confirms the payment.
                </p>
            </div>

            <div class="text-center text-sm text-muted-foreground">
                Already have an account?
                <TextLink :href="route('login')" class="underline underline-offset-4">Log in</TextLink>
            </div>
        </form>
    </AuthBase>
</template>
