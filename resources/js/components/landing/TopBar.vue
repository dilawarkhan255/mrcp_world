<script setup lang="ts">
import { sectionLinks } from '@/content/public-nav';
import type { SharedData } from '@/types';
import { usePage } from '@inertiajs/vue3';
import { LayoutGrid, LogIn, Menu, X } from 'lucide-vue-next';
import { computed, ref } from 'vue';

/**
 * The public top bar: logo (home), the section links and the account
 * buttons. There is no inline login form: a student logs in after
 * subscribing, from the Log in page.
 */
defineProps<{ appName: string }>();
const page = usePage<SharedData>();
const signedIn = computed(() => !!page.props.auth?.user);
const open = ref(false);
/** The section links, plus Contact, on every public page. */
const links = [...sectionLinks, { label: 'Contact', href: '/contact' }];
const current = computed(() => (typeof window === 'undefined' ? '' : window.location.pathname + window.location.hash));
const isCurrent = (href: string) => current.value === href || (href === '/contact' && current.value.startsWith('/contact'));
</script>
<template>
    <header class="sticky top-0 z-40 bg-white/95 text-ink shadow-sm backdrop-blur">
        <div class="mx-auto flex w-full max-w-[1280px] items-center gap-6 px-5 py-3 md:px-8">
            <!-- The logo always goes back to the home page. -->
            <a href="/" class="flex shrink-0 items-center" :aria-label="appName + ', home page'">
                <img src="/brand/mrcp-world-logo.trim.png" :alt="appName" class="h-14 w-auto object-contain" height="56" />
            </a>

            <nav aria-label="Primary" class="hidden items-center gap-1 md:flex">
                <a
                    v-for="link in links"
                    :key="link.href"
                    :href="link.href"
                    :aria-current="isCurrent(link.href) ? 'page' : undefined"
                    class="rounded-full px-3.5 py-2 text-sm font-semibold text-muted-strong transition hover:bg-[#fff4e8] hover:text-[#d9620b] aria-[current=page]:bg-light-teal aria-[current=page]:text-dark-teal"
                    >{{ link.label }}</a
                >
            </nav>

            <div class="ml-auto hidden items-center gap-2 sm:flex">
                <template v-if="signedIn">
                    <a
                        href="/dashboard"
                        class="inline-flex h-11 items-center gap-2 rounded-control bg-primary px-5 text-sm font-semibold text-primary-foreground shadow-sm transition focus-visible:ring-2 focus-visible:ring-teal focus-visible:ring-offset-2"
                        ><LayoutGrid class="size-4" aria-hidden="true" />Dashboard</a
                    >
                </template>
                <template v-else>
                    <a
                        href="/login"
                        class="inline-flex h-11 items-center gap-2 rounded-control border border-border bg-white px-5 text-sm font-semibold text-ink transition hover:border-amber hover:text-[#d9620b] focus-visible:ring-2 focus-visible:ring-teal focus-visible:ring-offset-2"
                        ><LogIn class="size-4" aria-hidden="true" />Log in</a
                    >
                    <a
                        href="/#pricing"
                        class="inline-flex h-11 items-center rounded-control bg-primary px-5 text-sm font-semibold text-primary-foreground shadow-sm transition focus-visible:ring-2 focus-visible:ring-teal focus-visible:ring-offset-2"
                        >Subscribe</a
                    >
                </template>
            </div>

            <button
                type="button"
                class="ml-auto inline-flex size-11 items-center justify-center rounded-control border border-border text-ink sm:ml-0 md:hidden"
                :aria-expanded="open"
                aria-controls="public-menu"
                :aria-label="open ? 'Close menu' : 'Open menu'"
                @click="open = !open"
            >
                <X v-if="open" class="size-5" aria-hidden="true" /><Menu v-else class="size-5" aria-hidden="true" />
            </button>
        </div>

        <!-- Small screens: the same links and buttons in a panel. -->
        <nav v-show="open" id="public-menu" aria-label="Menu" class="border-t bg-white px-5 pb-5 pt-2 md:hidden">
            <a
                v-for="link in links"
                :key="link.href"
                :href="link.href"
                class="block rounded-lg px-3 py-3 text-base font-semibold text-ink hover:bg-[#fff4e8] hover:text-[#d9620b]"
                @click="open = false"
                >{{ link.label }}</a
            >
            <div class="mt-3 grid grid-cols-2 gap-3 sm:hidden">
                <a
                    v-if="signedIn"
                    href="/dashboard"
                    class="col-span-2 inline-flex h-11 items-center justify-center rounded-control bg-primary text-sm font-semibold text-primary-foreground"
                    >Dashboard</a
                >
                <template v-else>
                    <a
                        href="/login"
                        class="inline-flex h-11 items-center justify-center rounded-control border border-border text-sm font-semibold text-ink"
                        >Log in</a
                    >
                    <a
                        href="/#pricing"
                        class="inline-flex h-11 items-center justify-center rounded-control bg-primary text-sm font-semibold text-primary-foreground"
                        @click="open = false"
                        >Subscribe</a
                    >
                </template>
            </div>
        </nav>
        <span class="brand-rainbow absolute inset-x-0 bottom-0 h-1" aria-hidden="true" />
    </header>
</template>
