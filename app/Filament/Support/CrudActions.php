<?php

namespace App\Filament\Support;

use Closure;
use Filament\Notifications\Notification;
use Filament\Tables\Actions\BulkAction;
use Filament\Tables\Actions\ViewAction;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;

/**
 * The shared row and bulk actions of the admin lists: View, and a bulk
 * delete that never breaks data. Each selected record is checked against
 * its policy and against the resource's "can this be deleted" rule (for
 * example, a subject that still has MCQs); those are skipped and counted.
 */
final class CrudActions
{
    /** Read-only view of a record, in the same form as Edit. */
    public static function view(bool $slideOver = false): ViewAction
    {
        $action = ViewAction::make();

        return $slideOver ? $action->slideOver() : $action;
    }

    /**
     * @param  (Closure(Model): bool)|null  $deletable  The resource's rule; null when every record may go.
     */
    public static function bulkDelete(?Closure $deletable = null, string $label = 'Delete selected', string $done = 'deleted'): BulkAction
    {
        return BulkAction::make('deleteSelected')
            ->label($label)
            ->icon('heroicon-o-trash')
            ->color('danger')
            ->requiresConfirmation()
            ->modalHeading($label.'?')
            ->modalDescription('Records that are still in use, or that you may not delete, are skipped.')
            ->modalSubmitActionLabel($label)
            ->deselectRecordsAfterCompletion()
            ->action(function (Collection $records) use ($deletable, $done): void {
                $deleted = 0;
                $skipped = 0;
                foreach ($records as $record) {
                    if (! (auth()->user()?->can('delete', $record) ?? false) || ($deletable !== null && ! $deletable($record))) {
                        $skipped++;

                        continue;
                    }
                    try {
                        $record->delete();
                        $deleted++;
                    } catch (QueryException) { // @phpstan-ignore catch.neverThrown (a foreign key refusing the delete throws it)
                        // A row another table still points at: leave it.
                        $skipped++;
                    }
                }
                $notification = Notification::make()->title($deleted.' '.$done.($skipped > 0 ? ', '.$skipped.' skipped (still in use or not allowed)' : ''));
                ($skipped > 0 ? $notification->warning() : $notification->success())->send();
            });
    }
}
