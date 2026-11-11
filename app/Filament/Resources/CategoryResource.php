<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CategoryResource\Pages;
use App\Filament\Support\CrudActions;
use App\Models\Category;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Resources\Pages\PageRegistration;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rules\Unique;

class CategoryResource extends Resource
{
    protected static ?string $model = Category::class;

    protected static ?string $navigationIcon = 'heroicon-o-folder';

    protected static ?string $navigationGroup = 'Content';

    protected static ?int $navigationSort = 2;

    /** @return list<string> */
    public static function getGloballySearchableAttributes(): array
    {
        return ['name', 'slug'];
    }

    protected static int $globalSearchResultsLimit = 5;

    public static function getGlobalSearchResultTitle(Model $record): string
    {
        /** @var Category $record */
        return (string) ($record->name);
    }

    /** @return array<string, string> */
    public static function getGlobalSearchResultDetails(Model $record): array
    {
        /** @var Category $record */
        return ['Exam' => $record->exam->name ?? '—'];
    }

    /** @return Builder<Category> */
    public static function getGlobalSearchEloquentQuery(): Builder
    {
        return parent::getGlobalSearchEloquentQuery()->with(['exam:id,name']);
    }

    /** These resources have no edit page of their own: open the list, searched for the record. */
    public static function getGlobalSearchResultUrl(Model $record): string
    {
        /** @var Category $record */
        return static::getUrl('index', ['tableSearch' => $record->name]);
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('exam_id')->relationship('exam', 'name', modifyQueryUsing: fn (Builder $query) => $query->whereNull('content_exam_id'))->required()->searchable()->preload()->live(),
            Forms\Components\TextInput::make('name')->required()->maxLength(255),
            Forms\Components\TextInput::make('slug')->required()->maxLength(255)->regex('/^[a-z0-9]+(?:-[a-z0-9]+)*$/')
                ->unique(ignoreRecord: true, modifyRuleUsing: fn (Unique $rule, Get $get): Unique => $rule->where('exam_id', $get('exam_id'))),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('name')->searchable()->sortable(),
            Tables\Columns\TextColumn::make('exam.name')->searchable()->sortable(),
            Tables\Columns\TextColumn::make('slug')->searchable(),
        ])->filters([
            Tables\Filters\SelectFilter::make('exam')->relationship('exam', 'name'),
        ])->actions([
            CrudActions::view(),
            Tables\Actions\EditAction::make(),
            Tables\Actions\DeleteAction::make()->visible(self::deletable()),
            CrudActions::deleteWithContents(self::deletable(), 'name'),
        ])->bulkActions([CrudActions::bulkDelete(self::deletable()), CrudActions::bulkDeleteWithContents()]);
    }

    /** @return \Closure(Model): bool A subject that still has MCQs stays. */
    public static function deletable(): \Closure
    {
        return fn (Model $record): bool => ! DB::table('questions')->where('category_id', $record->getKey())->exists();
    }

    /** @return array<string, PageRegistration> */
    public static function getPages(): array
    {
        return ['index' => Pages\ManageCategories::route('/')];
    }
}
