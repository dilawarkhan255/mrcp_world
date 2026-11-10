<?php

namespace App\Filament\Concerns;

use Filament\Tables\Actions\BulkAction;
use Filament\Tables\Actions\BulkActionGroup;
use Filament\Tables\Columns\Column;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Blade;
use Livewire\Attributes\Renderless;
use Yajra\DataTables\Facades\DataTables;

/**
 * Shows a Filament resource list as a server-side Yajra DataTable.
 *
 * The resource keeps its Filament table definition (columns, filters, row
 * and bulk actions): it is the single source of truth. This page renders
 * the rows with DataTables (resources/js/admin-yajra.ts) instead of the
 * Filament table markup. Rows come from yajraRows(), a Livewire call on the
 * page itself, so the page's authorisation, filters, search and sorting
 * apply exactly as before; Yajra pages, counts and builds the JSON. Each cell
 * is the Filament column component and each row action the Filament action
 * button, so edit modals, confirmations and notifications are unchanged.
 */
trait UsesYajraTable
{
    /** Rows per page offered in the length menu; requests above the last are capped. */
    public const YAJRA_PAGE_LENGTHS = [10, 25, 50, 100];

    public function getView(): string
    {
        return 'filament.pages.yajra-list-records';
    }

    /**
     * Column definitions for DataTables. Keys are positional (c0, c1, …)
     * because Filament names such as "exam.name" would be read as nested
     * paths by DataTables.
     *
     * @return list<array{data: string, title: string, orderable: bool}>
     */
    public function getYajraColumns(): array
    {
        $columns = [];
        foreach ($this->yajraVisibleColumns() as $index => $column) {
            $columns[] = ['data' => 'c'.$index, 'title' => (string) $column->getLabel(), 'orderable' => $column->isSortable()];
        }

        return $columns;
    }

    /**
     * Bulk actions as toolbar buttons (name, label, colour), in table order.
     *
     * @return list<array{name: string, label: string, color: string}>
     */
    public function getYajraBulkActions(): array
    {
        $buttons = [];
        foreach ($this->getTable()->getBulkActions() as $action) {
            $actions = $action instanceof BulkActionGroup ? $action->getActions() : [$action];
            foreach ($actions as $bulk) {
                if ($bulk instanceof BulkAction && $bulk->isVisible()) {
                    $color = $bulk->getColor();
                    $buttons[] = ['name' => $bulk->getName(), 'label' => (string) $bulk->getLabel(), 'color' => is_string($color) ? $color : 'gray'];
                }
            }
        }

        return $buttons;
    }

    public function hasYajraRowActions(): bool
    {
        return $this->getTable()->getActions() !== [];
    }

    /**
     * One page of rows for DataTables (server-side processing).
     *
     * @param  array<string, mixed>  $params  draw, start, length, search, sort (c<index>), dir
     * @return array<string, mixed>
     */
    #[Renderless]
    public function yajraRows(array $params): array
    {
        $table = $this->getTable();
        $columns = $this->yajraVisibleColumns();

        $this->tableSearch = $table->isSearchable() ? mb_substr(trim((string) data_get($params, 'search', '')), 0, 120) : '';
        $sort = (string) data_get($params, 'sort', '');
        $column = str_starts_with($sort, 'c') && ctype_digit(substr($sort, 1)) ? ($columns[(int) substr($sort, 1)] ?? null) : null;
        if ($column !== null && $column->isSortable()) {
            $this->tableSortColumn = $column->getName();
            $this->tableSortDirection = data_get($params, 'dir') === 'asc' ? 'asc' : 'desc';
        } else {
            $this->tableSortColumn = null;
            $this->tableSortDirection = null;
        }

        $length = (int) data_get($params, 'length', 25);
        $length = $length < 1 ? 25 : min($length, max(self::YAJRA_PAGE_LENGTHS));
        // Yajra reads paging from the request; search and ordering were applied
        // above through Filament, so Yajra gets none of its own.
        request()->merge([
            'draw' => (int) data_get($params, 'draw', 0),
            'start' => max(0, (int) data_get($params, 'start', 0)),
            'length' => $length,
            'search' => ['value' => '', 'regex' => false],
            'order' => [],
            'columns' => [],
        ]);

        $query = $this->getFilteredSortedTableQuery();
        $dataTable = DataTables::eloquent($query)
            // Filament applied the search and filters, so Yajra cannot tell the
            // two counts apart on its own.
            ->setTotalRecords($table->getQuery()->toBase()->getCountForPagination())
            ->setFilteredRecords((clone $query)->toBase()->getCountForPagination())
            ->order(function (): void {})
            ->addColumn('_key', fn (Model $record): string => $this->getTableRecordKey($record))
            ->addColumn('_actions', fn (Model $record): string => $this->renderYajraActions($record));
        $raw = ['_actions'];
        foreach ($columns as $index => $tableColumn) {
            $dataTable->addColumn('c'.$index, fn (Model $record): string => $this->renderYajraCell($tableColumn, $record));
            $raw[] = 'c'.$index;
        }

        $json = $dataTable->rawColumns($raw)->only(['_key', '_actions', ...array_slice($raw, 1)])->toArray();

        // Never send Yajra's debug output (SQL and input) to the browser.
        return array_intersect_key($json, array_flip(['draw', 'recordsTotal', 'recordsFiltered', 'data', 'error']));
    }

    /** @return list<Column> */
    protected function yajraVisibleColumns(): array
    {
        return array_values($this->getTable()->getVisibleColumns());
    }

    protected function renderYajraCell(Column $column, Model $record): string
    {
        $column->record($record);

        return Blade::render('<x-filament-tables::columns.column :column="$column" :record="$record" :record-key="$recordKey" />', [
            'column' => $column,
            'record' => $record,
            'recordKey' => $this->getTableRecordKey($record),
        ]);
    }

    protected function renderYajraActions(Model $record): string
    {
        return Blade::render('<x-filament-tables::actions :actions="$actions" :record="$record" />', [
            'actions' => $this->getTable()->getActions(),
            'record' => $record,
        ]);
    }
}
