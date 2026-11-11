<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PlanResource\Pages\ManagePlans;
use App\Filament\Support\CrudActions;
use App\Models\Plan;
use App\Models\Subscription;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Pages\PageRegistration;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class PlanResource extends Resource
{
    protected static ?string $model = Plan::class;

    protected static ?string $navigationIcon = 'heroicon-o-banknotes';

    protected static ?string $navigationGroup = 'Users and billing';

    protected static ?int $navigationSort = 3;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('qbank_id')->label('Qbank')->relationship('qbank', 'name')->required(),
            Forms\Components\TextInput::make('name')->required()->maxLength(255),
            Forms\Components\TextInput::make('price')->numeric()->minValue(0)->required(),
            Forms\Components\Select::make('currency')->options(['GBP' => 'GBP (£)', 'USD' => 'USD ($)', 'EUR' => 'EUR (€)', 'PKR' => 'PKR'])->default('GBP')->required(),
            Forms\Components\TextInput::make('duration_days')->label('Days of access')->numeric()->minValue(1)->maxValue(3650)->default(180)->required(),
            Forms\Components\TextInput::make('sort_order')->numeric()->minValue(0)->default(0),
            Forms\Components\Toggle::make('is_active')->label('On sale')->default(true),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('name')->weight('bold'),
            Tables\Columns\TextColumn::make('qbank.name')->label('Qbank')->badge()->color('gray')->placeholder('—'),
            Tables\Columns\TextColumn::make('price')->formatStateUsing(fn (Plan $record): string => $record->priceLabel()),
            Tables\Columns\TextColumn::make('duration_days')->label('Access')->formatStateUsing(fn (Plan $record): string => $record->durationLabel()),
            Tables\Columns\IconColumn::make('is_active')->label('On sale')->boolean(),
        ])->actions([
            CrudActions::view(),
            Tables\Actions\EditAction::make(),
            Tables\Actions\DeleteAction::make()->visible(self::deletable()),
            CrudActions::deleteWithContents(self::deletable(), 'name'),
        ])->bulkActions([CrudActions::bulkDelete(self::deletable()), CrudActions::bulkDeleteWithContents()])->defaultSort('sort_order');
    }

    /** @return \Closure(Model): bool A plan with subscriptions stays (switch it off instead). */
    public static function deletable(): \Closure
    {
        return fn (Model $record): bool => ! Subscription::where('plan_id', $record->getKey())->exists();
    }

    /** @return array<string, PageRegistration> */
    public static function getPages(): array
    {
        return ['index' => ManagePlans::route('/')];
    }
}
