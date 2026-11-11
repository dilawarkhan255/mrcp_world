<script setup lang="ts">
import AiQuickAsk from '@/components/practice/AiQuickAsk.vue';
import { computed } from 'vue';

/**
 * "Ask AI" beside every MCQ (practice, past papers, review, bookmarks, search
 * and mocks). Before the answer is revealed, the server gives Claude only the
 * stem and options, never the answer key. In an open mock it is locked.
 */
const props = defineProps<{ sessionId: number; position: number; answered: boolean; locked?: string | null }>();

const suggestions = computed(() =>
    props.answered
        ? ['Explain this question', 'Why is my choice wrong?', 'Give me a way to remember this']
        : ['Explain the key clue', 'Explain each option', 'What is this testing?'],
);
</script>

<template>
    <AiQuickAsk
        title="Ask AI about this MCQ"
        :url="'/questions/sessions/' + sessionId + '/ask/' + position"
        :suggestions="suggestions"
        :reset-key="position"
        :locked="locked"
        :hint="answered ? null : 'The AI works it out itself; it is not given the answer key.'"
    />
</template>
