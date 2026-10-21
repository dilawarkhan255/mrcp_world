<script setup lang="ts">
import Page from '@/pages/Legal/Page.vue';
defineProps<{ appName: string }>();
</script>
<template>
    <Page
        :app-name="appName"
        title="Terms of use"
        intro="The terms that apply when you use MRCP World, including the accounts, the question bank and the mock exams."
    />
</template>
