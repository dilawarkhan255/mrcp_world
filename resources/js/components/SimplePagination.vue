<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
defineProps<{ previous: string | null; next: string | null; page: number }>();
</script>
<template>
    <nav aria-label="Pagination" class="flex items-center justify-between gap-4 border-t pt-4 text-sm">
        <Link v-if="previous" :href="previous" class="mw-gradient-border rounded-lg px-4 py-2"><span class="text-primary">Previous</span></Link
        ><span v-else class="text-muted-foreground">Previous</span>
        <span>Page {{ page }}</span>
        <Link v-if="next" :href="next" class="mw-gradient-border rounded-lg px-4 py-2"><span class="text-primary">Next</span></Link
        ><span v-else class="text-muted-foreground">Next</span>
    </nav>
</template>
