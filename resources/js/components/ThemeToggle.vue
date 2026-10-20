<script setup lang="ts">
// Adapted from shadcn-vue-admin toggle-theme.vue (MIT).
import { Button } from '@/components/ui/button';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { useAppearance } from '@/composables/useAppearance';
import { Moon, Sun, SunMoon } from 'lucide-vue-next';
const { updateAppearance } = useAppearance();
</script>
<template>
    <DropdownMenu>
        <DropdownMenuTrigger as-child>
            <Button variant="outline" size="icon" aria-label="Choose theme" title="Choose theme">
                <Sun class="size-4 dark:hidden" /><Moon class="hidden size-4 dark:block" />
            </Button>
        </DropdownMenuTrigger>
        <DropdownMenuContent align="end">
            <DropdownMenuItem @click="updateAppearance('light')"><Sun class="mr-2 size-4" />Light</DropdownMenuItem>
            <DropdownMenuItem @click="updateAppearance('dark')"><Moon class="mr-2 size-4" />Dark</DropdownMenuItem>
            <DropdownMenuItem @click="updateAppearance('system')"><SunMoon class="mr-2 size-4" />System</DropdownMenuItem>
        </DropdownMenuContent>
    </DropdownMenu>
</template>
