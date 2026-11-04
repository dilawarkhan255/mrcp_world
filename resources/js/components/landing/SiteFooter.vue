<script setup lang="ts">
import { landingCopy } from '@/content/landing';
import type { SharedData } from '@/types';
import { usePage } from '@inertiajs/vue3';
import { MessageCircle, Smartphone } from 'lucide-vue-next';
import { computed } from 'vue';
const props = defineProps<{ appName: string }>();
const page = usePage<SharedData>();
/** Community links come from config/services.php; a missing link is hidden. */
const links = computed(() => page.props.links ?? { whatsapp: null, play_store: null });
const year = new Date().getFullYear();
</script>
<template>
    <footer class="bg-ink text-white">
        <div class="mx-auto flex w-full max-w-[1280px] flex-col gap-6 px-5 py-10 md:flex-row md:items-center md:justify-between md:px-8">
            <div class="space-y-3">
                <p class="text-sm text-white/70">© {{ props.appName }} {{ year }}. {{ landingCopy.footer.rights }}</p>
                <div v-if="links.whatsapp || links.play_store" class="flex flex-wrap gap-3">
                    <a
                        v-if="links.whatsapp"
                        :href="links.whatsapp"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="inline-flex min-h-[44px] items-center gap-2 rounded-control border border-white/20 px-4 text-sm font-semibold text-white transition hover:border-white/50"
                        ><MessageCircle class="size-4" aria-hidden="true" />Join our WhatsApp group</a
                    >
                    <a
                        v-if="links.play_store"
                        :href="links.play_store"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="inline-flex min-h-[44px] items-center gap-2 rounded-control border border-white/20 px-4 text-sm font-semibold text-white transition hover:border-white/50"
                        ><Smartphone class="size-4" aria-hidden="true" />Get it on Google Play</a
                    >
                </div>
            </div>
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
