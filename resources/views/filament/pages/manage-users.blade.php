<x-filament-panels::page>
    @php
        $columns = ['id', 'name', 'email', 'roles', 'created_at'];
    @endphp
    <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-900">
        <table
            data-yajra-table
            data-source="{{ route('admin.users.data') }}"
            data-columns="{{ implode(',', $columns) }}"
            data-order-column="0"
            data-order-direction="desc"
            class="w-full text-left text-sm"
        >
            <thead>
                <tr>
                    <th scope="col">Name</th>
                    <th scope="col">Email</th>
                    <th scope="col">Roles</th>
                    <th scope="col">Registered</th>
                </tr>
            </thead>
            <tbody></tbody>
        </table>
    </div>

    @vite(['resources/js/admin-datatables.ts'])
</x-filament-panels::page>
