<script setup lang="ts">
import { landingCopy } from '@/content/landing';
const props = defineProps<{ appName: string }>();
const year = new Date().getFullYear();
</script>
<template>
    <footer class="bg-ink text-white">
        <div class="mx-auto flex w-full max-w-[1280px] flex-col gap-6 px-5 py-10 md:flex-row md:items-center md:justify-between md:px-8">
            <p class="text-sm text-white/70">© {{ props.appName }} {{ year }}. {{ landingCopy.footer.rights }}</p>
            <nav aria-label="Footer" class="flex flex-wrap items-center gap-x-6 gap-y-3">
                <a href="/terms" class="inline-flex min-h-[44px] items-center text-sm font-semibold text-white/80 transition hover:text-white">{{
                    landingCopy.footer.terms
                }}</a>
                <a href="/privacy" class="inline-flex min-h-[44px] items-center text-sm font-semibold text-white/80 transition hover:text-white">{{
                    landingCopy.footer.privacy
                }}</a>
                <a href="/contact" class="inline-flex min-h-[44px] items-center text-sm font-semibold text-white/80 transition hover:text-white">{{
                    landingCopy.footer.contact
                }}</a>
                <a
                    href="/cookie-settings"
                    class="inline-flex min-h-[44px] items-center text-sm font-semibold text-white/80 transition hover:text-white"
                    >{{ landingCopy.footer.cookies }}</a
                >
            </nav>
        </div>
    </footer>
</template>
