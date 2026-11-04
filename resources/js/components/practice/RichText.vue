<script setup lang="ts">
/**
 * Shows MCQ Markdown that the server rendered with raw HTML stripped and
 * unsafe links removed (App\Support\QuestionContent). Falls back to the
 * plain text when no HTML is available, for example in older snapshots.
 */
defineProps<{ html?: string | null; text?: string | null }>();
</script>
<template>
    <!-- eslint-disable-next-line vue/no-v-html -->
    <div v-if="html" class="mcq-content" v-html="html" />
    <p v-else-if="text" class="whitespace-pre-wrap leading-relaxed">{{ text }}</p>
</template>
