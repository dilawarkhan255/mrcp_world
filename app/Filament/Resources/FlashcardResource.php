<?php

namespace App\Filament\Resources;

use App\Filament\Resources\FlashcardResource\Pages\ManageFlashcards;
use App\Filament\Support\CrudActions;
use App\Models\Flashcard;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\PageRegistration;
use Filament\Resources\Resource;
use Filament\Support\Enums\MaxWidth;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;

/**
 * Knowledge Tutor facts (stored as flashcards): a short question on the front,
 * the fact on the back, a high-yield rating and a difficulty from students' answers.
 */
class FlashcardResource extends Resource
{
    protected static ?string $model = Flashcard::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    protected static ?string $navigationGroup = 'Content';

    protected static ?string $navigationLabel = 'Knowledge Tutor facts';

    protected static ?string $modelLabel = 'fact';

    protected static ?int $navigationSort = 7;

    public static function form(Form $form): Form
    {
        $toolbar = ['bold', 'italic', 'strike', 'link', 'bulletList', 'orderedList', 'table', 'undo', 'redo'];

        return $form->schema([
            Forms\Components\Select::make('exam_id')->label('Exam')
                ->relationship('exam', 'name', modifyQueryUsing: fn (Builder $query) => $query->whereNull('content_exam_id'))->required()->live(),
            Forms\Components\Select::make('category_id')->label('Subject')
                ->relationship('category', 'name', modifyQueryUsing: fn (Builder $query, Get $get) => $query->where('exam_id', $get('exam_id')))
                ->searchable()->preload()->placeholder('General'),
            Forms\Components\MarkdownEditor::make('front')->label('Front: the question the student sees')->required()->maxLength(5000)->toolbarButtons($toolbar)->columnSpanFull(),
            Forms\Components\MarkdownEditor::make('back')->label('Back: the fact to remember')->required()->maxLength(10000)->toolbarButtons($toolbar)->columnSpanFull()
                ->helperText('Keep it short: the fact to remember. Cite NICE and WHO guidance as published; never guess a title or year.'),
            Forms\Components\Select::make('high_yield')->label('High-yield rating')->options(Flashcard::HIGH_YIELD)->default('A')->required()->native(false)
                ->helperText('How often it comes up in the exam. The tutor asks A++ facts first.'),
            Forms\Components\Placeholder::make('difficulty_info')->label('Difficulty')
                ->content(fn (?Flashcard $record): string => $record?->difficulty === null ? 'Worked out once students have answered it a few times.' : $record->difficulty.'% of answers were "forgot"'),
            Forms\Components\TextInput::make('sort_order')->label('Order in subject')->numeric()->minValue(0)->default(0),
            Forms\Components\Toggle::make('is_published')->label('Published — visible to subscribers')->default(false),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('front')->label('Front')->searchable()->weight('bold')
                ->formatStateUsing(fn (string $state): string => Str::limit(self::plain($state), 90))
                ->description(fn (Flashcard $record): string => Str::limit(self::plain($record->back), 110)),
            Tables\Columns\TextColumn::make('category.name')->label('Subject')->placeholder('General')->badge()->color('gray'),
            Tables\Columns\TextColumn::make('high_yield')->label('High-yield')->badge()->sortable()
                ->color(fn (string $state): string => match ($state) {
                    'A++' => 'danger', 'A+' => 'warning', 'A' => 'success', default => 'gray'
                }),
            Tables\Columns\TextColumn::make('difficulty')->label('Difficulty')->sortable()->alignCenter()
                ->formatStateUsing(fn (?int $state): string => $state === null ? '—' : $state.'%')->placeholder('—'),
            Tables\Columns\TextColumn::make('exam.name')->toggleable(),
            Tables\Columns\IconColumn::make('question_id')->label('From MCQ')->boolean()->getStateUsing(fn (Flashcard $record): bool => $record->question_id !== null),
            Tables\Columns\IconColumn::make('is_published')->label('Live')->boolean(),
            Tables\Columns\TextColumn::make('updated_at')->since()->sortable()->toggleable(),
        ])->filters([
            Tables\Filters\SelectFilter::make('exam')->relationship('exam', 'name', fn (Builder $query) => $query->whereNull('content_exam_id')),
            Tables\Filters\SelectFilter::make('category')->label('Subject')->relationship('category', 'name')->searchable(),
            Tables\Filters\SelectFilter::make('high_yield')->label('High-yield')->options(Flashcard::HIGH_YIELD),
            Tables\Filters\TernaryFilter::make('is_published')->label('Published'),
        ])->actions([
            CrudActions::view()->modalWidth(MaxWidth::ThreeExtraLarge),
            Tables\Actions\EditAction::make()->modalWidth(MaxWidth::ThreeExtraLarge),
            Tables\Actions\DeleteAction::make(),
        ])->bulkActions([
            Tables\Actions\BulkAction::make('publish')->label('Publish')->icon('heroicon-o-eye')->color('success')
                ->action(function (Collection $records): void {
                    Flashcard::whereIn('id', $records->modelKeys())->update(['is_published' => true]);
                    Notification::make()->success()->title($records->count().' published')->send();
                }),
            Tables\Actions\BulkAction::make('unpublish')->label('Unpublish')->icon('heroicon-o-eye-slash')->color('gray')
                ->action(function (Collection $records): void {
                    Flashcard::whereIn('id', $records->modelKeys())->update(['is_published' => false]);
                    Notification::make()->success()->title($records->count().' unpublished')->send();
                }),
            Tables\Actions\BulkAction::make('setHighYield')->label('Set high-yield rating')->icon('heroicon-o-star')->color('warning')
                ->form([Forms\Components\Select::make('high_yield')->label('High-yield rating')->options(Flashcard::HIGH_YIELD)->required()])
                ->action(function (Collection $records, array $data): void {
                    Flashcard::whereIn('id', $records->modelKeys())->update(['high_yield' => $data['high_yield']]);
                    Notification::make()->success()->title($records->count().' facts set to '.$data['high_yield'])->send();
                }),
            CrudActions::bulkDelete(),
        ])->modifyQueryUsing(fn (Builder $query) => $query->with(['exam:id,name', 'category:id,name']))
            ->defaultSort('id', 'desc')->paginationPageOptions([25, 50, 100]);
    }

    /** @return array<string, PageRegistration> */
    public static function getPages(): array
    {
        return ['index' => ManageFlashcards::route('/')];
    }

    /** Markdown as plain text for the list (no **, #, ` or tags). */
    private static function plain(string $markdown): string
    {
        return trim((string) preg_replace('/\s+/', ' ', str_replace(['**', '__', '`', '#', '>'], '', strip_tags($markdown))));
    }
}
