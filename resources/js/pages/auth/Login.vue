<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AuthBase from '@/layouts/AuthLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { LoaderCircle, Lock, Mail, ShoppingCart } from 'lucide-vue-next';

const props = defineProps<{
    status?: string;
    canResetPassword: boolean;
    /** The visitor chose a plan: the subscription form opens after logging in. */
    subscribing?: boolean;
    /** Login link of one exam: the student lands in that exam. */
    exam?: { name: string; slug: string } | null;
}>();

const form = useForm({
    email: '',
    password: '',
    remember: false,
    exam: props.exam?.slug ?? '',
});

const submit = () => {
    form.post(route('login'), {
        onFinish: () => form.reset('password'),
    });
};
</script>

<template>
    <AuthBase
        :title="exam ? 'Log in to ' + exam.name : 'Log in to your account'"
        :description="exam ? 'Your ' + exam.name + ' question bank opens after you log in.' : 'Welcome back. Log in to continue your revision.'"
    >
        <Head title="Log in" />

        <div v-if="subscribing" role="status" class="mw-logo-left mw-logo-soft mb-4 rounded-lg border p-3 text-sm">
            Log in to renew or add a Qbank. New here? Your account is created when you subscribe.
        </div>

        <div
            v-if="status"
            role="status"
            class="mb-4 rounded-lg border border-green-600/30 bg-green-600/10 p-3 text-sm font-medium text-green-700 dark:text-green-400"
        >
            {{ status }}
        </div>

        <form @submit.prevent="submit" class="flex flex-col gap-6">
            <div class="grid gap-6">
                <div class="grid gap-2">
                    <Label for="email">Email address</Label>
                    <div class="relative">
                        <Mail class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-primary" aria-hidden="true" />
                        <Input
                            id="email"
                            type="email"
                            required
                            autofocus
                            :tabindex="1"
                            autocomplete="email"
                            v-model="form.email"
                            placeholder="you@example.com"
                            class="h-11 pl-10"
                        />
                    </div>
                    <InputError :message="form.errors.email" />
                </div>

                <div class="grid gap-2">
                    <div class="flex items-center justify-between">
                        <Label for="password">Password</Label>
                        <TextLink v-if="canResetPassword" :href="route('password.request')" class="text-sm" :tabindex="5">
                            Forgot password?
                        </TextLink>
                    </div>
                    <div class="relative">
                        <Lock class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-primary" aria-hidden="true" />
                        <Input
                            id="password"
                            type="password"
                            required
                            :tabindex="2"
                            autocomplete="current-password"
                            v-model="form.password"
                            placeholder="Your password"
                            class="h-11 pl-10"
                        />
                    </div>
                    <InputError :message="form.errors.password" />
                </div>

                <div class="flex items-center justify-between" :tabindex="3">
                    <Label for="remember" class="flex items-center space-x-3">
                        <Checkbox id="remember" v-model:checked="form.remember" :tabindex="4" />
                        <span>Remember me</span>
                    </Label>
                </div>

                <Button type="submit" class="mt-2 h-11 w-full text-base" :tabindex="4" :disabled="form.processing">
                    <LoaderCircle v-if="form.processing" class="h-4 w-4 animate-spin" />
                    Log in
                </Button>
            </div>

            <div class="relative text-center text-xs uppercase tracking-widest text-muted-foreground">
                <span class="absolute inset-x-0 top-1/2 h-px bg-border" aria-hidden="true" />
                <span class="relative bg-card px-3">New student?</span>
            </div>
            <Link
                :href="route('register')"
                class="mw-gradient-border flex h-11 items-center justify-center gap-2 rounded-md text-sm font-semibold"
                :tabindex="5"
            >
                <ShoppingCart class="size-4 text-primary" aria-hidden="true" /><span class="text-primary">Choose a package and sign up</span>
            </Link>
            <p class="-mt-2 text-center text-xs text-muted-foreground">
                Buy a 3 or 5-month package first. Your account opens as soon as the payment is confirmed.
            </p>
        </form>
    </AuthBase>
</template>
