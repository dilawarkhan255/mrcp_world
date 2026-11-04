<script setup lang="ts">
import GlobalSearch from '@/components/GlobalSearch.vue';
import NotificationBell from '@/components/NotificationBell.vue';
import SidebarToggle from '@/components/SidebarToggle.vue';
import ThemeToggle from '@/components/ThemeToggle.vue';
import TopbarUser from '@/components/TopbarUser.vue';
import { Breadcrumb, BreadcrumbItem, BreadcrumbLink, BreadcrumbList, BreadcrumbPage, BreadcrumbSeparator } from '@/components/ui/breadcrumb';
import type { BreadcrumbItemType } from '@/types';

/**
 * Top bar of the student app: sidebar toggle and breadcrumbs on the left;
 * search, notifications, Day/Dark and the account menu on the right. Its
 * height matches the sidebar's logo row, so their bottom borders line up.
 */
defineProps<{
    breadcrumbs?: BreadcrumbItemType[];
}>();
</script>

<template>
    <header
        class="sticky top-0 z-30 flex h-20 shrink-0 items-center gap-3 border-b border-sidebar-border bg-background/90 px-4 backdrop-blur md:px-6"
    >
        <SidebarToggle />
        <nav v-if="breadcrumbs && breadcrumbs.length > 0" aria-label="Breadcrumb" class="hidden min-w-0 lg:block">
            <Breadcrumb>
                <BreadcrumbList>
                    <template v-for="(item, index) in breadcrumbs" :key="index">
                        <BreadcrumbItem class="min-w-0">
                            <BreadcrumbPage v-if="index === breadcrumbs.length - 1" class="truncate">{{ item.title }}</BreadcrumbPage>
                            <BreadcrumbLink v-else :href="item.href">{{ item.title }}</BreadcrumbLink>
                        </BreadcrumbItem>
                        <BreadcrumbSeparator v-if="index !== breadcrumbs.length - 1" />
                    </template>
                </BreadcrumbList>
            </Breadcrumb>
        </nav>
        <div class="ml-auto flex items-center gap-2">
            <GlobalSearch />
            <NotificationBell />
            <ThemeToggle />
            <TopbarUser />
        </div>
        <span class="brand-rainbow pointer-events-none absolute inset-x-0 -bottom-px h-0.5 opacity-80" aria-hidden="true" />
    </header>
</template>
