<script setup lang="ts">
import ExamSwitcher from '@/components/ExamSwitcher.vue';
import NavFlyout from '@/components/NavFlyout.vue';
import NavSubTree from '@/components/NavSubTree.vue';
import NavUser from '@/components/NavUser.vue';
import { Collapsible, CollapsibleContent, CollapsibleTrigger } from '@/components/ui/collapsible';
import { DropdownMenu, DropdownMenuContent, DropdownMenuLabel, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
    SidebarRail,
    useSidebar,
} from '@/components/ui/sidebar';
import { useAppearance } from '@/composables/useAppearance';
import { useNavActive } from '@/composables/useNavActive';
import { ACTIVE_CLASS } from '@/lib/nav-classes';
import { navIcon } from '@/lib/nav-icons';
import type { SharedData } from '@/types';
import { Link, usePage } from '@inertiajs/vue3';
import { Lock, MessageCircle, Minus, Moon, Plus, Shield, Smartphone, Sun } from 'lucide-vue-next';
import { computed } from 'vue';

const page = usePage<SharedData>();
const { state, isMobile, setOpenMobile } = useSidebar();
const { isActive, containsActive } = useNavActive();
const { appearance, updateAppearance } = useAppearance();

const navigation = computed(() => page.props.navigation ?? []);
const community = computed(() =>
    [
        { title: 'WhatsApp group', href: page.props.links?.whatsapp ?? null, icon: MessageCircle },
        { title: 'Android app', href: page.props.links?.play_store ?? null, icon: Smartphone },
    ].filter((link): link is { title: string; href: string; icon: typeof MessageCircle } => !!link.href),
);
// Icon-only mode (desktop). On mobile the sheet always shows the full labels.
const collapsed = computed(() => state.value === 'collapsed' && !isMobile.value);
const closeMobile = () => setOpenMobile(false);

// The "system" appearance follows the OS, so the segment shows the theme actually in use.
const isDark = computed(
    () => appearance.value === 'dark' || (appearance.value === 'system' && window.matchMedia('(prefers-color-scheme: dark)').matches),
);
const segmentClass = (selected: boolean) =>
    selected ? 'bg-sidebar-primary text-sidebar-primary-foreground shadow-sm' : 'text-sidebar-foreground hover:bg-sidebar-accent';
</script>

<template>
    <Sidebar collapsible="icon">
        <SidebarHeader class="gap-3 p-3 group-data-[collapsible=icon]:p-2">
            <div
                class="flex items-center justify-center rounded-xl border border-sidebar-border bg-white p-2 shadow-sm group-data-[collapsible=icon]:p-1"
            >
                <!-- Full logo when expanded; the small mark alone when the sidebar is icon-only. -->
                <img
                    src="/brand/mrcp-world-logo-full.png"
                    alt="MRCPWorld MCQ Bank"
                    class="h-16 w-16 object-contain group-data-[collapsible=icon]:hidden"
                />
                <img src="/brand/mrcp-world-mark.png" alt="" class="hidden size-7 object-contain group-data-[collapsible=icon]:block" />
            </div>
            <ExamSwitcher />
        </SidebarHeader>

        <SidebarContent class="p-2">
            <SidebarMenu>
                <template v-for="item in navigation" :key="item.title">
                    <!-- A plain link. -->
                    <SidebarMenuItem v-if="!item.children?.length">
                        <SidebarMenuButton as-child size="lg" :is-active="isActive(item)" :tooltip="item.title" :class="ACTIVE_CLASS">
                            <Link :href="item.href ?? '/dashboard'" :aria-current="isActive(item) ? 'page' : undefined" @click="closeMobile">
                                <component :is="navIcon(item.icon)" v-if="navIcon(item.icon)" />
                                <span class="group-data-[collapsible=icon]:hidden">{{ item.title }}</span>
                                <Lock
                                    v-if="item.locked"
                                    class="ml-auto size-3.5 text-muted-foreground group-data-[collapsible=icon]:hidden"
                                    aria-label="Needs a subscription"
                                />
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>

                    <!-- A group: a dropdown with sub-menus when icon-only, otherwise an expanding list. -->
                    <SidebarMenuItem v-else>
                        <DropdownMenu v-if="collapsed">
                            <DropdownMenuTrigger as-child>
                                <SidebarMenuButton size="lg" :is-active="containsActive(item)" :tooltip="item.title" :class="ACTIVE_CLASS">
                                    <component :is="navIcon(item.icon)" v-if="navIcon(item.icon)" />
                                    <span class="group-data-[collapsible=icon]:hidden">{{ item.title }}</span>
                                </SidebarMenuButton>
                            </DropdownMenuTrigger>
                            <DropdownMenuContent side="right" align="start" class="min-w-56">
                                <DropdownMenuLabel>{{ item.title }}</DropdownMenuLabel>
                                <NavFlyout :nodes="item.children ?? []" />
                            </DropdownMenuContent>
                        </DropdownMenu>

                        <Collapsible v-else v-slot="{ open }" :default-open="containsActive(item)">
                            <CollapsibleTrigger as-child>
                                <SidebarMenuButton size="lg" :is-active="containsActive(item)" :class="ACTIVE_CLASS">
                                    <component :is="navIcon(item.icon)" v-if="navIcon(item.icon)" />
                                    <span class="group-data-[collapsible=icon]:hidden">{{ item.title }}</span>
                                    <Minus v-if="open" class="ml-auto size-4" />
                                    <Plus v-else class="ml-auto size-4" />
                                </SidebarMenuButton>
                            </CollapsibleTrigger>
                            <CollapsibleContent>
                                <NavSubTree :nodes="item.children ?? []" />
                            </CollapsibleContent>
                        </Collapsible>
                    </SidebarMenuItem>
                </template>
            </SidebarMenu>

            <SidebarMenu v-if="community.length" class="mt-3 border-t border-sidebar-border pt-3">
                <SidebarMenuItem v-for="link in community" :key="link.title">
                    <SidebarMenuButton as-child :tooltip="link.title">
                        <a :href="link.href" target="_blank" rel="noopener noreferrer" @click="closeMobile"
                            ><component :is="link.icon" /><span class="group-data-[collapsible=icon]:hidden">{{ link.title }}</span></a
                        >
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>

            <SidebarMenu v-if="page.props.auth.canManageContent" class="mt-3 border-t border-sidebar-border pt-3">
                <SidebarMenuItem>
                    <SidebarMenuButton as-child size="lg" tooltip="Admin panel">
                        <a href="/admin" @click="closeMobile"><Shield /><span class="group-data-[collapsible=icon]:hidden">Admin panel</span></a>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarContent>

        <SidebarFooter class="gap-3 border-t border-sidebar-border p-3 group-data-[collapsible=icon]:p-2">
            <div class="grid gap-1.5 group-data-[collapsible=icon]:gap-1">
                <span class="text-[10px] font-semibold uppercase tracking-wider text-muted-foreground group-data-[collapsible=icon]:hidden"
                    >Appearance</span
                >
                <div
                    role="group"
                    aria-label="Appearance"
                    class="grid grid-cols-2 gap-1 rounded-lg bg-sidebar-accent p-1 group-data-[collapsible=icon]:grid-cols-1 group-data-[collapsible=icon]:p-0.5"
                >
                    <button
                        type="button"
                        :aria-pressed="!isDark"
                        title="Day mode"
                        class="flex items-center justify-center gap-1.5 rounded-md px-2 py-1.5 text-xs font-semibold transition-colors group-data-[collapsible=icon]:px-0"
                        :class="segmentClass(!isDark)"
                        @click="updateAppearance('light')"
                    >
                        <Sun class="size-4" /><span class="group-data-[collapsible=icon]:hidden">Day</span>
                    </button>
                    <button
                        type="button"
                        :aria-pressed="isDark"
                        title="Dark mode"
                        class="flex items-center justify-center gap-1.5 rounded-md px-2 py-1.5 text-xs font-semibold transition-colors group-data-[collapsible=icon]:px-0"
                        :class="segmentClass(isDark)"
                        @click="updateAppearance('dark')"
                    >
                        <Moon class="size-4" /><span class="group-data-[collapsible=icon]:hidden">Dark</span>
                    </button>
                </div>
            </div>
            <NavUser />
        </SidebarFooter>
        <SidebarRail />
    </Sidebar>
</template>
