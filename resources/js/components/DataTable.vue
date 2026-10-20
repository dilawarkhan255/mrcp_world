<script setup lang="ts" generic="TData, TValue">
// Adapted from shadcn-vue-admin data-table/data-table.vue and pagination (MIT).
// TanStack v8 replaces the upstream v9 API; no router or sample data is included.
import { Button } from '@/components/ui/button';
import type { ColumnDef, SortingState } from '@tanstack/vue-table';
import { FlexRender, getCoreRowModel, getFilteredRowModel, getPaginationRowModel, getSortedRowModel, useVueTable } from '@tanstack/vue-table';
import { ref } from 'vue';
const props = defineProps<{ columns: ColumnDef<TData, TValue>[]; data: TData[]; loading?: boolean }>();
const sorting = ref<SortingState>([]);
const filter = ref('');
const table = useVueTable({
    get data() {
        return props.data;
    },
    get columns() {
        return props.columns;
    },
    state: {
        get sorting() {
            return sorting.value;
        },
        get globalFilter() {
            return filter.value;
        },
    },
    onSortingChange: (updater) => {
        sorting.value = typeof updater === 'function' ? updater(sorting.value) : updater;
    },
    getCoreRowModel: getCoreRowModel(),
    getFilteredRowModel: getFilteredRowModel(),
    getPaginationRowModel: getPaginationRowModel(),
    getSortedRowModel: getSortedRowModel(),
});
</script>
<template>
    <div class="space-y-4">
        <slot name="toolbar" :table="table">
            <label class="block text-sm"
                >Filter rows<input v-model="filter" type="search" class="ml-3 rounded-md border bg-background px-3 py-2"
            /></label>
        </slot>
        <div class="overflow-x-auto rounded-md border">
            <table class="w-full text-sm" :aria-busy="loading">
                <thead class="border-b bg-muted/40">
                    <tr v-for="group in table.getHeaderGroups()" :key="group.id">
                        <th
                            v-for="header in group.headers"
                            :key="header.id"
                            class="px-4 py-3 text-left font-medium"
                            :aria-sort="
                                header.column.getIsSorted() === 'asc' ? 'ascending' : header.column.getIsSorted() === 'desc' ? 'descending' : 'none'
                            "
                        >
                            <template v-if="!header.isPlaceholder">
                                <button v-if="header.column.getCanSort()" class="flex items-center gap-2" @click="header.column.toggleSorting()">
                                    <FlexRender :render="header.column.columnDef.header" :props="header.getContext()" />
                                    <span aria-hidden="true">{{
                                        header.column.getIsSorted() === 'desc' ? '↓' : header.column.getIsSorted() === 'asc' ? '↑' : '↕'
                                    }}</span>
                                </button>
                                <FlexRender v-else :render="header.column.columnDef.header" :props="header.getContext()" />
                            </template>
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-if="loading">
                        <td :colspan="columns.length" class="h-24 text-center">Loading…</td>
                    </tr>
                    <template v-else-if="table.getRowModel().rows.length">
                        <tr
                            v-for="row in table.getRowModel().rows"
                            :key="row.id"
                            class="border-b last:border-0 hover:bg-muted/30"
                            :data-state="row.getIsSelected() ? 'selected' : undefined"
                        >
                            <td v-for="cell in row.getVisibleCells()" :key="cell.id" class="px-4 py-3">
                                <FlexRender :render="cell.column.columnDef.cell" :props="cell.getContext()" />
                            </td>
                        </tr>
                    </template>
                    <tr v-else>
                        <td :colspan="columns.length" class="h-24 text-center text-muted-foreground"><slot name="empty">No results found.</slot></td>
                    </tr>
                </tbody>
            </table>
        </div>
        <div class="flex flex-wrap items-center justify-end gap-3 text-sm">
            <label
                >Rows per page
                <select
                    class="ml-2 rounded border bg-background p-1"
                    :value="table.getState().pagination.pageSize"
                    @change="table.setPageSize(Number(($event.target as HTMLSelectElement).value))"
                >
                    <option v-for="size in [10, 20, 50]" :key="size" :value="size">{{ size }}</option>
                </select></label
            >
            <span>Page {{ table.getState().pagination.pageIndex + 1 }} of {{ Math.max(1, table.getPageCount()) }}</span>
            <Button variant="outline" size="sm" :disabled="!table.getCanPreviousPage()" @click="table.previousPage()">Previous</Button>
            <Button variant="outline" size="sm" :disabled="!table.getCanNextPage()" @click="table.nextPage()">Next</Button>
        </div>
    </div>
</template>
