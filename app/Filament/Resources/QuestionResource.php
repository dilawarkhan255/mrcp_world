<?php

namespace App\Filament\Resources;

use App\Filament\Resources\QuestionResource\Pages\ManageQuestions;
use App\Models\Question;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Resources\Pages\PageRegistration;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Rules\Unique;

class QuestionResource extends Resource
{
    protected static ?string $model = Question::class;

    protected static ?string $navigationIcon = 'heroicon-o-question-mark-circle';

    protected static ?string $navigationGroup = 'Content';

    public static function form(Form $form): Form
    {
        $fields = [
            Forms\Components\Select::make('exam_id')->relationship('exam', 'name')->required()->live(),
            Forms\Components\Select::make('category_id')->relationship('category', 'name', modifyQueryUsing: fn (Builder $query, Get $get) => $query->where('exam_id', $get('exam_id')))->required()->searchable()->optionsLimit(50),
            Forms\Components\Select::make('past_paper_id')->relationship('pastPaper', 'title', modifyQueryUsing: fn (Builder $query, Get $get) => $query->where('exam_id', $get('exam_id')))->searchable()->optionsLimit(50),
            Forms\Components\TextInput::make('code')->required()->maxLength(100)->regex('/^[A-Za-z0-9._-]+$/')->helperText('Unique within this exam. Reimports update the matching code.')
                ->unique(ignoreRecord: true, modifyRuleUsing: fn (Unique $rule, Get $get): Unique => $rule->where('exam_id', $get('exam_id'))),
            Forms\Components\Textarea::make('body')->label('Question')->required()->maxLength(20000)->rows(5)->columnSpanFull(),
        ];
        foreach (['A', 'B', 'C', 'D', 'E'] as $key) {
            $fields[] = Forms\Components\Textarea::make('options.'.$key)->label('Option '.$key)->required(in_array($key, ['A', 'B']))->maxLength(4000)->rows(2);
        }

        return $form->schema([...$fields,
            Forms\Components\Select::make('correct_option')->options(array_combine(['A', 'B', 'C', 'D', 'E'], ['A', 'B', 'C', 'D', 'E']))->required(),
            Forms\Components\Textarea::make('explanation')->maxLength(20000)->rows(4)->columnSpanFull(),
            Forms\Components\Toggle::make('is_published')->label('Published — visible to students')->default(false),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('code')->searchable(query: fn (Builder $query, string $search) => $query->where('code', 'like', $search.'%'))->sortable(),
            Tables\Columns\TextColumn::make('body')->label('Question')->limit(65),
            Tables\Columns\TextColumn::make('exam.name'),
            Tables\Columns\TextColumn::make('category.name'),
            Tables\Columns\TextColumn::make('pastPaper.title')->placeholder('Question bank'),
            Tables\Columns\IconColumn::make('is_published')->boolean(),
        ])->filters([
            Tables\Filters\SelectFilter::make('exam')->relationship('exam', 'name'),
            Tables\Filters\TernaryFilter::make('is_published')->label('Published'),
        ])->actions([Tables\Actions\EditAction::make()->mutateRecordDataUsing(fn (array $data, Question $record): array => [...$data, 'correct_option' => $record->correct_option, 'explanation' => $record->explanation]), Tables\Actions\DeleteAction::make()->label('Archive')])->defaultSort('id', 'desc')->paginationPageOptions([25, 50, 100]);
    }

    /** @return array<string, PageRegistration> */
    public static function getPages(): array
    {
        return ['index' => ManageQuestions::route('/')];
    }
}
