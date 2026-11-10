<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Flashcard;
use App\Models\User;
use App\Services\FlashcardService;
use App\Support\CurrentExam;
use App\Support\QuestionContent;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Flashcards of the selected exam's bank: the decks by subject, a study
 * screen that shows one card at a time, and the student's rating.
 */
class FlashcardController extends Controller
{
    public function index(Request $request, FlashcardService $cards): Response
    {
        /** @var User $user */
        $user = $request->user();

        return Inertia::render('flashcards/Index', [
            'exam' => CurrentExam::props($request),
            'decks' => $cards->decks($user, CurrentExam::content($request)),
        ]);
    }

    public function study(Request $request, FlashcardService $cards): Response
    {
        /** @var User $user */
        $user = $request->user();
        $exam = CurrentExam::content($request);
        $subject = $request->query('subject');
        $categoryId = match (true) {
            $subject === 'general' => 0,
            is_string($subject) && $subject !== '' => Category::where('exam_id', $exam->id)->where('slug', $subject)->value('id') ?? -1,
            default => null,
        };
        abort_if($categoryId === -1, 404);
        $card = $cards->next($user, $exam, $categoryId);
        $deck = collect($cards->decks($user, $exam));
        if ($categoryId !== null) {
            $deck = $deck->where('category_id', $categoryId === 0 ? null : $categoryId);
        }

        return Inertia::render('flashcards/Study', [
            'exam' => CurrentExam::props($request),
            'subject' => ['slug' => is_string($subject) ? $subject : null, 'name' => $categoryId === null ? 'All subjects' : ($categoryId === 0 ? 'General' : (string) Category::whereKey($categoryId)->value('name'))],
            'card' => $card === null ? null : [
                'id' => $card->id,
                'front_html' => QuestionContent::html($card->front),
                'back_html' => QuestionContent::html($card->back),
                'previews' => $cards->previews($user, $card),
            ],
            'counts' => ['due' => (int) $deck->sum('due'), 'new' => (int) $deck->sum('new'), 'total' => (int) $deck->sum('total')],
        ]);
    }

    public function review(Request $request, Flashcard $flashcard, FlashcardService $cards): RedirectResponse
    {
        // Only a published card of the selected exam's bank.
        abort_unless($flashcard->is_published && $flashcard->exam_id === CurrentExam::content($request)->id, 404);
        $data = $request->validate(['rating' => ['required', Rule::in(FlashcardService::RATINGS)]]);
        /** @var User $user */
        $user = $request->user();
        $cards->review($user, $flashcard, (string) $data['rating']);

        return back();
    }
}
