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
import { CheckCircle2, CreditCard, LoaderCircle, PoundSterling } from 'lucide-vue-next';
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
    <AuthBase
        wide
        title="Choose a package and sign up"
        description="Pick your Qbank, create your account and pay securely. Your account opens straight away."
    >
        <Head title="Subscribe" />

        <form @submit.prevent="submit" class="flex flex-col gap-6">
            <!-- Package and account side by side (stacked on small screens). -->
            <div class="grid gap-6 md:grid-cols-2 md:gap-8">
                <fieldset class="grid content-start gap-3">
                    <legend class="mb-3 flex items-center gap-2 text-sm font-semibold">
                        <span class="inline-flex size-6 items-center justify-center rounded-full bg-primary text-xs font-bold text-primary-foreground"
                            >1</span
                        >Choose your package<span class="mw-required-mark" aria-hidden="true">*</span>
                    </legend>
                    <p v-if="!props.plans.length" class="rounded-lg border border-dashed p-4 text-center text-sm text-muted-foreground">
                        No Qbank is on sale at the moment. Please check back later.
                    </p>
                    <label
                        v-for="plan in props.plans"
                        :key="plan.id"
                        class="flex cursor-pointer items-start gap-3 rounded-xl border p-3.5 transition"
                        :class="form.plan_id === plan.id ? 'mw-gradient-border shadow-md' : 'hover:-translate-y-0.5 hover:shadow-sm'"
                    >
                        <input v-model="form.plan_id" type="radio" name="plan" :value="plan.id" class="mt-1 accent-primary" required />
                        <span class="min-w-0 flex-1">
                            <span class="flex items-baseline justify-between gap-2">
                                <span class="font-semibold">{{ plan.qbank }}</span>
                                <strong class="inline-flex items-center text-primary"
                                    ><PoundSterling v-if="plan.price.startsWith('£')" class="size-4" aria-label="GBP" />{{
                                        plan.price.replace(/^£/, '')
                                    }}</strong
                                >
                            </span>
                            <span class="mt-0.5 block text-sm text-muted-foreground">{{ plan.duration }} · For {{ plan.exams.join(', ') }}</span>
                        </span>
                        <CheckCircle2 v-if="form.plan_id === plan.id" class="mt-0.5 size-4 shrink-0 text-primary" aria-hidden="true" />
                    </label>
                    <InputError :message="form.errors.plan_id" />
                    <p v-if="selectedExam" class="text-xs text-muted-foreground">You came from {{ selectedExam.name }}.</p>
                </fieldset>

                <fieldset class="grid content-start gap-4">
                    <legend class="mb-3 flex items-center gap-2 text-sm font-semibold">
                        <span class="inline-flex size-6 items-center justify-center rounded-full bg-primary text-xs font-bold text-primary-foreground"
                            >2</span
                        >Your account
                    </legend>
                    <div class="grid gap-2">
                        <Label for="name">Name</Label>
                        <Input class="h-11" id="name" type="text" required autocomplete="name" v-model="form.name" placeholder="Full name" />
                        <InputError :message="form.errors.name" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="email">Email address</Label>
                        <Input
                            class="h-11"
                            id="email"
                            type="email"
                            required
                            autocomplete="email"
                            v-model="form.email"
                            placeholder="you@example.com"
                        />
                        <InputError :message="form.errors.email" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="password">Password</Label>
                        <Input
                            class="h-11"
                            id="password"
                            type="password"
                            required
                            autocomplete="new-password"
                            v-model="form.password"
                            placeholder="Password"
                        />
                        <InputError :message="form.errors.password" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="password_confirmation">Confirm password</Label>
                        <Input
                            class="h-11"
                            id="password_confirmation"
                            type="password"
                            required
                            autocomplete="new-password"
                            v-model="form.password_confirmation"
                            placeholder="Confirm password"
                        />
                        <InputError :message="form.errors.password_confirmation" />
                    </div>
                </fieldset>
            </div>

            <!-- Payment underneath, full width. -->
            <div v-if="card" class="mw-logo-left mw-logo-soft grid gap-3 rounded-xl border p-4">
                <p class="flex items-center gap-2 text-sm font-semibold">
                    <span class="inline-flex size-6 items-center justify-center rounded-full bg-primary text-xs font-bold text-primary-foreground"
                        >3</span
                    >Pay by card<CreditCard class="size-4 text-primary" aria-hidden="true" />
                </p>
                <p class="text-xs leading-relaxed text-muted-foreground">
                    After you create your account you go to Stripe's secure payment page. Cards from every country Stripe accepts, Pakistan included,
                    work there. Your Qbank opens as soon as the payment is confirmed.
                </p>
                <PaymentMethods />
            </div>
            <fieldset v-else class="grid gap-3 rounded-xl border p-4">
                <legend class="flex items-center gap-2 px-1 text-sm font-semibold">
                    <span class="inline-flex size-6 items-center justify-center rounded-full bg-primary text-xs font-bold text-primary-foreground"
                        >3</span
                    >Pay and enter your payment reference
                </legend>
                <p class="rounded-md bg-muted/60 p-3 text-xs leading-relaxed">{{ paymentInstructions }}</p>
                <div class="grid gap-4 md:grid-cols-2">
                    <div class="grid gap-2">
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
                    </div>
                    <div class="grid gap-2">
                        <Label for="note">Note for the team (optional)</Label>
                        <textarea
                            placeholder="Anything the team should know (optional)"
                            id="note"
                            v-model="form.note"
                            rows="2"
                            maxlength="1000"
                            class="rounded-md border bg-background px-3 py-2 text-sm"
                        />
                    </div>
                </div>
            </fieldset>

            <div class="grid gap-3">
                <Button type="submit" class="h-11 w-full text-base" :disabled="form.processing">
                    <LoaderCircle v-if="form.processing" class="h-4 w-4 animate-spin" />
                    {{ card ? 'Create account and pay by card' : 'Subscribe and create account' }}
                </Button>
                <p v-if="!card" class="text-center text-xs text-muted-foreground">
                    You can log in straight away. Your Qbank opens once the team confirms the payment.
                </p>
                <p class="text-center text-sm text-muted-foreground">
                    Already have an account?
                    <TextLink :href="route('login')" class="underline underline-offset-4">Log in</TextLink>
                </p>
            </div>
        </form>
    </AuthBase>
</template>
