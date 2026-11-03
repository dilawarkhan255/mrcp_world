<script setup lang="ts">
// Icon-only sidebar: the items of a dropdown. Nodes with children become sub-menus.
import { DropdownMenuItem, DropdownMenuSub, DropdownMenuSubContent, DropdownMenuSubTrigger } from '@/components/ui/dropdown-menu';
import { useSidebar } from '@/components/ui/sidebar';
import { useNavActive } from '@/composables/useNavActive';
import type { NavNode } from '@/types';
import { Link } from '@inertiajs/vue3';

defineProps<{ nodes: NavNode[] }>();

const { setOpenMobile } = useSidebar();
const { isActive, containsActive } = useNavActive();
</script>
<template>
    <template v-for="node in nodes" :key="node.title + (node.href ?? '')">
        <DropdownMenuItem v-if="!node.children?.length" as-child>
            <Link
                :href="node.href ?? '/dashboard'"
                :aria-current="isActive(node) ? 'page' : undefined"
                :class="isActive(node) ? 'font-semibold text-primary' : ''"
                @click="setOpenMobile(false)"
            >
                {{ node.title }}
            </Link>
        </DropdownMenuItem>
        <DropdownMenuSub v-else>
            <DropdownMenuSubTrigger :class="containsActive(node) ? 'font-semibold text-primary' : ''">{{ node.title }}</DropdownMenuSubTrigger>
            <DropdownMenuSubContent class="min-w-56">
                <NavFlyout :nodes="node.children ?? []" />
            </DropdownMenuSubContent>
        </DropdownMenuSub>
    </template>
</template>
