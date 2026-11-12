<?php

namespace App\Filament\Resources\FlashcardResource\Pages;

use App\Filament\Concerns\UsesYajraTable;
use App\Filament\Resources\FlashcardResource;
use App\Models\Category;
use App\Models\Exam;
use App\Models\Flashcard;
use App\Models\Question;
use App\Services\FlashcardService;
use Filament\Actions;
use Filament\Forms;
use Filament\Forms\Get;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ManageRecords;
use Filament\Support\Enums\MaxWidth;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\HtmlString;

class ManageFlashcards extends ManageRecords
{
    use UsesYajraTable;

    protected static string $resource = FlashcardResource::class;

    public function getSubheading(): HtmlString
    {
        return new HtmlString('Facts are what students answer in the <strong>Knowledge Tutor</strong>: a short question on the front, the fact to remember on the back. '
            .'The tutor asks the most high-yield facts first (A++ before C) and brings each one back with spaced repetition. '
            .'Difficulty is worked out from students\' answers. Quickest start: <strong>Make facts from MCQs</strong>.');
    }

    /** @return array<Actions\Action> */
    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('fromQuestions')->label('Make facts from MCQs')->icon('heroicon-o-sparkles')->color('gray')
                ->authorize(fn (): bool => auth()->user()?->can('create', Flashcard::class) ?? false)
                ->modalHeading('Make facts from MCQs')
                ->modalDescription('Each published MCQ becomes one fact: its question on the front, the answer and key points on the back. MCQs that already have a fact are skipped, so it is safe to run again after new uploads.')
                ->modalSubmitActionLabel('Make facts')
                ->form([
                    Forms\Components\Select::make('exam_id')->label('Exam')->required()->live()
                        ->options(fn (): array => Exam::whereNull('content_exam_id')->orderBy('sort_order')->pluck('name', 'id')->all()),
                    Forms\Components\Select::make('category_id')->label('Subject')->placeholder('All subjects')
                        ->options(fn (Get $get): array => Category::where('exam_id', $get('exam_id'))->orderBy('name')->pluck('name', 'id')->all()),
                    Forms\Components\Toggle::make('only_key_points')->label('Only MCQs that have key points')->default(true)
                        ->helperText('Key points make the best facts. Switch off to use the start of the explanation when there are none.'),
                ])
                ->action(function (array $data, FlashcardService $cards): void {
                    $made = 0;
                    $skipped = 0;
                    Question::query()->where('exam_id', $data['exam_id'])->where('is_published', true)
                        ->when($data['category_id'] ?? null, fn (Builder $query, $id) => $query->where('category_id', $id))
                        ->when($data['only_key_points'] ?? true, fn (Builder $query) => $query->whereNotNull('key_points')->where('key_points', '!=', ''))
                        ->chunkById(200, function ($questions) use ($cards, &$made, &$skipped): void {
                            foreach ($questions as $question) {
                                $cards->fromQuestion($question)->wasRecentlyCreated ? $made++ : $skipped++;
                            }
                        });
                    Notification::make()->success()->title($made.' facts made'.($skipped > 0 ? ', '.$skipped.' MCQs already had one' : ''))->send();
                }),
            Actions\CreateAction::make()->label('New fact')->icon('heroicon-o-plus')->modalWidth(MaxWidth::ThreeExtraLarge),
        ];
    }
}
