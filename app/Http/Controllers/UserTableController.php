<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Yajra\DataTables\Facades\DataTables;

class UserTableController extends Controller
{
    /**
     * Server side rows for the Yajra DataTable in the admin panel.
     */
    public function __invoke(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', User::class);

        $maskEmails = (bool) config('privacy.mask_email_for_editors') && ! auth()->user()?->can('billing.users.manage');

        $baseQuery = User::query()->with('roles');

        // Get total count before any filtering
        $totalRecords = (clone $baseQuery)->count();

        // Apply global search
        $search = $request->input('search.value');
        $query = clone $baseQuery;
        if (is_string($search) && $search !== '') {
            $query->where(function ($q) use ($search): void {
                $q->where('name', 'like', '%'.$search.'%')
                  ->orWhere('email', 'like', '%'.$search.'%');
            });
        }

        // Get filtered count
        $filteredRecords = (clone $query)->count();

        return DataTables::of($query)
            ->addColumn('id', fn (User $user): int => $user->id)
            ->addColumn('name', fn (User $user): string => $user->name)
            ->addColumn('email', fn (User $user): string => $maskEmails ? $this->mask($user->email) : $user->email)
            ->addColumn('roles', fn (User $user): string => $user->roles->pluck('name')->implode(', '))
            ->addColumn('created_at', fn (User $user): string => (string) $user->created_at)
            ->orderColumn('id', 'id $1')
            ->orderColumn('name', 'name $1')
            ->orderColumn('email', 'email $1')
            ->orderColumn('created_at', 'created_at $1')
            ->setTotalRecords($totalRecords)
            ->setFilteredRecords($filteredRecords)
            ->make(true);
    }

    /** Keeps the domain and hides the local part. */
    private function mask(string $email): string
    {
        [$local, $domain] = array_pad(explode('@', $email, 2), 2, '');

        return substr($local, 0, 2).'***@'.$domain;
    }
}
