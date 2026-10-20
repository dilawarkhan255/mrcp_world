<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PastPaperResource\Pages\ManagePastPapers;
use App\Models\PastPaper;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Resources\Pages\PageRegistration;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Validation\Rules\Unique;

class PastPaperResource extends Resource
{
    protected static ?string $model = PastPaper::class;

    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    protected static ?string $navigationGroup = 'Content';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('exam_id')->relationship('exam', 'name')->required()->live(),
            Forms\Components\TextInput::make('title')->required()->maxLength(255),
            Forms\Components\TextInput::make('slug')->required()->maxLength(255)->regex('/^[a-z0-9]+(?:-[a-z0-9]+)*$/')->unique(ignoreRecord: true, modifyRuleUsing: fn (Unique $rule, Get $get): Unique => $rule->where('exam_id', $get('exam_id'))),
            Forms\Components\TextInput::make('year')->numeric()->required()->minValue(1900)->maxValue((int) date('Y') + 1),
            Forms\Components\Toggle::make('is_published')->label('Published — visible to students')->default(false),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('title')->searchable()->sortable(),
            Tables\Columns\TextColumn::make('exam.name'),
            Tables\Columns\TextColumn::make('year')->sortable(),
            Tables\Columns\IconColumn::make('is_published')->boolean(),
        ])->actions([Tables\Actions\EditAction::make(), Tables\Actions\DeleteAction::make()])->defaultSort('year', 'desc')->paginationPageOptions([25, 50, 100]);
    }

    /** @return array<string, PageRegistration> */
    public static function getPages(): array
    {
        return ['index' => ManagePastPapers::route('/')];
    }
}
