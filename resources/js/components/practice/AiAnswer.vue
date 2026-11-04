<script setup lang="ts">
import RichText from '@/components/practice/RichText.vue';
import { Button } from '@/components/ui/button';
import { postJson } from '@/lib/post-json';
import { Loader2, Sparkles } from 'lucide-vue-next';
import { ref, watch } from 'vue';

/**
 * A button that asks the AI tutor (Claude) and shows its answer. The server
 * renders the Markdown with raw HTML stripped.
 */
const props = defineProps<{ url: string; label: string; title: string }>();
const html = ref<string | null>(null);
const error = ref<string | null>(null);
const remaining = ref<number | null>(null);
const loading = ref(false);

watch(
    () => props.url,
    () => {
        html.value = null;
        error.value = null;
    },
);

async function ask() {
    loading.value = true;
    error.value = null;
    try {
        const { ok, status, data } = await postJson<{ html?: string; error?: string; remaining?: number }>(props.url);
        remaining.value = data.remaining ?? null;
        if (ok && data.html) {
            html.value = data.html;
        } else {
            error.value = data.error ?? (status === 429 ? 'Too many requests. Please wait a minute.' : 'The AI tutor is not available right now.');
        }
    } catch {
        error.value = 'The AI tutor could not be reached. Please try again.';
    } finally {
        loading.value = false;
    }
}
</script>

<template>
    <section class="relative overflow-hidden rounded-xl border bg-card p-5">
        <span class="brand-rainbow absolute inset-x-0 top-0 h-0.5" aria-hidden="true" />
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h3 class="flex items-center gap-2 text-sm font-semibold"><Sparkles class="size-4 text-primary" aria-hidden="true" />{{ title }}</h3>
            <Button v-if="!html" size="sm" :disabled="loading" @click="ask">
                <Loader2 v-if="loading" class="size-4 animate-spin" aria-hidden="true" /><Sparkles v-else class="size-4" aria-hidden="true" />{{
                    loading ? 'Thinking…' : label
                }}
            </Button>
        </div>
        <p v-if="error" role="alert" class="mt-3 text-sm text-destructive">{{ error }}</p>
        <div v-if="html" class="mt-3 space-y-3" aria-live="polite">
            <RichText :html="html" class="text-sm" />
            <p class="text-[11px] text-muted-foreground">
                Written by AI (Claude). Check important facts against the explanation and current NICE guidance.
                <template v-if="remaining !== null"> {{ remaining }} AI requests left today.</template>
            </p>
        </div>
    </section>
</template>
