<script setup lang="ts">
// Expanded sidebar tree below a top-level item. Nodes with children open and close in place.
import { Collapsible, CollapsibleContent, CollapsibleTrigger } from '@/components/ui/collapsible';
import { SidebarMenuSub, SidebarMenuSubButton, SidebarMenuSubItem, useSidebar } from '@/components/ui/sidebar';
import { useNavActive } from '@/composables/useNavActive';
import { ACTIVE_CLASS } from '@/lib/nav-classes';
import type { NavNode } from '@/types';
import { Link } from '@inertiajs/vue3';
import { Minus, Plus } from 'lucide-vue-next';

defineProps<{ nodes: NavNode[] }>();

const { setOpenMobile } = useSidebar();
const { isActive, containsActive } = useNavActive();
const closeMobile = () => setOpenMobile(false);
</script>
<template>
    <SidebarMenuSub>
        <template v-for="node in nodes" :key="node.title + (node.href ?? '')">
            <SidebarMenuSubItem v-if="!node.children?.length">
                <SidebarMenuSubButton as-child :is-active="isActive(node)" :class="ACTIVE_CLASS">
                    <Link :href="node.href ?? '/dashboard'" :aria-current="isActive(node) ? 'page' : undefined" @click="closeMobile">
                        {{ node.title }}
                    </Link>
                </SidebarMenuSubButton>
            </SidebarMenuSubItem>
            <SidebarMenuSubItem v-else>
                <Collapsible v-slot="{ open }" :default-open="containsActive(node)">
                    <CollapsibleTrigger as-child>
                        <SidebarMenuSubButton as="button" class="w-full cursor-pointer" :class="containsActive(node) ? 'font-semibold' : ''">
                            <span>{{ node.title }}</span>
                            <Minus v-if="open" class="ml-auto size-3.5" />
                            <Plus v-else class="ml-auto size-3.5" />
                        </SidebarMenuSubButton>
                    </CollapsibleTrigger>
                    <CollapsibleContent>
                        <NavSubTree :nodes="node.children ?? []" />
                    </CollapsibleContent>
                </Collapsible>
            </SidebarMenuSubItem>
        </template>
    </SidebarMenuSub>
</template>
