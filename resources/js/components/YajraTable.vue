<script setup lang="ts">
/**
 * A server-side data table fed by Yajra DataTables (Laravel). Columns render
 * plain text by default (escaped); a column can pass its own render function
 * returning safe HTML (escape values with escapeHtml from lib/session-table). Row actions are icon buttons that navigate with
 * Inertia. Used by the student lists (sessions, mock attempts).
 */
import { escapeHtml } from '@/lib/session-table';
import { router } from '@inertiajs/vue3';
import DataTable from 'datatables.net-dt';
import 'datatables.net-dt/css/dataTables.dataTables.css';
import { createElement, type IconNode } from 'lucide';
import { onBeforeUnmount, onMounted, ref } from 'vue';

export interface YajraColumn {
    data: string;
    title: string;
    orderable?: boolean;
    className?: string;
    /** Returns trusted HTML: escape any value with escapeHtml(). */
    render?: (value: unknown, row: Record<string, unknown>) => string;
}
export interface YajraAction {
    label: string | ((row: Record<string, unknown>) => string);
    icon: IconNode | ((row: Record<string, unknown>) => IconNode);
    href: (row: Record<string, unknown>) => string;
}

const props = withDefaults(
    defineProps<{
        url: string;
        columns: YajraColumn[];
        actions?: YajraAction[];
        order?: [number, 'asc' | 'desc'];
        pageLength?: number;
        searchPlaceholder?: string;
        emptyText?: string;
    }>(),
    { actions: () => [], order: () => [0, 'desc'], pageLength: 10, searchPlaceholder: 'Search…', emptyText: 'Nothing here yet.' },
);

const element = ref<HTMLTableElement | null>(null);
let table: InstanceType<typeof DataTable> | null = null;

function iconHtml(icon: IconNode): string {
    const svg = createElement(icon);
    svg.setAttribute('width', '16');
    svg.setAttribute('height', '16');
    svg.setAttribute('aria-hidden', 'true');
    return svg.outerHTML;
}

function actionsHtml(row: Record<string, unknown>): string {
    return props.actions
        .map((action) => {
            const label = typeof action.label === 'function' ? action.label(row) : action.label;
            return (
                '<a href="' +
                escapeHtml(action.href(row)) +
                '" data-inertia-link class="yajra-action" title="' +
                escapeHtml(label) +
                '" aria-label="' +
                escapeHtml(label) +
                '">' +
                iconHtml(typeof action.icon === 'function' ? action.icon(row) : action.icon) +
                '</a>'
            );
        })
        .join('');
}

/** Row actions are links; follow them with Inertia instead of a full page load. */
function onClick(event: MouseEvent) {
    const link = (event.target as HTMLElement).closest('a[data-inertia-link]');
    if (link instanceof HTMLAnchorElement && !event.ctrlKey && !event.metaKey) {
        event.preventDefault();
        router.visit(link.getAttribute('href') ?? '/');
    }
}

onMounted(() => {
    if (!element.value) return;
    const columns = props.columns.map((column) => ({
        data: column.data,
        name: column.data,
        title: column.title,
        orderable: column.orderable ?? false,
        className: column.className ?? '',
        render: (value: unknown, type: string, row: Record<string, unknown>) =>
            type === 'display' && column.render ? column.render(value, row) : type === 'display' ? escapeHtml(value) : value,
    }));
    if (props.actions.length) {
        columns.push({
            data: 'id',
            name: 'actions',
            title: 'Actions',
            orderable: false,
            className: 'yajra-actions',
            render: (_value: unknown, type: string, row: Record<string, unknown>) => (type === 'display' ? actionsHtml(row) : ''),
        });
    }
    table = new DataTable(element.value, {
        serverSide: true,
        processing: true,
        ajax: { url: props.url, type: 'GET' },
        columns,
        order: [props.order],
        pageLength: props.pageLength,
        lengthMenu: [10, 25, 50],
        autoWidth: false,
        language: {
            search: '',
            searchPlaceholder: props.searchPlaceholder,
            emptyTable: props.emptyText,
            zeroRecords: 'No matches.',
            info: '_START_–_END_ of _TOTAL_',
            infoEmpty: '',
            infoFiltered: '',
            lengthMenu: '_MENU_ per page',
        },
    });
    element.value.addEventListener('click', onClick);
});

onBeforeUnmount(() => {
    element.value?.removeEventListener('click', onClick);
    table?.destroy();
    table = null;
});
</script>

<template>
    <div class="yajra-table overflow-x-auto rounded-xl border bg-card p-4 shadow-sm">
        <table ref="element" class="display w-full text-sm" />
    </div>
</template>
