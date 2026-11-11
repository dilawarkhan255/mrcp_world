<?php

namespace App\Filament\Resources;

use App\Filament\Resources\QbankResource\Pages;
use App\Filament\Support\CrudActions;
use App\Models\Qbank;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Pages\PageRegistration;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Qbanks are the products students subscribe to. Each groups one or more exams.
 */
class QbankResource extends Resource
{
    protected static ?string $model = Qbank::class;

    protected static ?string $navigationIcon = 'heroicon-o-squares-2x2';

    protected static ?string $navigationGroup = 'Content';

    protected static ?string $navigationLabel = 'Qbanks';

    protected static ?string $modelLabel = 'Qbank';

    protected static ?int $navigationSort = 0;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('name')->required()->maxLength(255),
            Forms\Components\TextInput::make('slug')->required()->maxLength(255)->regex('/^[a-z0-9]+(?:-[a-z0-9]+)*$/')->unique(ignoreRecord: true),
            Forms\Components\Textarea::make('description')->maxLength(1000)->rows(2)->columnSpanFull()->helperText('For example "MRCP 2".'),
            Forms\Components\TextInput::make('sort_order')->numeric()->minValue(0)->default(0),
            Forms\Components\Toggle::make('is_active')->default(true),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('name')->searchable()->sortable()->weight('bold'),
            Tables\Columns\TextColumn::make('exams.name')->label('Exams')->badge()->separator(','),
            Tables\Columns\TextColumn::make('description')->limit(60)->toggleable(),
            Tables\Columns\TextColumn::make('sort_order')->label('Order')->sortable(),
            Tables\Columns\IconColumn::make('is_active')->boolean(),
        ])->actions([
            CrudActions::view(),
            Tables\Actions\EditAction::make(),
            Tables\Actions\DeleteAction::make()->visible(self::deletable()),
            CrudActions::deleteWithContents(self::deletable(), 'name'),
        ])->bulkActions([CrudActions::bulkDelete(self::deletable()), CrudActions::bulkDeleteWithContents()])
            ->defaultSort('sort_order')->modifyQueryUsing(fn ($query) => $query->with('exams:id,qbank_id,name'));
    }

    /** @return \Closure(Model): bool A Qbank with exams or subscriptions stays. */
    public static function deletable(): \Closure
    {
        return fn (Model $record): bool => ! DB::table('exams')->where('qbank_id', $record->getKey())->exists()
            && ! DB::table('subscriptions')->where('qbank_id', $record->getKey())->exists();
    }

    /** @return array<string, PageRegistration> */
    public static function getPages(): array
    {
        return ['index' => Pages\ManageQbanks::route('/')];
    }
}
