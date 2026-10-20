<script setup lang="ts">
// Adapted from shadcn-vue-admin app-sidebar shell and nav-team-collapsible (MIT).
import AppLogo from '@/components/AppLogo.vue';
import ExamSwitcher from '@/components/ExamSwitcher.vue';
import NavUser from '@/components/NavUser.vue';
import { Collapsible, CollapsibleContent, CollapsibleTrigger } from '@/components/ui/collapsible';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuLabel, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
    SidebarMenuSub,
    SidebarMenuSubButton,
    SidebarMenuSubItem,
    SidebarRail,
    useSidebar,
} from '@/components/ui/sidebar';
import type { SharedData } from '@/types';
import { Link, usePage } from '@inertiajs/vue3';
import { BookOpen, Bookmark, Brain, ChevronRight, LayoutGrid, ListChecks } from 'lucide-vue-next';
import { computed } from 'vue';
const page = usePage<SharedData>();
const { state, isMobile, setOpenMobile } = useSidebar();
const groups = computed(() => page.props.navigation ?? []);
const icons: Record<string, typeof LayoutGrid> = { BookOpen, Bookmark, Brain, LayoutGrid, ListChecks };
const active = (href: string) => page.url.split('?')[0] === href;
const closeMobile = () => setOpenMobile(false);
</script>
<template>
    <Sidebar collapsible="icon">
        <SidebarHeader class="border-b p-3">
            <SidebarMenu
                ><SidebarMenuItem
                    ><SidebarMenuButton size="lg" as-child
                        ><Link href="/dashboard" @click="closeMobile"><AppLogo /></Link></SidebarMenuButton></SidebarMenuItem
            ></SidebarMenu>
            <ExamSwitcher />
        </SidebarHeader>
        <SidebarContent class="p-2">
            <SidebarMenu>
                <template v-for="group in groups" :key="group.title">
                    <SidebarMenuItem v-if="group.title === 'Home'">
                        <SidebarMenuButton as-child :is-active="active('/dashboard')" tooltip="Home"
                            ><Link href="/dashboard" @click="closeMobile"><LayoutGrid /><span>Home</span></Link></SidebarMenuButton
                        >
                    </SidebarMenuItem>
                    <SidebarMenuItem v-else>
                        <Collapsible v-if="state !== 'collapsed' || isMobile" :default-open="true" class="group/nav">
                            <CollapsibleTrigger as-child
                                ><SidebarMenuButton :tooltip="group.title"
                                    ><component :is="icons[group.icon]" /><span>{{ group.title }}</span
                                    ><ChevronRight
                                        class="ml-auto size-4 transition-transform group-data-[state=open]/nav:rotate-90" /></SidebarMenuButton
                            ></CollapsibleTrigger>
                            <CollapsibleContent
                                ><SidebarMenuSub
                                    ><SidebarMenuSubItem v-for="item in group.items" :key="item.href"
                                        ><SidebarMenuSubButton as-child :is-active="active(item.href)"
                                            ><Link :href="item.href" @click="closeMobile">{{ item.title }}</Link></SidebarMenuSubButton
                                        ></SidebarMenuSubItem
                                    ></SidebarMenuSub
                                ></CollapsibleContent
                            >
                        </Collapsible>
                        <DropdownMenu v-else>
                            <DropdownMenuTrigger as-child
                                ><SidebarMenuButton :tooltip="group.title"
                                    ><component :is="icons[group.icon]" /><span>{{ group.title }}</span></SidebarMenuButton
                                ></DropdownMenuTrigger
                            >
                            <DropdownMenuContent side="right" align="start"
                                ><DropdownMenuLabel>{{ group.title }}</DropdownMenuLabel
                                ><DropdownMenuItem v-for="item in group.items" :key="item.href" as-child
                                    ><Link :href="item.href" :aria-current="active(item.href) ? 'page' : undefined">{{
                                        item.title
                                    }}</Link></DropdownMenuItem
                                ></DropdownMenuContent
                            >
                        </DropdownMenu>
                    </SidebarMenuItem>
                </template>
            </SidebarMenu>
        </SidebarContent>
        <SidebarFooter class="border-t"><NavUser /></SidebarFooter>
        <SidebarRail />
    </Sidebar>
</template>
