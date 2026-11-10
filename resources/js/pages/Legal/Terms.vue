<script setup lang="ts">
import LegalDocument from '@/components/legal/LegalDocument.vue';
defineProps<{ appName: string; summary: string; sections: { title: string; body: string }[]; updated: string }>();
</script>
<template>
    <LegalDocument
        :app-name="appName"
        title="Terms of use"
        :description="`The terms that apply when you use ${appName}.`"
        :summary="summary"
        :sections="sections"
        :updated="updated"
    />
</template>
