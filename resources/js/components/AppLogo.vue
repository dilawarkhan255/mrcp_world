<script setup lang="ts">
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import { APP_NAME } from '@/lib/brand';
</script>
<template>
    <div class="flex aspect-square size-8 shrink-0 items-center justify-center rounded-lg bg-primary text-primary-foreground">
        <AppLogoIcon class="size-5" />
    </div>
    <span class="truncate font-heading text-sm font-semibold group-data-[collapsible=icon]:hidden">{{ APP_NAME }}</span>
</template>
