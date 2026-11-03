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
import { Minus, Moon, Plus, Shield, Sun } from 'lucide-vue-next';
import { computed } from 'vue';

const page = usePage<SharedData>();
const { state, isMobile, setOpenMobile } = useSidebar();
const { isActive, containsActive } = useNavActive();
const { appearance, updateAppearance } = useAppearance();

const navigation = computed(() => page.props.navigation ?? []);
// Icon-only mode (desktop). On mobile the sheet always shows the full labels.
const collapsed = computed(() => state.value === 'collapsed' && !isMobile.value);
const closeMobile = () => setOpenMobile(false);

// The "system" appearance follows the OS, so the segment shows the theme actually in use.
const isDark = computed(
    () => appearance.value === 'dark' || (appearance.value === 'system' && window.matchMedia('(prefers-color-scheme: dark)').matches),
);
const segmentClass = (selected: boolean) =>
    selected ? 'bg-sidebar-primary text-sidebar-primary-foreground shadow-sm' : 'text-sidebar-foreground hover:bg-white/10';
</script>

<template>
    <Sidebar collapsible="icon">
        <SidebarHeader class="gap-3 p-3 group-data-[collapsible=icon]:p-2">
            <div
                class="flex items-center gap-3 rounded-xl bg-white p-3 shadow-sm group-data-[collapsible=icon]:justify-center group-data-[collapsible=icon]:p-1"
            >
                <img src="/brand/mrcp-world-mark.png" alt="" class="size-9 shrink-0 object-contain group-data-[collapsible=icon]:size-6" />
                <div class="grid leading-tight group-data-[collapsible=icon]:hidden">
                    <span class="text-lg font-bold tracking-tight">
                        <span class="text-[#e03131]">M</span><span class="text-[#2f9e44]">R</span><span class="text-[#1c7ed6]">C</span
                        ><span class="text-[#f08c00]">P</span><span class="text-[#14274a]">World</span>
                    </span>
                    <span class="text-[10px] font-semibold uppercase tracking-[0.2em] text-[#14274a]/70">MCQ BANK</span>
                </div>
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
                <span class="text-[10px] font-semibold uppercase tracking-wider text-white/75 group-data-[collapsible=icon]:hidden">Appearance</span>
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
