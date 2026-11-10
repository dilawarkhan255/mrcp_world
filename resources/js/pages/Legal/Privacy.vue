<script setup lang="ts">
import LegalDocument from '@/components/legal/LegalDocument.vue';
defineProps<{ appName: string; summary: string; sections: { title: string; body: string }[]; updated: string }>();
</script>
<template>
    <LegalDocument
        :app-name="appName"
        title="Privacy notice"
        :description="`How ${appName} stores and uses your data.`"
        :summary="summary"
        :sections="sections"
        :updated="updated"
    />
</template>
