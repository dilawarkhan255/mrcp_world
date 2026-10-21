<script setup lang="ts">
import { landingCopy } from '@/content/landing';
import { useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
defineProps<{ appName: string }>();
const form = useForm({ email: '', password: '' });
const showErrors = ref(false);
const errors = computed(() => [form.errors.email, form.errors.password].filter((message): message is string => Boolean(message)));
function submit() {
    showErrors.value = true;
    form.post('/login', {
        onError: () => {
            showErrors.value = true;
        },
    });
}
</script>
<template>
    <header class="bg-ink text-white">
        <div class="mx-auto flex w-full max-w-[1280px] flex-wrap items-center gap-x-8 gap-y-4 px-5 py-4 md:px-8">
            <a href="#top" class="flex items-center gap-3">
                <span class="flex size-10 items-center justify-center overflow-hidden rounded-xl bg-white p-1">
                    <img src="/brand/mrcp-world-mark.png" alt="" class="size-full object-contain" width="40" height="40" />
                </span>
                <span class="font-heading text-lg font-semibold tracking-tight">{{ appName }}</span>
            </a>
            <nav aria-label="Primary" class="order-3 flex w-full items-center gap-6 sm:order-none sm:w-auto">
                <a href="#exams" class="rounded-lg px-1 py-2 text-sm font-semibold text-white/80 transition hover:text-white">{{
                    landingCopy.topBar.exams
                }}</a>
                <a href="#features" class="rounded-lg px-1 py-2 text-sm font-semibold text-white/80 transition hover:text-white">{{
                    landingCopy.topBar.features
                }}</a>
                <a href="#pricing" class="rounded-lg px-1 py-2 text-sm font-semibold text-white/80 transition hover:text-white">{{
                    landingCopy.topBar.pricing
                }}</a>
            </nav>
            <div class="ml-auto flex items-center gap-3">
                <form class="relative hidden items-center gap-2 sm:flex" novalidate @submit.prevent="submit">
                    <div class="relative">
                        <label for="landing-email" class="sr-only">{{ landingCopy.topBar.email }}</label>
                        <input
                            id="landing-email"
                            v-model="form.email"
                            type="email"
                            name="email"
                            autocomplete="email"
                            :aria-invalid="Boolean(form.errors.email)"
                            :aria-describedby="errors.length ? 'landing-login-errors' : undefined"
                            placeholder="Email"
                            class="h-11 w-44 rounded-control border border-white/20 bg-white/10 px-3 text-sm text-white placeholder:text-white/60 focus:border-white/40 focus:outline-none focus-visible:ring-2 focus-visible:ring-white/70"
                        />
                    </div>
                    <div class="relative">
                        <label for="landing-password" class="sr-only">{{ landingCopy.topBar.password }}</label>
                        <input
                            id="landing-password"
                            v-model="form.password"
                            type="password"
                            name="password"
                            autocomplete="current-password"
                            :aria-invalid="Boolean(form.errors.password)"
                            :aria-describedby="errors.length ? 'landing-login-errors' : undefined"
                            placeholder="Password"
                            class="h-11 w-40 rounded-control border border-white/20 bg-white/10 px-3 text-sm text-white placeholder:text-white/60 focus:border-white/40 focus:outline-none focus-visible:ring-2 focus-visible:ring-white/70"
                        />
                    </div>
                    <button
                        type="submit"
                        :disabled="form.processing"
                        class="h-11 rounded-control bg-teal px-5 text-sm font-semibold text-white transition hover:bg-dark-teal focus-visible:ring-2 focus-visible:ring-white/70 focus-visible:ring-offset-2 focus-visible:ring-offset-ink disabled:opacity-60"
                    >
                        {{ form.processing ? '…' : landingCopy.topBar.logIn }}
                    </button>
                    <div
                        v-if="showErrors && errors.length"
                        id="landing-login-errors"
                        role="alert"
                        class="absolute right-0 top-full z-20 mt-2 w-64 rounded-lg border border-red-300 bg-white p-3 text-left text-xs font-medium text-red-700 shadow-lg"
                    >
                        <p v-for="message in errors" :key="message">{{ message }}</p>
                    </div>
                </form>
                <a
                    href="/login"
                    class="inline-flex h-11 items-center rounded-control bg-teal px-5 text-sm font-semibold text-white transition hover:bg-dark-teal focus-visible:ring-2 focus-visible:ring-white/70 sm:hidden"
                    >{{ landingCopy.topBar.logIn }}</a
                >
            </div>
        </div>
    </header>
</template>
