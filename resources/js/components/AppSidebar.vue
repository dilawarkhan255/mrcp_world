<script setup lang="ts">
// Shell adapted from shadcn-vue-admin app-sidebar/index.vue (MIT).
import NavUser from '@/components/NavUser.vue';
import AppLogo from '@/components/AppLogo.vue';
import { Link } from '@inertiajs/vue3';
import { LayoutGrid } from 'lucide-vue-next';
import { Sidebar, SidebarContent, SidebarFooter, SidebarHeader, SidebarMenu, SidebarMenuItem, SidebarMenuButton, SidebarRail } from '@/components/ui/sidebar';
</script>
<template>
    <Sidebar collapsible="icon">
        <SidebarHeader class="border-b"><SidebarMenu><SidebarMenuItem><SidebarMenuButton size="lg" as-child><Link href="/dashboard"><AppLogo /></Link></SidebarMenuButton></SidebarMenuItem></SidebarMenu></SidebarHeader>
        <SidebarContent><SidebarMenu class="p-2"><SidebarMenuItem><SidebarMenuButton as-child><Link href="/dashboard"><LayoutGrid /><span>Home</span></Link></SidebarMenuButton></SidebarMenuItem></SidebarMenu></SidebarContent>
        <SidebarFooter class="border-t"><NavUser /></SidebarFooter>
        <SidebarRail />
    </Sidebar>
</template>
