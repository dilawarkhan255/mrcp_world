<?php

namespace App\Jobs;

use App\Exceptions\ImportFileException;
use App\Models\Category;
use App\Models\PastPaper;
use App\Models\Question;
use App\Models\QuestionImport;
use App\Models\User;
use App\Services\QuestionFileReader;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Throwable;

class ImportQuestions implements ShouldQueue
{
    use Queueable;

    public int $timeout = 1200;

    public int $tries = 3;

    public int $backoff = 30;

    public function __construct(public int $importId)
    {
        $this->onQueue('imports');
    }

    /** @return list<WithoutOverlapping> */
    public function middleware(): array
    {
        return [(new WithoutOverlapping('question-import-'.$this->importId))->expireAfter(1260)->dontRelease()];
    }

    public function handle(QuestionFileReader $reader): void
    {
        try {
            $this->process($reader);
        } catch (ImportFileException $exception) {
            QuestionImport::whereKey($this->importId)->update(['status' => 'failed', 'failure' => $exception->getMessage()]);
        }
    }

    private function process(QuestionFileReader $reader): void
    {
        $import = QuestionImport::findOrFail($this->importId);
        if ($import->status === 'completed') {
            return;
        }
        if (! User::find($import->user_id)?->can('create', Question::class)) {
            throw new ImportFileException('The uploader no longer has content management permission.');
        }
        if (! str_starts_with($import->path, 'question-imports/') || ! Storage::disk('local')->exists($import->path)) {
            throw new ImportFileException('Private import file is unavailable.');
        }
        if (Storage::disk('local')->size($import->path) > 20 * 1024 * 1024) {
            throw new ImportFileException('File exceeds the 20 MB upload limit.');
        }
        if ($import->past_paper_id && ! PastPaper::whereKey($import->past_paper_id)->where('exam_id', $import->exam_id)->exists()) {
            throw new ImportFileException('Past paper must belong to the selected exam.');
        }
        $import->update(['status' => 'processing', 'failure' => null]);
        $headers = [];
        $batch = [];
        foreach ($reader->rows(Storage::disk('local')->path($import->path)) as $number => $values) {
            if ($number === 1) {
                $headers = array_map(fn (string $value): string => strtolower(ltrim($value, "\xEF\xBB\xBF")), $values);
                if (count($headers) > 16 || count($headers) !== count(array_unique($headers)) || array_diff(['code', 'category_slug', 'question', 'option_a', 'option_b', 'correct_option'], $headers)) {
                    throw new ImportFileException('Missing or duplicate headers. Download the import template.');
                }

                continue;
            }
            if ($number > 50001) {
                throw new ImportFileException('Maximum 50,000 rows per file. Split the import into smaller files.');
            }
            if ($number <= $import->last_row) {
                continue;
            }
            $batch[$number] = count($values) > count($headers) ? ['_invalid' => 'Too many columns'] : array_combine($headers, array_pad($values, count($headers), ''));
            if (count($batch) === 100) {
                $this->saveBatch($import, $batch);
                $batch = [];
            }
        }
        if ($batch) {
            $this->saveBatch($import, $batch);
        }
        if (! $headers) {
            throw new ImportFileException('The import file is empty.');
        }
        $import->update(['status' => 'completed']);
    }

    /** @param array<int, array<string, string>> $batch */
    private function saveBatch(QuestionImport $import, array $batch): void
    {
        DB::transaction(function () use ($import, $batch) {
            $slugs = array_unique(array_column($batch, 'category_slug'));
            $categories = Category::where('exam_id', $import->exam_id)->whereIn('slug', $slugs)->pluck('id', 'slug');
            $rows = [];
            $errors = $import->errors ?? [];
            $rejected = 0;
            foreach ($batch as $number => $row) {
                if (! array_filter($row, fn (string $value): bool => $value !== '')) {
                    continue;
                }
                $options = [];
                foreach (['A', 'B', 'C', 'D', 'E'] as $key) {
                    if (($row['option_'.strtolower($key)] ?? '') !== '') {
                        $options[$key] = $row['option_'.strtolower($key)];
                    }
                }
                $correct = strtoupper($row['correct_option'] ?? '');
                $validator = Validator::make($row, [
                    'code' => ['required', 'string', 'max:100', 'regex:/^[A-Za-z0-9._-]+$/'],
                    'question' => ['required', 'string', 'max:20000'],
                    'category_slug' => ['required', 'string', 'max:255'],
                    'explanation' => ['nullable', 'string', 'max:20000'],
                    'option_a' => ['required', 'string', 'max:4000'],
                    'option_b' => ['required', 'string', 'max:4000'],
                    'option_c' => ['nullable', 'string', 'max:4000'],
                    'option_d' => ['nullable', 'string', 'max:4000'],
                    'option_e' => ['nullable', 'string', 'max:4000'],
                ]);
                $message = $validator->fails() ? $validator->errors()->first() : null;
                if (isset($row['_invalid'])) {
                    $message = $row['_invalid'];
                }
                if (! isset($categories[$row['category_slug'] ?? ''])) {
                    $message = 'Category slug does not exist in the selected exam.';
                }
                if (! isset($options[$correct])) {
                    $message = 'Correct option must name a populated option (A–E).';
                }
                if ($message !== null) {
                    $rejected++;
                    if (count($errors) < 100) {
                        $errors[] = ['row' => $number, 'message' => $message];
                    }

                    continue;
                }
                $rows[] = [
                    'exam_id' => $import->exam_id, 'category_id' => $categories[$row['category_slug']], 'past_paper_id' => $import->past_paper_id,
                    'code' => $row['code'], 'body' => $row['question'], 'options' => json_encode($options, JSON_THROW_ON_ERROR),
                    'correct_option' => $correct, 'explanation' => $row['explanation'] ?? '', 'is_published' => $import->publish,
                    'deleted_at' => null, 'created_at' => now(), 'updated_at' => now(),
                ];
            }
            if ($rows) {
                Question::upsert($rows, ['exam_id', 'code'], ['category_id', 'past_paper_id', 'body', 'options', 'correct_option', 'explanation', 'is_published', 'deleted_at', 'updated_at']);
            }
            $import->update([
                'processed' => $import->processed + count($rows) + $rejected, 'imported' => $import->imported + count($rows),
                'rejected' => $import->rejected + $rejected, 'last_row' => max(array_keys($batch)), 'errors' => $errors,
            ]);
        }, 3);
    }

    public function failed(?Throwable $exception): void
    {
        QuestionImport::whereKey($this->importId)->update(['status' => 'failed', 'failure' => 'Import stopped. Rows already committed are retained. Check the worker log, correct the file and upload again.']);
    }
}
