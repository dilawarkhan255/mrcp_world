import type { YajraAction, YajraColumn } from '@/components/YajraTable.vue';
import { Eye, Play } from 'lucide';

/** Escapes a value for the HTML that data-table cells are built from. */
export function escapeHtml(value: unknown): string {
    const map: Record<string, string> = { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' };
    return String(value ?? '').replace(/[&<>"']/g, (char) => map[char] ?? char);
}

function date(value: unknown): string {
    return value ? escapeHtml(new Date(String(value)).toLocaleString()) : '<span class="text-muted-foreground">—</span>';
}

function status(row: Record<string, unknown>): string {
    return row.completed_at
        ? '<span class="yajra-badge bg-emerald-500/15 text-emerald-700 dark:text-emerald-400">Completed</span>'
        : '<span class="yajra-badge bg-amber/20 text-amber-800 dark:text-amber">In progress</span>';
}

/** Columns shared by the sessions and mock attempts tables (StudentTableController). */
export const startedColumn: YajraColumn = { data: 'created_at', title: 'Started', orderable: true, render: (value) => date(value) };
export const progressColumn: YajraColumn = {
    data: 'answered_count',
    title: 'Progress',
    orderable: true,
    render: (value, row) => escapeHtml(value) + ' / ' + escapeHtml(row.total_questions),
};
export const statusColumn: YajraColumn = { data: 'status', title: 'Status', render: (_value, row) => status(row) };

/** Resume (play) an open session, or review (eye) a finished one. */
export function openAction(base: (row: Record<string, unknown>) => string): YajraAction {
    return {
        label: (row) => (row.completed_at ? 'Review' : 'Resume'),
        icon: (row) => (row.completed_at ? Eye : Play),
        href: (row) => base(row) + String(row.id),
    };
}
