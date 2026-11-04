<script setup lang="ts">
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuGroup,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { SidebarMenu, SidebarMenuButton, SidebarMenuItem } from '@/components/ui/sidebar';
import type { Exam, SharedData } from '@/types';
import { router, usePage } from '@inertiajs/vue3';
import { GraduationCap } from 'lucide-vue-next';
import { computed } from 'vue';
const page = usePage<SharedData>();
const exams = computed(() => page.props.exams ?? []);
/** Exams grouped by Qbank, keeping the server order. */
const groups = computed(() => {
    const result: { label: string | null; exams: Exam[] }[] = [];
    for (const exam of exams.value) {
        const label = exam.qbank ?? null;
        const last = result[result.length - 1];
        if (last && last.label === label) {
            last.exams.push(exam);
        } else {
            result.push({ label, exams: [exam] });
        }
    }
    return result;
});
function selectExam(examId: number) {
    router.post('/exam', { exam_id: examId }, { preserveScroll: true });
}
</script>
<template>
    <SidebarMenu
        ><SidebarMenuItem
            ><DropdownMenu>
                <DropdownMenuTrigger as-child
                    ><SidebarMenuButton size="lg" :tooltip="page.props.currentExam?.name || 'Choose exam'"
                        ><GraduationCap class="text-sidebar-primary" /><span class="grid text-left group-data-[collapsible=icon]:hidden"
                            ><span class="text-[10px] uppercase tracking-wider text-muted-foreground">Your exam</span
                            ><span class="truncate text-xs font-semibold">{{ page.props.currentExam?.name || 'Choose exam' }}</span></span
                        ></SidebarMenuButton
                    ></DropdownMenuTrigger
                >
                <DropdownMenuContent side="right" align="start" class="min-w-56">
                    <template v-for="(group, index) in groups" :key="group.label ?? 'other'">
                        <DropdownMenuSeparator v-if="index > 0" />
                        <DropdownMenuGroup>
                            <DropdownMenuLabel v-if="group.label" class="text-[10px] uppercase tracking-wider text-muted-foreground">{{
                                group.label
                            }}</DropdownMenuLabel>
                            <DropdownMenuItem
                                v-for="exam in group.exams"
                                :key="exam.id"
                                :aria-current="exam.id === page.props.currentExam?.id ? 'true' : undefined"
                                @click="selectExam(exam.id)"
                                >{{ exam.name
                                }}<span v-if="exam.id === page.props.currentExam?.id" class="ml-auto text-primary">✓</span></DropdownMenuItem
                            >
                        </DropdownMenuGroup>
                    </template>
                    <DropdownMenuItem v-if="!exams.length" disabled>No exams available</DropdownMenuItem>
                </DropdownMenuContent>
            </DropdownMenu></SidebarMenuItem
        ></SidebarMenu
    >
</template>
