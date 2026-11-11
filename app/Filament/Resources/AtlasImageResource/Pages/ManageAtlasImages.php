<?php

namespace App\Filament\Resources\AtlasImageResource\Pages;

use App\Filament\Concerns\UsesYajraTable;
use App\Filament\Resources\AtlasImageResource;
use App\Models\AtlasImage;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ManageRecords;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ManageAtlasImages extends ManageRecords
{
    use UsesYajraTable;

    protected static string $resource = AtlasImageResource::class;

    /** @return array<Actions\Action> */
    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()->label('Add image'),
            Actions\Action::make('bulkUpload')
                ->label('Bulk upload')
                ->icon('heroicon-m-arrow-up-tray')
                ->color('gray')
                ->authorize(fn (): bool => auth()->user()?->can('create', AtlasImage::class) ?? false)
                ->modalDescription('Upload up to 50 images at a time. Each becomes an unpublished draft titled from its file name; add the diagnosis and check the copyright before publishing.')
                ->form([
                    AtlasImageResource::upload('files', multiple: true)->label('Images')->required(),
                    Forms\Components\Hidden::make('original_names'),
                    Forms\Components\TextInput::make('subject')->maxLength(100),
                    ...AtlasImageResource::copyrightFields(),
                ])
                ->action(function (array $data): void {
                    $names = (array) ($data['original_names'] ?? []);
                    $count = DB::transaction(function () use ($data, $names): int {
                        $count = 0;
                        foreach ((array) $data['files'] as $path) {
                            $original = (string) ($names[$path] ?? pathinfo((string) $path, PATHINFO_FILENAME));
                            $title = Str::of(pathinfo($original, PATHINFO_FILENAME))->replace(['_', '-'], ' ')->squish()->title()->limit(255, '')->toString() ?: 'Untitled image';
                            AtlasImage::create([
                                'title' => $title, 'image_path' => (string) $path, 'subject' => $data['subject'] ?? null,
                                'source_name' => $data['source_name'] ?? null, 'source_url' => $data['source_url'] ?? null,
                                'license' => $data['license'] ?? 'unknown', 'attribution' => $data['attribution'] ?? null,
                                'copyright_confirmed' => false, 'is_published' => false,
                            ]);
                            $count++;
                        }

                        return $count;
                    });
                    Notification::make()->success()->title($count.' images added as drafts')->body('Confirm the copyright of each image before publishing.')->send();
                }),
        ];
    }
}
