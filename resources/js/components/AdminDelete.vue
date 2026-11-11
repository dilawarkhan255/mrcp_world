<script setup lang="ts">
import type { SharedData } from '@/types';
import { router, usePage } from '@inertiajs/vue3';
import { Loader2, Trash2 } from 'lucide-vue-next';
import { computed, ref } from 'vue';

/**
 * A small Delete button that only admins see in the student app (MCQ,
 * comment, chapter, flashcard, atlas picture). Asks first, then sends a
 * DELETE request; the server checks the admin role again.
 */
const props = withDefaults(defineProps<{ url: string; label?: string; confirmText?: string; iconOnly?: boolean }>(), {
    label: 'Delete',
    confirmText: 'Delete this? This cannot be undone.',
    iconOnly: false,
});

const emit = defineEmits<{ deleted: [] }>();
const isAdmin = computed(() => !!usePage<SharedData>().props.auth?.isAdmin);
const busy = ref(false);

function remove() {
    if (busy.value || !window.confirm(props.confirmText)) return;
    busy.value = true;
    router.delete(props.url, { preserveScroll: true, onSuccess: () => emit('deleted'), onFinish: () => (busy.value = false) });
}
</script>

<template>
    <button
        v-if="isAdmin"
        type="button"
        class="inline-flex items-center gap-1.5 rounded-md border border-destructive/40 px-2.5 py-1 text-xs font-semibold text-destructive transition hover:bg-destructive hover:text-white disabled:opacity-50"
        :title="label + ' (admin)'"
        :aria-label="label"
        :disabled="busy"
        @click="remove"
    >
        <Loader2 v-if="busy" class="size-3.5 animate-spin" aria-hidden="true" /><Trash2 v-else class="size-3.5" aria-hidden="true" />
        <span v-if="!iconOnly">{{ label }}</span>
    </button>
</template>
