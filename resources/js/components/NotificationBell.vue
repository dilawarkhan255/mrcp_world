<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { DropdownMenu, DropdownMenuContent, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import type { SharedData } from '@/types';
import { router, usePage } from '@inertiajs/vue3';
import { Bell, BellRing, CalendarClock, CheckCircle2, Loader2, MessagesSquare } from 'lucide-vue-next';
import { computed, onBeforeUnmount, onMounted, ref, type Component } from 'vue';

/**
 * The bell of the student app: the unread count comes with every page and is
 * refreshed every minute; the list is loaded when the bell is opened.
 */
interface NotificationItem {
    id: string;
    title: string;
    body: string;
    url: string | null;
    icon: string;
    kind: string;
    read: boolean;
    created_at: string | null;
}

const ICONS: Record<string, Component> = { CheckCircle2, MessagesSquare, CalendarClock, Bell };
const page = usePage<SharedData>();
const unread = computed(() => page.props.notifications?.unread ?? 0);
const items = ref<NotificationItem[]>([]);
const loading = ref(false);
let poll: ReturnType<typeof setInterval> | null = null;

async function load() {
    loading.value = true;
    try {
        const response = await fetch('/notifications', { headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' } });
        if (response.ok) items.value = ((await response.json()) as { items: NotificationItem[] }).items;
    } finally {
        loading.value = false;
    }
}
function openItem(item: NotificationItem) {
    router.post('/notifications/' + item.id + '/read', {}, { preserveScroll: true });
}
function readAll() {
    router.post('/notifications/read-all', {}, { preserveScroll: true, onSuccess: () => items.value.forEach((item) => (item.read = true)) });
}
function ago(value: string | null): string {
    if (!value) return '';
    const minutes = Math.round((Date.now() - new Date(value).getTime()) / 60000);
    if (minutes < 1) return 'just now';
    if (minutes < 60) return minutes + ' min ago';
    if (minutes < 1440) return Math.round(minutes / 60) + ' h ago';
    return Math.round(minutes / 1440) + ' d ago';
}
function tone(kind: string): string {
    return kind === 'success' ? 'text-green-600 dark:text-green-400' : kind === 'warning' ? 'text-amber-600 dark:text-amber-400' : 'text-primary';
}
onMounted(() => {
    poll = setInterval(() => {
        if (document.visibilityState === 'visible') router.reload({ only: ['notifications'] });
    }, 60000);
});
onBeforeUnmount(() => {
    if (poll) clearInterval(poll);
});
</script>

<template>
    <DropdownMenu @update:open="(open: boolean) => open && load()">
        <DropdownMenuTrigger as-child>
            <Button variant="outline" size="icon" class="relative" :aria-label="unread ? unread + ' unread notifications' : 'Notifications'">
                <component :is="unread ? BellRing : Bell" class="size-4" />
                <span
                    v-if="unread"
                    class="absolute -right-1 -top-1 flex min-w-[1.1rem] items-center justify-center rounded-full bg-destructive px-1 text-[10px] font-bold leading-[1.1rem] text-white"
                    >{{ unread > 9 ? '9+' : unread }}</span
                >
            </Button>
        </DropdownMenuTrigger>
        <DropdownMenuContent align="end" class="w-[min(92vw,22rem)] p-0">
            <div class="flex items-center justify-between border-b px-4 py-3">
                <p class="text-sm font-semibold">Notifications</p>
                <button v-if="unread" type="button" class="text-xs font-medium text-primary hover:underline" @click="readAll">
                    Mark all as read
                </button>
            </div>
            <div class="max-h-96 overflow-y-auto">
                <p v-if="loading && !items.length" class="flex items-center justify-center gap-2 px-4 py-8 text-sm text-muted-foreground">
                    <Loader2 class="size-4 animate-spin" aria-hidden="true" />Loading…
                </p>
                <p v-else-if="!items.length" class="px-4 py-8 text-center text-sm text-muted-foreground">You are all caught up.</p>
                <ul v-else>
                    <li v-for="item in items" :key="item.id" class="border-b last:border-b-0">
                        <button
                            type="button"
                            class="flex w-full gap-3 px-4 py-3 text-left hover:bg-accent focus-visible:bg-accent focus-visible:outline-none"
                            :class="item.read ? 'opacity-70' : ''"
                            @click="openItem(item)"
                        >
                            <component :is="ICONS[item.icon] ?? Bell" class="mt-0.5 size-4 shrink-0" :class="tone(item.kind)" aria-hidden="true" />
                            <span class="min-w-0 flex-1">
                                <span class="flex items-start justify-between gap-2">
                                    <span class="text-sm font-medium">{{ item.title }}</span>
                                    <span v-if="!item.read" class="mt-1.5 size-2 shrink-0 rounded-full bg-primary" aria-label="Unread" />
                                </span>
                                <span class="mt-0.5 line-clamp-2 block text-xs text-muted-foreground">{{ item.body }}</span>
                                <span class="mt-1 block text-[11px] text-muted-foreground">{{ ago(item.created_at) }}</span>
                            </span>
                        </button>
                    </li>
                </ul>
            </div>
        </DropdownMenuContent>
    </DropdownMenu>
</template>
