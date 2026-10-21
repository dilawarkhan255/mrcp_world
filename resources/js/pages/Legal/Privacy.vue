<script setup lang="ts">
import Page from '@/pages/Legal/Page.vue';
defineProps<{ appName: string }>();
</script>
<template>
    <Page
        :app-name="appName"
        title="Privacy notice"
        intro="What we store about you, why we store it, and the choices you have over that information."
    />
</template>
