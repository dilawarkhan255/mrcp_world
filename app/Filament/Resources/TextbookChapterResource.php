<?php

namespace App\Filament\Resources;

use App\Filament\Resources\TextbookChapterResource\Pages\ManageTextbookChapters;
use App\Models\TextbookChapter;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Resources\Pages\PageRegistration;
use Filament\Resources\Resource;
use Filament\Support\Enums\MaxWidth;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Rules\Unique;

class TextbookChapterResource extends Resource
{
    protected static ?string $model = TextbookChapter::class;

    protected static ?string $navigationIcon = 'heroicon-o-book-open';

    protected static ?string $navigationGroup = 'Content';

    protected static ?string $navigationLabel = 'Textbook';

    protected static ?string $modelLabel = 'chapter';

    protected static ?int $navigationSort = 6;

    public static function form(Form $form): Form
    {
        $toolbar = ['bold', 'italic', 'strike', 'link', 'heading', 'bulletList', 'orderedList', 'table', 'attachFiles', 'blockquote', 'undo', 'redo'];

        return $form->schema([
            Forms\Components\Select::make('exam_id')->label('Exam')
                ->relationship('exam', 'name', modifyQueryUsing: fn (Builder $query) => $query->whereNull('content_exam_id'))->required()->live(),
            Forms\Components\Select::make('category_id')->label('Subject')
                ->relationship('category', 'name', modifyQueryUsing: fn (Builder $query, Get $get) => $query->where('exam_id', $get('exam_id')))
                ->searchable()->preload()->placeholder('General'),
            Forms\Components\TextInput::make('title')->required()->maxLength(255),
            Forms\Components\TextInput::make('slug')->maxLength(200)->regex('/^[a-z0-9]+(?:-[a-z0-9]+)*$/')->helperText('Leave empty to build it from the title.')
                ->unique(ignoreRecord: true, modifyRuleUsing: fn (Unique $rule, Get $get): Unique => $rule->where('exam_id', $get('exam_id'))),
            Forms\Components\TextInput::make('summary')->maxLength(500)->columnSpanFull(),
            Forms\Components\MarkdownEditor::make('body')->label('Chapter')->required()->maxLength(200000)->toolbarButtons($toolbar)
                ->fileAttachmentsDisk('public')->fileAttachmentsDirectory('textbook-media')->fileAttachmentsVisibility('public')->columnSpanFull()
                ->helperText('Markdown: headings, tables, images (flowcharts and diagrams as images). Cite NICE and WHO guidance as published.'),
            Forms\Components\MarkdownEditor::make('key_points')->maxLength(8000)->toolbarButtons($toolbar)->columnSpanFull(),
            Forms\Components\TextInput::make('sort_order')->label('Order in subject')->numeric()->minValue(0)->default(0),
            Forms\Components\Toggle::make('is_published')->label('Published — visible to subscribers')->default(false),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('title')->searchable()->weight('bold')->description(fn (TextbookChapter $record): ?string => $record->summary),
            Tables\Columns\TextColumn::make('exam.name')->toggleable(),
            Tables\Columns\TextColumn::make('category.name')->label('Subject')->placeholder('General'),
            Tables\Columns\TextColumn::make('sort_order')->label('Order')->sortable(),
            Tables\Columns\IconColumn::make('is_published')->label('Live')->boolean(),
            Tables\Columns\TextColumn::make('updated_at')->since()->toggleable(),
        ])->filters([
            Tables\Filters\SelectFilter::make('exam')->relationship('exam', 'name', fn (Builder $query) => $query->whereNull('content_exam_id')),
            Tables\Filters\SelectFilter::make('category')->label('Subject')->relationship('category', 'name')->searchable(),
            Tables\Filters\TernaryFilter::make('is_published')->label('Published'),
        ])->actions([
            Tables\Actions\EditAction::make()->slideOver()->modalWidth(MaxWidth::FiveExtraLarge),
            Tables\Actions\DeleteAction::make(),
        ])->modifyQueryUsing(fn (Builder $query) => $query->with(['exam:id,name', 'category:id,name']))
            ->defaultSort('sort_order')->paginationPageOptions([25, 50, 100]);
    }

    /** @return array<string, PageRegistration> */
    public static function getPages(): array
    {
        return ['index' => ManageTextbookChapters::route('/')];
    }
}
