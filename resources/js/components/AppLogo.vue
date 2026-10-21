<script setup lang="ts">
import { APP_NAME } from '@/lib/brand';
</script>
<template>
    <div class="flex aspect-square size-8 shrink-0 items-center justify-center overflow-hidden rounded-lg bg-white">
        <img src="/brand/mrcp-world-mark.png" alt="" class="size-full object-contain" width="32" height="32" />
    </div>
    <span class="truncate font-heading text-sm font-semibold group-data-[collapsible=icon]:hidden">{{ APP_NAME }}</span>
</template>
