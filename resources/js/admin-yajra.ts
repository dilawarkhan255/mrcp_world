/**
 * Yajra DataTables for the Filament admin lists (view:
 * resources/views/filament/pages/yajra-list-records.blade.php, PHP:
 * App\Filament\Concerns\UsesYajraTable).
 *
 * Rows are fetched with a Livewire call on the page ($wire.yajraRows), so
 * the page's authorisation, filters, search and sorting apply on the server.
 * Cells and row action buttons arrive as Filament HTML; their wire:click
 * handlers open the usual Filament modals. After an action, a filter change
 * or a create, the table reloads and keeps its page.
 */
import DataTable, { type AjaxData } from 'datatables.net-dt';
import 'datatables.net-dt/css/dataTables.dataTables.css';
import '../css/admin-yajra.css';

interface YajraConfig {
    columns: { data: string; title: string; orderable: boolean }[];
    bulk: boolean;
    rowActions: boolean;
    searchable: boolean;
    pageLengths: number[];
    pageLength: number;
    emptyText: string;
    searchPlaceholder: string;
}

interface Wire {
    $id: string;
    yajraRows(params: Record<string, unknown>): Promise<unknown>;
    mountTableBulkAction(name: string, records: string[]): Promise<unknown>;
}

interface Commit {
    calls?: { method: string }[];
    updates?: Record<string, unknown>;
}

interface LivewireGlobal {
    hook(
        name: 'commit',
        callback: (payload: {
            component: { id: string };
            commit: Commit;
            succeed: (fn: (response: { effects?: { dispatches?: { name: string }[] } }) => void) => void;
        }) => void,
    ): (() => void) | void;
}

interface AlpineGlobal {
    data(name: string, factory: (config: YajraConfig) => Record<string, unknown>): void;
}

declare global {
    interface Window {
        Alpine?: AlpineGlobal;
        Livewire?: LivewireGlobal;
    }
}

/** Server calls after which the rows may have changed. */
const RELOAD_AFTER = /^(mount|callMounted)(Table)?(Bulk)?Action$|^removeTableFilters?$|^resetTableFiltersForm$/;

function escapeHtml(value: unknown): string {
    const map: Record<string, string> = { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' };
    return String(value ?? '').replace(/[&<>"']/g, (char) => map[char] ?? char);
}

function yajraTable(config: YajraConfig) {
    let table: InstanceType<typeof DataTable> | null = null;
    let stopHook: (() => void) | void;

    return {
        selected: [] as string[],
        filtersOpen: false,

        init(this: { $refs: Record<string, HTMLElement>; $wire: Wire; selected: string[]; reload: (clear?: boolean) => void }) {
            const element = this.$refs.table as HTMLTableElement;
            const wire = this.$wire;
            const columns: Record<string, unknown>[] = [];

            if (config.bulk) {
                columns.push({
                    data: '_key',
                    orderable: false,
                    className: 'mw-yajra-select',
                    title: '<input type="checkbox" class="fi-checkbox-input mw-yajra-checkbox" data-mw-select-page aria-label="Select all on this page">',
                    render: (key: unknown) =>
                        '<input type="checkbox" class="fi-checkbox-input mw-yajra-checkbox" data-mw-select value="' +
                        escapeHtml(key) +
                        '"' +
                        (this.selected.includes(String(key)) ? ' checked' : '') +
                        ' aria-label="Select row">',
                });
            }
            columns.push(...config.columns.map((column) => ({ data: column.data, title: escapeHtml(column.title), orderable: column.orderable })));
            if (config.rowActions) {
                columns.push({ data: '_actions', title: '<span class="sr-only">Actions</span>', orderable: false, className: 'mw-yajra-actions' });
            }

            table = new DataTable(element, {
                serverSide: true,
                processing: true,
                searching: config.searchable,
                // No initial order: the resource's default sort applies on the server.
                order: [],
                columns,
                pageLength: config.pageLength,
                lengthMenu: config.pageLengths,
                autoWidth: false,
                ajax: (data: AjaxData, callback: (json: unknown) => void) => {
                    const order = data.order?.[0];
                    const sortColumn = order ? String(data.columns?.[order.column]?.data ?? '') : '';
                    wire.yajraRows({
                        draw: data.draw,
                        start: data.start,
                        length: data.length,
                        search: data.search?.value ?? '',
                        sort: sortColumn,
                        dir: order?.dir ?? '',
                    }).then(callback);
                },
                drawCallback: () => syncPageCheckbox(element, this.selected),
                language: {
                    search: '',
                    searchPlaceholder: config.searchPlaceholder || 'Search…',
                    emptyTable: escapeHtml(config.emptyText),
                    zeroRecords: 'No matching records.',
                    info: '_START_–_END_ of _TOTAL_',
                    infoEmpty: '',
                    infoFiltered: '',
                    lengthMenu: '_MENU_ per page',
                    processing: 'Loading…',
                },
            });

            // Row and page checkboxes.
            element.addEventListener('change', (event) => {
                const input = event.target as HTMLInputElement;
                if (input.matches('[data-mw-select]')) {
                    this.selected = input.checked
                        ? [...new Set([...this.selected, input.value])]
                        : this.selected.filter((key) => key !== input.value);
                    syncPageCheckbox(element, this.selected);
                } else if (input.matches('[data-mw-select-page]')) {
                    const keys = rowCheckboxes(element).map((box) => {
                        box.checked = input.checked;
                        return box.value;
                    });
                    this.selected = input.checked ? [...new Set([...this.selected, ...keys])] : this.selected.filter((key) => !keys.includes(key));
                }
            });

            stopHook = window.Livewire?.hook('commit', ({ component, commit, succeed }) => {
                if (component.id !== wire.$id) return;
                const calls = commit.calls ?? [];
                if (calls.some((call) => call.method === 'yajraRows')) return;
                const changed =
                    calls.some((call) => RELOAD_AFTER.test(call.method)) ||
                    Object.keys(commit.updates ?? {}).some((key) => key.startsWith('tableFilters'));
                if (!changed) return;
                const finishedBulk = calls.some((call) => call.method === 'callMountedTableBulkAction');
                const clearedFilters = calls.some((call) => call.method === 'removeTableFilters');
                const onlyMounted = calls.every((call) => /^mount/.test(call.method)) && !Object.keys(commit.updates ?? {}).length;
                succeed(({ effects }) => {
                    // A modal opened (edit form, confirmation): nothing changed yet. Reloading now would
                    // briefly disable its buttons; the table reloads when the action is submitted.
                    if (onlyMounted && (effects?.dispatches ?? []).some((dispatch) => dispatch.name === 'open-modal')) return;
                    if (clearedFilters) table?.search('');
                    this.reload(finishedBulk);
                });
            });
        },

        reload(this: { selected: string[] }, clearSelection = false) {
            if (clearSelection) this.selected = [];
            table?.ajax.reload(undefined, false);
        },

        bulk(this: { $wire: Wire; selected: string[] }, name: string) {
            if (this.selected.length) this.$wire.mountTableBulkAction(name, [...this.selected]);
        },

        clearSelection(this: { $refs: Record<string, HTMLElement>; selected: string[] }) {
            this.selected = [];
            rowCheckboxes(this.$refs.table).forEach((box) => (box.checked = false));
            syncPageCheckbox(this.$refs.table, []);
        },

        destroy() {
            if (typeof stopHook === 'function') stopHook();
            table?.destroy();
            table = null;
        },
    };
}

function rowCheckboxes(element: HTMLElement): HTMLInputElement[] {
    return Array.from(element.querySelectorAll<HTMLInputElement>('tbody [data-mw-select]'));
}

function syncPageCheckbox(element: HTMLElement, selected: string[]): void {
    const page = element.querySelector<HTMLInputElement>('thead [data-mw-select-page]');
    if (!page) return;
    const keys = rowCheckboxes(element).map((box) => box.value);
    const count = keys.filter((key) => selected.includes(key)).length;
    page.checked = keys.length > 0 && count === keys.length;
    page.indeterminate = count > 0 && count < keys.length;
}

function register(): void {
    window.Alpine?.data('mwYajraTable', yajraTable as unknown as (config: YajraConfig) => Record<string, unknown>);
}

// Alpine (bundled with Livewire) starts after this module; register before it does.
if (window.Alpine) register();
else document.addEventListener('alpine:init', register, { once: true });
