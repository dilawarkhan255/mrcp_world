<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AtlasImageResource\Pages\ManageAtlasImages;
use App\Filament\Support\CrudActions;
use App\Models\AtlasImage;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\PageRegistration;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

class AtlasImageResource extends Resource
{
    protected static ?string $model = AtlasImage::class;

    protected static ?string $navigationIcon = 'heroicon-o-photo';

    protected static ?string $navigationGroup = 'Media';

    protected static ?string $navigationLabel = 'Image Atlas';

    protected static ?string $modelLabel = 'atlas image';

    protected static ?int $navigationSort = 1;

    public static function getNavigationBadge(): ?string
    {
        $pending = AtlasImage::where('copyright_confirmed', false)->count();

        return $pending > 0 ? (string) $pending : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return 'Images waiting for a copyright check';
    }

    /** The upload field shared by the form and the bulk upload action. */
    public static function upload(string $name, bool $multiple = false): Forms\Components\FileUpload
    {
        $field = Forms\Components\FileUpload::make($name)
            ->disk(AtlasImage::DISK)->directory(AtlasImage::DIRECTORY)->visibility('public')
            ->image()->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp', 'image/gif'])->maxSize(5120)
            ->getUploadedFileNameForStorageUsing(fn (TemporaryUploadedFile $file): string => Str::uuid().'.'.strtolower($file->getClientOriginalExtension()));

        return $multiple ? $field->multiple()->maxFiles(50)->panelLayout('grid')->storeFileNamesIn('original_names') : $field;
    }

    /** @return list<Forms\Components\Component> */
    public static function copyrightFields(): array
    {
        return [
            Forms\Components\TextInput::make('source_name')->label('Source')->maxLength(255)->placeholder('e.g. DermNet, own collection'),
            Forms\Components\TextInput::make('source_url')->label('Source link')->url()->maxLength(2048),
            Forms\Components\Select::make('license')->options(AtlasImage::LICENSES)->default('unknown')->required()->live(),
            Forms\Components\TextInput::make('attribution')->maxLength(500)->placeholder('Credit line required by the licence')
                ->required(fn (Get $get): bool => in_array($get('license'), ['cc-by', 'cc-by-sa', 'cc-by-nc'], true)),
        ];
    }

    /** @return list<string> */
    public static function getGloballySearchableAttributes(): array
    {
        return ['title', 'diagnosis'];
    }

    protected static int $globalSearchResultsLimit = 5;

    public static function getGlobalSearchResultTitle(Model $record): string
    {
        /** @var AtlasImage $record */
        return (string) ($record->title);
    }

    /** @return array<string, string> */
    public static function getGlobalSearchResultDetails(Model $record): array
    {
        /** @var AtlasImage $record */
        return ['Diagnosis' => $record->diagnosis ?? '—'];
    }

    /** @return Builder<AtlasImage> */
    public static function getGlobalSearchEloquentQuery(): Builder
    {
        return parent::getGlobalSearchEloquentQuery()->with([]);
    }

    /** These resources have no edit page of their own: open the list, searched for the record. */
    public static function getGlobalSearchResultUrl(Model $record): string
    {
        /** @var AtlasImage $record */
        return static::getUrl('index', ['tableSearch' => $record->title]);
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Image')->columns(2)->schema([
                self::upload('image_path')->label('Image file')->required()->imageEditor()->columnSpanFull(),
                Forms\Components\TextInput::make('title')->required()->maxLength(255),
                Forms\Components\TextInput::make('diagnosis')->maxLength(255),
                Forms\Components\TextInput::make('subject')->maxLength(100)->datalist(fn (): array => AtlasImage::query()->whereNotNull('subject')->distinct()->orderBy('subject')->limit(50)->pluck('subject')->all()),
                Forms\Components\TextInput::make('system')->maxLength(100),
                Forms\Components\Select::make('qbank_id')->label('Qbank')->relationship('qbank', 'name')->placeholder('All Qbanks'),
                Forms\Components\Textarea::make('description')->label('Findings and teaching points')->maxLength(8000)->rows(4)->columnSpanFull(),
            ]),
            Forms\Components\Section::make('Copyright')->description('Images can come from other websites. Record the source and licence, and only confirm when you are sure we may use the image.')
                ->columns(2)->schema([
                    ...self::copyrightFields(),
                    Forms\Components\Toggle::make('copyright_confirmed')->label('I confirm we have the right to use this image')->live(),
                    Forms\Components\Toggle::make('is_published')->label('Published — visible to students')
                        ->disabled(fn (Get $get): bool => ! $get('copyright_confirmed') || $get('license') === 'unknown')
                        ->helperText('Requires a confirmed copyright and a known licence.'),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\ImageColumn::make('image_path')->label('')->disk(AtlasImage::DISK)->height(48)->width(64)->extraImgAttributes(['loading' => 'lazy', 'class' => 'rounded-md object-cover']),
            Tables\Columns\TextColumn::make('title')->searchable(query: fn (Builder $query, string $search) => $query->where(fn (Builder $query) => $query->where('title', 'like', '%'.$search.'%')->orWhere('diagnosis', 'like', '%'.$search.'%')))->weight('bold')->description(fn (AtlasImage $record): ?string => $record->diagnosis),
            Tables\Columns\TextColumn::make('subject')->badge()->color('gray')->sortable(),
            Tables\Columns\TextColumn::make('license')->formatStateUsing(fn (string $state): string => AtlasImage::LICENSES[$state] ?? $state)->color(fn (string $state): string => $state === 'unknown' ? 'danger' : 'gray'),
            Tables\Columns\IconColumn::make('copyright_confirmed')->label('Rights')->boolean(),
            Tables\Columns\IconColumn::make('is_published')->label('Live')->boolean(),
        ])->filters([
            Tables\Filters\SelectFilter::make('subject')->options(fn (): array => AtlasImage::query()->whereNotNull('subject')->distinct()->orderBy('subject')->pluck('subject', 'subject')->all()),
            Tables\Filters\SelectFilter::make('license')->options(AtlasImage::LICENSES),
            Tables\Filters\TernaryFilter::make('copyright_confirmed')->label('Copyright confirmed'),
            Tables\Filters\TernaryFilter::make('is_published')->label('Published'),
        ])->actions([
            CrudActions::view(slideOver: true),
            Tables\Actions\EditAction::make()->slideOver(),
            Tables\Actions\DeleteAction::make(),
        ])->bulkActions([
            Tables\Actions\BulkAction::make('publish')->label('Publish')->icon('heroicon-o-eye')->requiresConfirmation()
                ->modalDescription('Only images with a confirmed copyright and a known licence are published. The others are skipped.')
                ->action(function (Collection $records): void {
                    $ready = $records->filter(fn (Model $image): bool => $image instanceof AtlasImage && $image->copyright_confirmed && $image->license !== 'unknown');
                    AtlasImage::whereIn('id', $ready->modelKeys())->update(['is_published' => true]);
                    Notification::make()->success()->title($ready->count().' published, '.($records->count() - $ready->count()).' skipped')->send();
                }),
            Tables\Actions\BulkAction::make('unpublish')->label('Unpublish')->icon('heroicon-o-eye-slash')
                ->action(fn (Collection $records) => AtlasImage::whereIn('id', $records->modelKeys())->update(['is_published' => false])),
            CrudActions::bulkDelete(),
        ])->defaultSort('id', 'desc')->paginationPageOptions([24, 48, 96]);
    }

    /** @return array<string, PageRegistration> */
    public static function getPages(): array
    {
        return ['index' => ManageAtlasImages::route('/')];
    }
}
