<script setup lang="ts">
import { landingCopy } from '@/content/landing';
import { accountLinks, legalLinks, sectionLinks } from '@/content/public-nav';
import type { SharedData } from '@/types';
import { usePage } from '@inertiajs/vue3';
import { MessageCircle, Smartphone } from 'lucide-vue-next';
import { computed } from 'vue';
const props = defineProps<{ appName: string }>();
const page = usePage<SharedData>();
/** Community links come from config/services.php; a missing link is hidden. */
const links = computed(() => page.props.links ?? { whatsapp: null, play_store: null });
const year = new Date().getFullYear();
const columns = [
    { title: 'Explore', links: sectionLinks },
    { title: 'Account', links: accountLinks },
    { title: 'Legal', links: legalLinks },
];
</script>
<template>
    <footer class="relative border-t bg-white text-ink">
        <span class="brand-rainbow absolute inset-x-0 top-0 h-1" aria-hidden="true" />
        <div class="mx-auto grid w-full max-w-[1280px] gap-10 px-5 py-12 md:grid-cols-[1.4fr_1fr_1fr_1fr] md:px-8">
            <div class="space-y-4">
                <a href="/" class="inline-flex" :aria-label="props.appName + ', home page'">
                    <img src="/brand/mrcp-world-logo.trim.png" :alt="props.appName" class="h-14 w-auto object-contain" height="56" loading="lazy" />
                </a>
                <p class="max-w-xs text-sm text-muted-soft">
                    MCQ banks, past paper MCQs and timed mock exams for MRCP Part 1, MRCP Part 2, IMM and FCPS Part 2.
                </p>
                <div v-if="links.whatsapp || links.play_store" class="flex flex-wrap gap-3">
                    <a
                        v-if="links.whatsapp"
                        :href="links.whatsapp"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="inline-flex min-h-[44px] items-center gap-2 rounded-control border border-border px-4 text-sm font-semibold text-ink transition hover:border-amber hover:text-[#d9620b]"
                        ><MessageCircle class="size-4" aria-hidden="true" />Join our WhatsApp group</a
                    >
                    <a
                        v-if="links.play_store"
                        :href="links.play_store"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="inline-flex min-h-[44px] items-center gap-2 rounded-control border border-border px-4 text-sm font-semibold text-ink transition hover:border-amber hover:text-[#d9620b]"
                        ><Smartphone class="size-4" aria-hidden="true" />Get it on Google Play</a
                    >
                </div>
            </div>
            <nav v-for="column in columns" :key="column.title" :aria-label="column.title">
                <h2 class="text-xs font-semibold uppercase tracking-wider text-muted-soft">{{ column.title }}</h2>
                <ul class="mt-3 space-y-1">
                    <li v-for="link in column.links" :key="link.label">
                        <a
                            :href="link.href"
                            class="inline-flex min-h-[36px] items-center text-sm font-semibold text-muted-strong transition hover:text-[#d9620b]"
                            >{{ link.label }}</a
                        >
                    </li>
                </ul>
            </nav>
        </div>
        <div class="border-t">
            <p class="mx-auto w-full max-w-[1280px] px-5 py-5 text-sm text-muted-soft md:px-8">
                © {{ props.appName }} {{ year }}. {{ landingCopy.footer.rights }}
            </p>
        </div>
    </footer>
</template>
