<?php

namespace App\Jobs;

use App\Models\Category;
use App\Models\PastPaper;
use App\Models\Question;
use App\Models\QuestionImport;
use App\Models\User;
use App\Services\PdfImportException;
use App\Services\PdfQuestionParser;
use App\Support\Notify;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Turns an uploaded PDF (question bank or past paper) into MCQs, without AI:
 * the PDF's text is read with smalot/pdfparser and split into questions by
 * App\Services\PdfQuestionParser. Questions are saved as drafts unless the
 * admin chose to publish them. Codes come from the file's hash and the
 * question number, so uploading the same PDF again updates those questions.
 */
class ImportQuestionsFromPdf implements ShouldQueue
{
    use Queueable;

    public int $timeout = 1200;

    public int $tries = 3;

    public const MAX_BYTES = 20 * 1024 * 1024;

    public function __construct(public int $importId)
    {
        $this->onQueue('imports');
    }

    /** @return list<WithoutOverlapping> */
    public function middleware(): array
    {
        return [(new WithoutOverlapping('question-import-'.$this->importId))->expireAfter(1260)->dontRelease()];
    }

    public function handle(PdfQuestionParser $parser): void
    {
        $import = QuestionImport::find($this->importId);
        if ($import === null || $import->status === 'completed') {
            return;
        }
        try {
            $this->process($import, $parser);
        } catch (PdfImportException $exception) {
            $import->update(['status' => 'failed', 'failure' => $exception->getMessage()]);
            $this->tell($import, 'PDF upload failed', $exception->getMessage(), 'danger');
        }
    }

    private function process(QuestionImport $import, PdfQuestionParser $parser): void
    {
        if (! User::find($import->user_id)?->can('create', Question::class)) {
            throw new PdfImportException('The uploader no longer has content management permission.');
        }
        $disk = Storage::disk('local');
        if (! str_starts_with($import->path, 'question-imports/') || ! $disk->exists($import->path)) {
            throw new PdfImportException('The private PDF file is unavailable.');
        }
        if ($disk->size($import->path) > self::MAX_BYTES) {
            throw new PdfImportException('The PDF is larger than 20 MB. Split it into smaller files.');
        }
        $pdf = (string) $disk->get($import->path);
        if (! str_starts_with($pdf, '%PDF')) {
            throw new PdfImportException('This file is not a PDF.');
        }
        if ($import->past_paper_id && ! PastPaper::whereKey($import->past_paper_id)->where('exam_id', $import->exam_id)->exists()) {
            throw new PdfImportException('The past paper must belong to the selected exam.');
        }
        $import->update(['status' => 'processing', 'failure' => null]);

        $text = $parser->text($pdf);
        if (trim($text) === '') {
            throw new PdfImportException('No text was found in this PDF. A scanned PDF (pictures of pages) cannot be read; use a text PDF, or CSV/XLSX.');
        }
        $questions = $parser->parse($text);
        if ($questions === []) {
            throw new PdfImportException('No MCQs were found. Check that the PDF follows the format shown in the upload form (numbered questions, A–E options and "Answer: B").');
        }

        $byName = Category::where('exam_id', $import->exam_id)->pluck('id', 'name')
            ->mapWithKeys(fn (int $id, string $name): array => [Str::lower($name) => $id]);
        $prefix = 'PDF-'.substr(sha1($pdf), 0, 8).'-';
        $errors = [];
        foreach ($questions as $question) {
            $import->increment('processed');
            try {
                // The question's own "Subject:" line first, then the subject chosen for the whole PDF.
                $this->save($import, $question, $prefix, $byName->get(Str::lower($question['subject'])) ?? $import->category_id);
                $import->increment('imported');
            } catch (ValidationException $exception) {
                $import->increment('rejected');
                $errors[] = ['row' => $question['number'], 'message' => collect($exception->errors())->flatten()->first() ?? 'Invalid question.'];
            }
        }

        $import->update(['status' => 'completed', 'last_row' => count($questions), 'errors' => array_slice($errors, 0, 200)]);
        $import->refresh();
        $this->tell($import, 'PDF upload finished',
            $import->imported.' MCQs saved'.($import->publish ? '' : ' as drafts').', '.$import->rejected.' skipped.', $import->rejected > 0 ? 'warning' : 'success');
    }

    /**
     * @param  array{number: int, stem: string, options: array<string, string>, correct_option: string|null, explanation: string, subject: string, title: string, key_points: string, mnemonics: string}  $mcq
     */
    private function save(QuestionImport $import, array $mcq, string $prefix, ?int $categoryId): void
    {
        $number = $mcq['number'];
        if ($mcq['stem'] === '') {
            throw ValidationException::withMessages(['body' => 'Question '.$number.' has no question text.']);
        }
        if (count($mcq['options']) < 2) {
            throw ValidationException::withMessages(['options' => 'Question '.$number.': fewer than 2 options (A., B., …) were found.']);
        }
        if ($mcq['correct_option'] === null) {
            throw ValidationException::withMessages(['correct_option' => 'Question '.$number.': no answer was found (add "Answer: B" under it, or an answer key).']);
        }
        $note = null;
        if ($categoryId === null) {
            $note = $mcq['subject'] !== ''
                ? 'Subject "'.Str::limit($mcq['subject'], 80).'" was not found in this exam: moved to "Unsorted (PDF import)".'
                : 'No subject (no "Subject:" line and none chosen for the upload): moved to "Unsorted (PDF import)".';
            $categoryId = $this->unsorted($import->exam_id);
        }

        $question = Question::withTrashed()->firstOrNew(['exam_id' => $import->exam_id, 'code' => $prefix.str_pad((string) $number, 3, '0', STR_PAD_LEFT)]);
        $question->fill([
            'category_id' => $categoryId,
            'past_paper_id' => $import->past_paper_id,
            'body' => $mcq['stem'],
            'options' => array_map(fn (string $text): string => Str::limit($text, 2000, ''), $mcq['options']),
            'correct_option' => $mcq['correct_option'],
            'explanation' => $mcq['explanation'] !== '' ? $mcq['explanation'] : null,
            'key_points' => $mcq['key_points'] !== '' ? $mcq['key_points'] : null,
            'mnemonics' => $mcq['mnemonics'] !== '' ? $mcq['mnemonics'] : null,
            // The topic ("Hypertrophic cardiomyopathy") drives the Google Images and YouTube links.
            'image_search' => $mcq['title'] !== '' ? Str::limit($mcq['title'], 200, '') : null,
            'source_reference' => 'PDF upload #'.$import->id.', question '.$number,
            'needs_review' => $note !== null,
            'review_note' => $note,
            'is_published' => $import->publish,
        ]);
        $question->deleted_at = null;
        $question->save();
    }

    /** The subject for questions whose subject is missing or unknown. */
    private function unsorted(int $examId): int
    {
        return Category::firstOrCreate(['exam_id' => $examId, 'slug' => 'unsorted-pdf-import'], ['name' => 'Unsorted (PDF import)'])->id;
    }

    private function tell(QuestionImport $import, string $title, string $body, string $color): void
    {
        $user = User::find($import->user_id);
        if ($user !== null) {
            Notify::staff([$user], $title.' (#'.$import->id.')', $body, '/admin/question-imports', 'heroicon-o-document-text', $color);
        }
    }

    public function failed(?Throwable $exception): void
    {
        QuestionImport::whereKey($this->importId)->update([
            'status' => 'failed',
            'failure' => 'The PDF import stopped. Check the worker log, then upload the PDF again (questions already saved are updated, not duplicated).',
        ]);
    }
}
