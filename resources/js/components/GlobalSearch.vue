<script setup lang="ts">
import { navIcon } from '@/lib/nav-icons';
import { Link, router } from '@inertiajs/vue3';
import { Loader2, Lock, Search, X } from 'lucide-vue-next';
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';

/**
 * The search box of the top bar: a few results per kind while typing, Enter
 * opens the full MCQ search. Ctrl+K (or Cmd+K) focuses it.
 */
interface SearchItem {
    title: string;
    href: string;
    excerpt?: string;
    subject?: string | null;
}
interface SearchGroup {
    label: string;
    icon: string;
    items: SearchItem[];
}

const term = ref('');
const groups = ref<SearchGroup[]>([]);
const locked = ref(false);
const searchUrl = ref('');
const loading = ref(false);
const open = ref(false);
const mobileOpen = ref(false);
const input = ref<HTMLInputElement | null>(null);
const root = ref<HTMLElement | null>(null);
let timer: ReturnType<typeof setTimeout> | null = null;
let controller: AbortController | null = null;

const hasResults = computed(() => groups.value.some((group) => group.items.length));

async function load(value: string) {
    controller?.abort();
    controller = new AbortController();
    loading.value = true;
    try {
        const response = await fetch('/search/quick?q=' + encodeURIComponent(value), {
            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            signal: controller.signal,
        });
        if (!response.ok) throw new Error(String(response.status));
        const data = (await response.json()) as { groups: SearchGroup[]; locked: boolean; searchUrl: string };
        groups.value = data.groups;
        locked.value = data.locked;
        searchUrl.value = data.searchUrl;
        open.value = true;
    } catch (error) {
        if ((error as Error).name !== 'AbortError') groups.value = [];
    } finally {
        loading.value = false;
    }
}

watch(term, (value) => {
    if (timer) clearTimeout(timer);
    const trimmed = value.trim();
    if (trimmed.length < 2) {
        groups.value = [];
        open.value = false;
        return;
    }
    timer = setTimeout(() => load(trimmed), 250);
});

function submit() {
    const trimmed = term.value.trim();
    if (trimmed.length < 2) return;
    close();
    router.visit('/questions/search?q=' + encodeURIComponent(trimmed));
}
function close() {
    open.value = false;
    mobileOpen.value = false;
}
function clear() {
    term.value = '';
    input.value?.focus();
}
function onKey(event: KeyboardEvent) {
    if ((event.ctrlKey || event.metaKey) && event.key.toLowerCase() === 'k') {
        event.preventDefault();
        mobileOpen.value = true;
        requestAnimationFrame(() => input.value?.focus());
    }
    if (event.key === 'Escape') close();
}
function onClickOutside(event: MouseEvent) {
    if (root.value && !root.value.contains(event.target as Node)) open.value = false;
}
onMounted(() => {
    document.addEventListener('keydown', onKey);
    document.addEventListener('mousedown', onClickOutside);
});
onBeforeUnmount(() => {
    document.removeEventListener('keydown', onKey);
    document.removeEventListener('mousedown', onClickOutside);
    if (timer) clearTimeout(timer);
    controller?.abort();
});
</script>

<template>
    <div ref="root" class="relative">
        <button
            type="button"
            class="inline-flex size-9 items-center justify-center rounded-md border bg-background text-muted-foreground hover:text-foreground md:hidden"
            aria-label="Search"
            @click="
                mobileOpen = !mobileOpen;
                $nextTick(() => input?.focus());
            "
        >
            <Search class="size-4" />
        </button>
        <form
            role="search"
            class="absolute right-0 top-11 z-50 w-[min(92vw,28rem)] md:static md:block md:w-72 lg:w-96"
            :class="mobileOpen ? 'block' : 'hidden'"
            @submit.prevent="submit"
        >
            <label class="relative block">
                <span class="sr-only">Search MCQs, subjects, the textbook and the Image Atlas</span>
                <Search class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" aria-hidden="true" />
                <input
                    ref="input"
                    v-model="term"
                    type="search"
                    maxlength="100"
                    autocomplete="off"
                    placeholder="Search MCQs, subjects, textbook…"
                    class="h-9 w-full rounded-lg border bg-muted/50 pl-9 pr-16 text-sm shadow-sm placeholder:text-muted-foreground focus-visible:bg-background focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                    :aria-expanded="open"
                    aria-controls="global-search-results"
                    @focus="open = hasResults"
                />
                <Loader2
                    v-if="loading"
                    class="absolute right-9 top-1/2 size-4 -translate-y-1/2 animate-spin text-muted-foreground"
                    aria-hidden="true"
                />
                <button
                    v-if="term"
                    type="button"
                    class="absolute right-2 top-1/2 -translate-y-1/2 rounded p-1 text-muted-foreground hover:text-foreground"
                    aria-label="Clear search"
                    @click="clear"
                >
                    <X class="size-3.5" />
                </button>
                <kbd
                    v-else
                    class="pointer-events-none absolute right-2 top-1/2 hidden -translate-y-1/2 rounded border bg-background px-1.5 text-[10px] font-medium text-muted-foreground lg:inline"
                    >Ctrl K</kbd
                >
            </label>

            <div
                v-if="open"
                id="global-search-results"
                class="absolute left-0 right-0 top-11 max-h-[70vh] overflow-y-auto rounded-xl border bg-popover p-2 text-popover-foreground shadow-lg"
            >
                <p v-if="locked" class="mb-2 flex items-center gap-2 rounded-lg bg-muted/60 px-3 py-2 text-xs text-muted-foreground">
                    <Lock class="size-3.5" aria-hidden="true" />MCQs, the textbook and the Image Atlas are searched once you subscribe.
                </p>
                <p v-if="!hasResults" class="px-3 py-4 text-center text-sm text-muted-foreground">No matches for “{{ term }}”.</p>
                <section v-for="group in groups" :key="group.label" class="py-1">
                    <h3 class="flex items-center gap-1.5 px-3 py-1 text-[10px] font-semibold uppercase tracking-wider text-muted-foreground">
                        <component :is="navIcon(group.icon)" v-if="navIcon(group.icon)" class="size-3" aria-hidden="true" />{{ group.label }}
                    </h3>
                    <ul>
                        <li v-for="item in group.items" :key="item.href + item.title">
                            <Link
                                :href="item.href"
                                class="block rounded-lg px-3 py-2 text-sm hover:bg-accent focus-visible:bg-accent focus-visible:outline-none"
                                @click="close"
                            >
                                <span class="font-medium">{{ item.title }}</span>
                                <span v-if="item.subject" class="ml-2 text-xs text-muted-foreground">{{ item.subject }}</span>
                                <span v-if="item.excerpt" class="mt-0.5 line-clamp-1 block text-xs text-muted-foreground">{{ item.excerpt }}</span>
                            </Link>
                        </li>
                    </ul>
                </section>
                <button
                    v-if="!locked && term.trim().length >= 2"
                    type="submit"
                    class="mt-1 w-full rounded-lg border-t px-3 py-2 text-left text-sm font-medium text-primary hover:bg-accent"
                >
                    Search all MCQs for “{{ term.trim() }}” ↵
                </button>
            </div>
        </form>
    </div>
</template>
