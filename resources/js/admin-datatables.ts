/**
 * Admin panel tables that are served by Yajra DataTables.
 *
 * This entry point is only loaded on the admin pages that use it, so the
 * student application never downloads the table library.
 */
import 'datatables.net';
import 'datatables.net-dt/css/dataTables.dataTables.css';

const table = document.querySelector<HTMLTableElement>('[data-yajra-table]');

type DataTableConstructor = new (target: HTMLTableElement, options: Record<string, unknown>) => unknown;

if (table) {
    const source = table.dataset.source ?? '';
    const columns = (table.dataset.columns ?? '').split(',').filter(Boolean);
    const orderColumn = Number(table.dataset.orderColumn ?? 0);
    const orderDirection = table.dataset.orderDirection ?? 'asc';
    const DataTable = (window as unknown as { DataTable?: DataTableConstructor }).DataTable;

    if (DataTable) {
        new DataTable(table, {
            serverSide: true,
            processing: true,
            ajax: {
                url: source,
                type: 'GET',
            },
            columns: columns.map((name) => ({ data: name, name })),
            order: [[orderColumn, orderDirection]],
            pageLength: 25,
            lengthMenu: [25, 50, 100],
            language: {
                emptyTable: 'No records found.',
                zeroRecords: 'No records found.',
            },
        });
    }
}
