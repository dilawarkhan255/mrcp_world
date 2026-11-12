<?php

namespace App\Http\Controllers;

use App\Models\Flashcard;
use App\Models\User;
use App\Services\FlashcardService;
use App\Support\CurrentExam;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * A rating for a flashcard (a Knowledge Tutor fact) outside a tutor run.
 * The decks and the study screen are the Knowledge Tutor now (KnowledgeTutorController).
 */
class FlashcardController extends Controller
{
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
