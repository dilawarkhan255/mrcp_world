<script setup lang="ts">
import ExamSwitcher from '@/components/ExamSwitcher.vue';
import NavFlyout from '@/components/NavFlyout.vue';
import NavSubTree from '@/components/NavSubTree.vue';
import { Collapsible, CollapsibleContent, CollapsibleTrigger } from '@/components/ui/collapsible';
import { DropdownMenu, DropdownMenuContent, DropdownMenuLabel, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import {
    Sidebar,
    SidebarContent,
    SidebarGroupLabel,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
    SidebarRail,
    useSidebar,
} from '@/components/ui/sidebar';
import { useNavActive } from '@/composables/useNavActive';
import { ACTIVE_CLASS } from '@/lib/nav-classes';
import { navIcon } from '@/lib/nav-icons';
import type { SharedData } from '@/types';
import { Link, usePage } from '@inertiajs/vue3';
import { Lock, MessageCircle, Minus, Plus, Shield, Smartphone } from 'lucide-vue-next';
import { computed } from 'vue';

/**
 * Student sidebar: the logo row (the same height as the top bar, so their
 * bottom borders line up), the exam switcher and the navigation. Appearance,
 * search, notifications and the user menu live in the top bar.
 */
const page = usePage<SharedData>();
const { state, isMobile, setOpenMobile } = useSidebar();
const { isActive, containsActive } = useNavActive();

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
</script>

<template>
    <Sidebar collapsible="icon">
        <SidebarHeader class="h-20 shrink-0 flex-row items-center justify-center border-b border-sidebar-border p-0">
            <Link href="/dashboard" class="flex h-full items-center justify-center px-3" aria-label="MRCPWorld dashboard">
                <!-- Full logo when expanded; the small mark alone when the sidebar is icon-only. -->
                <img
                    src="/brand/mrcp-world-logo.trim.png"
                    alt="MRCPWorld MCQ Bank"
                    class="h-16 w-auto object-contain group-data-[collapsible=icon]:hidden dark:hidden"
                />
                <!-- Dark mode: the same logo with the word "World" in a light colour. -->
                <img
                    src="/brand/mrcp-world-logo.dark.png"
                    alt="MRCPWorld MCQ Bank"
                    class="hidden h-16 w-auto object-contain group-data-[collapsible=icon]:!hidden dark:block"
                />
                <img src="/brand/mrcp-world-mark.trim.png" alt="" class="hidden size-10 object-contain group-data-[collapsible=icon]:block" />
            </Link>
        </SidebarHeader>

        <SidebarContent class="gap-0 p-2">
            <div class="mb-2 border-b border-sidebar-border pb-2">
                <ExamSwitcher />
            </div>
            <SidebarMenu>
                <template v-for="(item, index) in navigation" :key="item.title + '-' + index">
                    <!-- A section heading, for example "More". -->
                    <li v-if="item.section" class="list-none">
                        <SidebarGroupLabel class="mt-3 px-2 text-[10px] uppercase tracking-wider">{{ item.section }}</SidebarGroupLabel>
                    </li>

                    <!-- A plain link. -->
                    <SidebarMenuItem v-else-if="!item.children?.length">
                        <SidebarMenuButton as-child :is-active="isActive(item)" :tooltip="item.title" :class="ACTIVE_CLASS">
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
                                <SidebarMenuButton :is-active="containsActive(item)" :tooltip="item.title" :class="ACTIVE_CLASS">
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
                                <SidebarMenuButton :is-active="containsActive(item)" :class="ACTIVE_CLASS">
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
                    <SidebarMenuButton as-child tooltip="Admin panel">
                        <a href="/admin" @click="closeMobile"><Shield /><span class="group-data-[collapsible=icon]:hidden">Admin panel</span></a>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarContent>
        <SidebarRail />
    </Sidebar>
</template>
