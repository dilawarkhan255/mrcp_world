<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ExamResource\Pages;
use App\Filament\Support\CrudActions;
use App\Models\Exam;
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

class ExamResource extends Resource
{
    protected static ?string $model = Exam::class;

    protected static ?string $navigationIcon = 'heroicon-o-academic-cap';

    protected static ?string $navigationGroup = 'Content';

    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('name')->required()->maxLength(255),
            Forms\Components\TextInput::make('slug')->required()->maxLength(255)->regex('/^[a-z0-9]+(?:-[a-z0-9]+)*$/')->unique(ignoreRecord: true),
            Forms\Components\Select::make('qbank_id')->label('Qbank')->relationship('qbank', 'name')->preload()->live()
                ->required(fn (Get $get): bool => filled($get('content_exam_id'))),
            Forms\Components\Select::make('content_exam_id')->label('Uses the question bank of')
                ->relationship('contentExam', 'name', modifyQueryUsing: fn (Builder $query, Get $get, ?Exam $record) => $query
                    ->whereNull('content_exam_id')
                    ->where('qbank_id', $get('qbank_id'))
                    ->when($record, fn (Builder $query) => $query->whereKeyNot($record->id)))
                ->helperText('Leave empty when this exam has its own subjects and questions. Set it only for an exam that should show the questions of another exam.'),
            Forms\Components\TextInput::make('code')->label('Tile code')->maxLength(4)->helperText('Up to 4 letters shown on the landing page.'),
            Forms\Components\TextInput::make('sort_order')->numeric()->minValue(0)->default(0),
            Forms\Components\Textarea::make('description')->maxLength(1000)->rows(2)->columnSpanFull(),
            Forms\Components\Toggle::make('is_active')->default(true),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('name')->searchable()->sortable()->weight('bold'),
            Tables\Columns\TextColumn::make('qbank.name')->label('Qbank')->badge()->placeholder('—'),
            Tables\Columns\TextColumn::make('contentExam.name')->label('Question bank')->placeholder('Own content'),
            Tables\Columns\TextColumn::make('slug')->searchable()->toggleable(),
            Tables\Columns\TextColumn::make('sort_order')->label('Order')->sortable(),
            Tables\Columns\IconColumn::make('is_active')->boolean(),
        ])->actions([
            CrudActions::view(),
            Tables\Actions\EditAction::make(),
            Tables\Actions\DeleteAction::make()->visible(self::deletable()),
            CrudActions::deleteWithContents(self::deletable(), 'name'),
        ])->bulkActions([CrudActions::bulkDelete(self::deletable()), CrudActions::bulkDeleteWithContents()])
            ->defaultSort('sort_order')->modifyQueryUsing(fn (Builder $query) => $query->with(['qbank:id,name', 'contentExam:id,name']));
    }

    /** @return \Closure(Model): bool Only an exam nothing refers to may go. */
    public static function deletable(): \Closure
    {
        return function (Model $record): bool {
            foreach (['categories', 'past_papers', 'questions', 'textbook_chapters', 'practice_sessions', 'question_imports'] as $table) {
                if (DB::table($table)->where('exam_id', $record->getKey())->exists()) {
                    return false;
                }
            }

            return ! DB::table('exams')->where('content_exam_id', $record->getKey())->exists();
        };
    }

    /** @return array<string, PageRegistration> */
    public static function getPages(): array
    {
        return ['index' => Pages\ManageExams::route('/')];
    }
}
