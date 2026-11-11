<script setup lang="ts">
import AuthLayout from '@/layouts/auth/AuthSimpleLayout.vue';

defineProps<{
    title?: string;
    description?: string;
    /** A wider form card (the sign-up page has two columns). */
    wide?: boolean;
}>();
</script>

<template>
    <AuthLayout :title="title" :description="description" :wide="wide">
        <slot />
    </AuthLayout>
</template>
