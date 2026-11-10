<script setup lang="ts">
import LegalDocument from '@/components/legal/LegalDocument.vue';
defineProps<{ appName: string; summary: string; sections: { title: string; body: string }[]; updated: string }>();
</script>
<template>
    <LegalDocument
        :app-name="appName"
        title="Cookie settings"
        :description="`The cookies ${appName} uses.`"
        :summary="summary"
        :sections="sections"
        :updated="updated"
    />
</template>
