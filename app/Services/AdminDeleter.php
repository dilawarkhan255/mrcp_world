<?php

namespace App\Services;

use App\Models\Category;
use App\Models\Exam;
use App\Models\PastPaper;
use App\Models\Plan;
use App\Models\Qbank;
use App\Models\Question;
use App\Models\QuestionImport;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;

/**
 * "Delete with everything inside", for admins only (the admin panel checks
 * the role). Normal deletes refuse a record that is still in use; this one
 * removes what depends on it first, in one transaction:
 *
 * - Exam: its subjects, past papers, MCQs, textbook chapters, flashcards,
 *   uploads and every student session of it.
 * - Subject (category): its MCQs (chapters and flashcards stay, without a subject).
 * - Past paper: its MCQs and uploads.
 * - Qbank: its exams (as above), plans and subscriptions.
 * - Plan: nothing else; subscriptions keep their access and lose the plan link.
 * - User: their upload records (the MCQs they uploaded stay).
 * - MCQ: removed for good (also when archived); old results keep their snapshot.
 *
 * impact() lists what goes, for the confirmation.
 */
class AdminDeleter
{
    /** @return array<string, int> what else is removed, label => count (only counts above 0) */
    public function impact(Model $record): array
    {
        $counts = match (true) {
            $record instanceof Exam => $this->examImpact([$record->id]),
            $record instanceof Category => ['MCQs' => $this->questions()->where('category_id', $record->id)->count()],
            $record instanceof PastPaper => [
                'MCQs' => $this->questions()->where('past_paper_id', $record->id)->count(),
                'uploads' => QuestionImport::where('past_paper_id', $record->id)->count(),
            ],
            $record instanceof Qbank => array_merge(
                ['exams' => Exam::where('qbank_id', $record->id)->count()],
                $this->examImpact(Exam::where('qbank_id', $record->id)->pluck('id')->all()),
                [
                    'plans' => Plan::where('qbank_id', $record->id)->count(),
                    'subscriptions' => DB::table('subscriptions')->where('qbank_id', $record->id)->count(),
                ],
            ),
            $record instanceof Plan => ['subscriptions that lose the plan link (access stays)' => DB::table('subscriptions')->where('plan_id', $record->id)->count()],
            $record instanceof User => ['upload records' => QuestionImport::where('user_id', $record->id)->count()],
            default => [],
        };

        return array_filter($counts, fn (int $count): bool => $count > 0);
    }

    /** Deletes the record and everything that depends on it. */
    public function delete(Model $record): void
    {
        DB::transaction(function () use ($record) {
            match (true) {
                $record instanceof Exam => $this->deleteExams([$record->id]),
                $record instanceof Category => $this->deleteCategory($record),
                $record instanceof PastPaper => $this->deletePastPaper($record),
                $record instanceof Qbank => $this->deleteQbank($record),
                $record instanceof Plan => $record->delete(),
                $record instanceof User => $this->deleteUser($record),
                $record instanceof Question => $this->questions()->where('id', $record->getKey())->delete(),
                $record instanceof QuestionImport => $this->deleteImports(QuestionImport::whereKey($record->id)->get()),
                default => throw new InvalidArgumentException('This record cannot be deleted with its contents.'),
            };
        });
    }

    /** MCQs, archived ones included, as a plain query (a delete removes them for good). */
    private function questions(): Builder
    {
        return DB::table('questions');
    }

    /**
     * @param  list<int>  $examIds
     * @return array<string, int>
     */
    private function examImpact(array $examIds): array
    {
        return [
            'subjects' => Category::whereIn('exam_id', $examIds)->count(),
            'past papers' => PastPaper::whereIn('exam_id', $examIds)->count(),
            'MCQs' => $this->questions()->whereIn('exam_id', $examIds)->count(),
            'textbook chapters' => DB::table('textbook_chapters')->whereIn('exam_id', $examIds)->count(),
            'flashcards' => DB::table('flashcards')->whereIn('exam_id', $examIds)->count(),
            'student sessions' => DB::table('practice_sessions')->whereIn('exam_id', $examIds)->count(),
            'uploads' => QuestionImport::whereIn('exam_id', $examIds)->count(),
        ];
    }

    /** @param  list<int>  $examIds */
    private function deleteExams(array $examIds): void
    {
        if ($examIds === []) {
            return;
        }
        // Exams that read this bank keep working on their own.
        Exam::whereIn('content_exam_id', $examIds)->update(['content_exam_id' => null]);
        $this->deleteImports(QuestionImport::whereIn('exam_id', $examIds)->get());
        DB::table('practice_sessions')->whereIn('exam_id', $examIds)->delete();
        DB::table('textbook_chapters')->whereIn('exam_id', $examIds)->delete();
        DB::table('flashcards')->whereIn('exam_id', $examIds)->delete();
        $this->questions()->whereIn('exam_id', $examIds)->delete();
        PastPaper::whereIn('exam_id', $examIds)->get()->each(fn (PastPaper $paper) => $this->deletePaperRow($paper));
        DB::table('categories')->whereIn('exam_id', $examIds)->delete();
        DB::table('exams')->whereIn('id', $examIds)->delete();
    }

    private function deleteCategory(Category $category): void
    {
        $this->questions()->where('category_id', $category->id)->delete();
        $category->delete();
    }

    private function deletePastPaper(PastPaper $paper): void
    {
        $this->questions()->where('past_paper_id', $paper->id)->delete();
        $this->deleteImports(QuestionImport::where('past_paper_id', $paper->id)->get());
        $this->deletePaperRow($paper);
    }

    private function deletePaperRow(PastPaper $paper): void
    {
        $image = $paper->image_path;
        $paper->delete();
        if ($image) {
            Storage::disk(PastPaper::IMAGE_DISK)->delete($image);
        }
    }

    private function deleteQbank(Qbank $qbank): void
    {
        $this->deleteExams(Exam::where('qbank_id', $qbank->id)->pluck('id')->all());
        DB::table('subscriptions')->where('qbank_id', $qbank->id)->delete();
        DB::table('plans')->where('qbank_id', $qbank->id)->delete();
        $qbank->delete();
    }

    private function deleteUser(User $user): void
    {
        $this->deleteImports(QuestionImport::where('user_id', $user->id)->get());
        $user->delete();
    }

    /** @param  iterable<QuestionImport>  $imports  Upload records and their private files (the MCQs stay). */
    private function deleteImports(iterable $imports): void
    {
        foreach ($imports as $import) {
            $path = $import->path;
            DB::table('question_imports')->where('id', $import->id)->delete();
            if (str_starts_with($path, 'question-imports/')) {
                Storage::disk('local')->delete($path);
            }
        }
    }
}
