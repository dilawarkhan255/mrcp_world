<?php

namespace App\Filament\Support;

use App\Services\AdminDeleter;
use Closure;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Tables\Actions\Action;
use Filament\Tables\Actions\BulkAction;
use Filament\Tables\Actions\ViewAction;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Support\HtmlString;

/**
 * The shared row and bulk actions of the admin lists: View, and a bulk
 * delete that never breaks data. Each selected record is checked against
 * its policy and against the resource's "can this be deleted" rule (for
 * example, a subject that still has MCQs); those are skipped and counted.
 */
final class CrudActions
{
    /** Read-only view of a record, in the same form as Edit (a centred modal, see CrudModal). */
    public static function view(): ViewAction
    {
        return ViewAction::make();
    }

    /** Only the admin role may delete a record together with everything inside it. */
    public static function isAdmin(): bool
    {
        return (bool) auth()->user()?->hasRole('admin');
    }

    /**
     * "Delete with contents" for a record the normal delete refuses (still in
     * use). Admins only; the confirmation lists what else goes and asks for
     * the record's name to be typed.
     *
     * @param  Closure(Model): bool  $deletable  The resource's normal rule (the action shows where it says no).
     * @param  (Closure(Model): bool)|null  $allowed  An extra rule (e.g. never your own account).
     */
    public static function deleteWithContents(Closure $deletable, string $nameAttribute = 'name', ?Closure $allowed = null, string $label = 'Delete with contents'): Action
    {
        return Action::make('deleteWithContents')
            ->label($label)
            ->icon('heroicon-o-trash')
            ->iconButton()
            ->color('danger')
            ->tooltip($label)
            ->visible(fn (Model $record): bool => self::isAdmin() && ! $deletable($record) && ($allowed === null || $allowed($record)))
            ->modalHeading(fn (Model $record): string => 'Delete "'.$record->getAttribute($nameAttribute).'" with everything inside?')
            ->modalDescription(function (Model $record): HtmlString {
                $impact = app(AdminDeleter::class)->impact($record);
                $items = collect($impact)->map(fn (int $count, string $label): string => '<li><strong>'.number_format($count).'</strong> '.e($label).'</li>')->implode('');

                return new HtmlString('<p>This cannot be undone. Also deleted:</p><ul style="margin:.5rem 0 0 1.25rem;list-style:disc">'.($items ?: '<li>nothing else</li>').'</ul>');
            })
            ->form(fn (Model $record): array => [
                TextInput::make('confirm')
                    ->label('Type "'.$record->getAttribute($nameAttribute).'" to confirm')
                    ->required()
                    ->in([(string) $record->getAttribute($nameAttribute)])
                    ->validationMessages(['in' => 'Type the name exactly as shown.']),
            ])
            ->modalSubmitActionLabel('Delete everything')
            ->action(function (Model $record): void {
                abort_unless(self::isAdmin(), 403);
                app(AdminDeleter::class)->delete($record);
                Notification::make()->title('Deleted with everything inside')->success()->send();
            });
    }

    /** The bulk version of deleteWithContents (admins only; type DELETE). */
    public static function bulkDeleteWithContents(?Closure $allowed = null, string $label = 'Delete selected with contents'): BulkAction
    {
        return BulkAction::make('deleteSelectedWithContents')
            ->label($label)
            ->icon('heroicon-o-trash')
            ->color('danger')
            ->visible(fn (): bool => self::isAdmin())
            ->modalHeading('Delete the selected records with everything inside?')
            ->modalDescription('Their MCQs, subjects, papers, sessions and other contents are deleted too. This cannot be undone.')
            ->form([
                TextInput::make('confirm')->label('Type DELETE to confirm')->required()->in(['DELETE'])
                    ->validationMessages(['in' => 'Type DELETE in capital letters.']),
            ])
            ->modalSubmitActionLabel('Delete everything')
            ->deselectRecordsAfterCompletion()
            ->action(function (Collection $records) use ($allowed): void {
                abort_unless(self::isAdmin(), 403);
                $deleted = 0;
                foreach ($records as $record) {
                    if ($allowed !== null && ! $allowed($record)) {
                        continue;
                    }
                    app(AdminDeleter::class)->delete($record);
                    $deleted++;
                }
                Notification::make()->title($deleted.' deleted with everything inside')->success()->send();
            });
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
