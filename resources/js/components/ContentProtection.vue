<script setup lang="ts">
import { useContentProtection } from '@/composables/useContentProtection';
import type { SharedData } from '@/types';
import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
/**
 * Copy protection for every page (mounted once in app.ts) and, for a signed-in
 * student, a faint watermark with their email and the date, so a screenshot
 * that is shared can be traced. Guests are protected too. Content staff are not
 * protected, because they need to copy content while editing.
 */
const page = usePage<SharedData>();
const protection = computed(() => page.props.protection ?? null);
const enabled = computed(() => !!protection.value?.enabled);
useContentProtection(enabled);
const watermark = computed(() => {
    const label = protection.value?.watermark;
    if (!enabled.value || !label) return null;
    const text = label.replace(/[&<>"']/g, (char) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[char] ?? char);
    const svg =
        `<svg xmlns="http://www.w3.org/2000/svg" width="360" height="200">` +
        `<text x="20" y="120" transform="rotate(-24 180 100)" font-family="sans-serif" font-size="14" fill="#64748b">${text}</text></svg>`;
    return `url("data:image/svg+xml;charset=utf-8,${encodeURIComponent(svg)}")`;
});
</script>
<template>
    <div
        v-if="watermark"
        aria-hidden="true"
        class="pointer-events-none fixed inset-0 z-[60] select-none opacity-[0.12] print:hidden"
        :style="{ backgroundImage: watermark, backgroundRepeat: 'repeat' }"
    />
</template>
