{{-- A resource list as a server-side Yajra DataTable (App\Filament\Concerns\UsesYajraTable). --}}
@php
    $table = $this->getTable();
    $bulkActions = $this->getYajraBulkActions();
    $isFilterable = $table->isFilterable();
    $activeFilters = $isFilterable ? array_sum(array_map(fn ($filter): int => count($filter->getIndicators()), $table->getFilters())) : 0;
    $config = [
        'columns' => $this->getYajraColumns(),
        'bulk' => $bulkActions !== [],
        'rowActions' => $this->hasYajraRowActions(),
        'searchable' => $table->isSearchable(),
        'pageLengths' => $this::YAJRA_PAGE_LENGTHS,
        'pageLength' => in_array($table->getDefaultPaginationPageOption(), $this::YAJRA_PAGE_LENGTHS, true) ? $table->getDefaultPaginationPageOption() : 25,
        'emptyText' => (string) ($table->getEmptyStateHeading() ?? 'No records found.'),
        'searchPlaceholder' => (string) $table->getSearchPlaceholder(),
    ];
@endphp

<x-filament-panels::page
    @class([
        'fi-resource-list-records-page',
        'fi-resource-' . str_replace('/', '-', $this->getResource()::getSlug()),
    ])
>
    <div class="flex flex-col gap-y-6">
        <x-filament-panels::resources.tabs />

        <div
            x-data="mwYajraTable(@js($config))"
            class="mw-yajra fi-ta-ctn divide-y divide-gray-200 overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:divide-white/10 dark:bg-gray-900 dark:ring-white/10"
        >
            @if ($isFilterable)
                <div class="mw-yajra-filters px-4 py-3 sm:px-6">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <x-filament::button
                            color="gray"
                            size="sm"
                            icon="heroicon-m-funnel"
                            outlined
                            x-on:click="filtersOpen = ! filtersOpen"
                            x-bind:aria-expanded="filtersOpen.toString()"
                        >
                            Filters
                            @if ($activeFilters)
                                <x-filament::badge size="xs" class="ms-1">{{ $activeFilters }}</x-filament::badge>
                            @endif
                        </x-filament::button>

                        @if ($activeFilters)
                            <x-filament::link tag="button" color="danger" size="sm" wire:click="removeTableFilters">
                                Reset filters
                            </x-filament::link>
                        @endif
                    </div>

                    <div x-show="filtersOpen" x-collapse x-cloak class="pt-4">
                        {{ $this->getTableFiltersForm() }}
                    </div>
                </div>
            @endif

            @if ($bulkActions)
                <div x-show="selected.length" x-cloak class="mw-yajra-bulk flex flex-wrap items-center gap-3 bg-gray-50 px-4 py-2 sm:px-6 dark:bg-white/5">
                    <span class="text-sm font-medium text-gray-700 dark:text-gray-200" x-text="selected.length + ' selected'"></span>
                    @foreach ($bulkActions as $action)
                        {{-- @js() is not compiled inside component attributes, so the name is encoded here. --}}
                        <x-filament::button size="sm" color="primary" x-on:click="bulk({{ \Illuminate\Support\Js::from($action['name']) }})">
                            {{ $action['label'] }}
                        </x-filament::button>
                    @endforeach
                    <x-filament::link tag="button" color="gray" size="sm" x-on:click="clearSelection()">Clear selection</x-filament::link>
                </div>
            @endif

            <div wire:ignore class="mw-yajra-body overflow-x-auto px-4 py-3 sm:px-6">
                <table x-ref="table" class="display w-full"></table>
            </div>
        </div>
    </div>

    {{--
        The modals of the header actions (New …), the row actions (Edit, Delete, …)
        and the bulk actions. A Filament page with a table leaves them to the
        table's markup, which this page replaces with DataTables, so they are
        rendered here; without them no action could open.
    --}}
    <x-filament-actions::modals />

</x-filament-panels::page>
