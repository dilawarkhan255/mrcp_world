<script setup lang="ts">
// Expanded sidebar tree below a top-level item. Nodes with children open and close in place.
import { Collapsible, CollapsibleContent, CollapsibleTrigger } from '@/components/ui/collapsible';
import { SidebarMenuSub, SidebarMenuSubButton, SidebarMenuSubItem, useSidebar } from '@/components/ui/sidebar';
import { useNavActive } from '@/composables/useNavActive';
import { ACTIVE_CLASS } from '@/lib/nav-classes';
import { navIcon } from '@/lib/nav-icons';
import type { NavNode } from '@/types';
import { Link } from '@inertiajs/vue3';
import { Lock, Minus, Plus } from 'lucide-vue-next';

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
                        <component :is="navIcon(node.icon)" v-if="navIcon(node.icon)" class="size-3.5 shrink-0" aria-hidden="true" />
                        <span>{{ node.title }}</span>
                        <Lock v-if="node.locked" class="ml-auto size-3 text-muted-foreground" aria-label="Needs a subscription" />
                    </Link>
                </SidebarMenuSubButton>
            </SidebarMenuSubItem>
            <SidebarMenuSubItem v-else>
                <Collapsible v-slot="{ open }" :default-open="containsActive(node)">
                    <CollapsibleTrigger as-child>
                        <SidebarMenuSubButton as="button" class="w-full cursor-pointer" :class="containsActive(node) ? 'font-semibold' : ''">
                            <component :is="navIcon(node.icon)" v-if="navIcon(node.icon)" class="size-3.5 shrink-0" aria-hidden="true" />
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
