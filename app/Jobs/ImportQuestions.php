<?php

namespace App\Jobs;

use App\Exceptions\ImportFileException;
use App\Models\Category;
use App\Models\PastPaper;
use App\Models\Question;
use App\Models\QuestionImport;
use App\Models\User;
use App\Services\QuestionFileReader;
use App\Support\QuestionContent;
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

    /** Required columns of an import file. */
    public const REQUIRED = ['code', 'category_slug', 'question', 'option_a', 'option_b', 'correct_option'];

    /** Every column an import file may contain, in template order. */
    public const HEADERS = [
        'code', 'category_slug', 'question', 'option_a', 'option_b', 'option_c', 'option_d', 'option_e', 'correct_option', 'explanation',
        'key_points', 'mnemonics', 'system', 'repeat_count', 'source_reference',
        'nice_reference', 'nice_url', 'who_reference', 'who_url', 'other_reference', 'other_url',
        'youtube_url', 'image_links', 'image_search', 'image_search_sites', 'needs_review', 'review_note',
    ];

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
                if (count($headers) > count(self::HEADERS) || count($headers) !== count(array_unique($headers)) || array_diff(self::REQUIRED, $headers)) {
                    throw new ImportFileException('Missing or duplicate headers. Download the import template.');
                }
                if ($unknown = array_diff($headers, self::HEADERS)) {
                    throw new ImportFileException('Unknown header: '.implode(', ', array_slice($unknown, 0, 3)).'. Download the import template.');
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
                    'key_points' => ['nullable', 'string', 'max:8000'],
                    'mnemonics' => ['nullable', 'string', 'max:4000'],
                    'system' => ['nullable', 'string', 'max:100'],
                    'repeat_count' => ['nullable', 'integer', 'min:0', 'max:999'],
                    'source_reference' => ['nullable', 'string', 'max:255'],
                    'nice_reference' => ['nullable', 'string', 'max:500'],
                    'who_reference' => ['nullable', 'string', 'max:500'],
                    'other_reference' => ['nullable', 'string', 'max:500'],
                    'youtube_url' => ['nullable', 'string', 'max:255'],
                    'image_links' => ['nullable', 'string', 'max:8000'],
                    'image_search' => ['nullable', 'string', 'max:150'],
                    'image_search_sites' => ['nullable', 'string', 'max:200'],
                    'needs_review' => ['nullable', 'string', 'in:0,1,yes,no,true,false,YES,NO,Yes,No,TRUE,FALSE'],
                    'review_note' => ['nullable', 'string', 'max:500'],
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
                $youtube = trim($row['youtube_url'] ?? '');
                if ($message === null && $youtube !== '' && QuestionContent::youtubeId($youtube) === null) {
                    $message = 'youtube_url must be a YouTube watch, share or embed link.';
                }
                if ($message !== null) {
                    $rejected++;
                    if (count($errors) < 100) {
                        $errors[] = ['row' => $number, 'message' => $message];
                    }

                    continue;
                }
                [$references, $droppedLinks] = $this->references($row);
                $links = QuestionContent::cleanLinks($this->imageLinks($row['image_links'] ?? ''));
                $flag = in_array(strtolower($row['needs_review'] ?? ''), ['1', 'yes', 'true'], true);
                $note = trim($row['review_note'] ?? '');
                if ($droppedLinks > 0) {
                    // A citation link that is not a valid http(s) address is removed and the MCQ is flagged for a person to check.
                    $flag = true;
                    $note = trim($note.' Reference link was not a valid http(s) address and was removed.');
                }
                $rows[] = [
                    'exam_id' => $import->exam_id, 'category_id' => $categories[$row['category_slug']], 'past_paper_id' => $import->past_paper_id,
                    'code' => $row['code'], 'body' => $row['question'], 'options' => json_encode($options, JSON_THROW_ON_ERROR),
                    'correct_option' => $correct, 'explanation' => $row['explanation'] ?? '',
                    'key_points' => $this->nullable($row['key_points'] ?? ''), 'mnemonics' => $this->nullable($row['mnemonics'] ?? ''),
                    'references' => $references === null ? null : json_encode($references, JSON_THROW_ON_ERROR),
                    'youtube_url' => $this->nullable($youtube), 'image_links' => $links === null ? null : json_encode($links, JSON_THROW_ON_ERROR),
                    'image_search' => QuestionContent::cleanSearchTerm($row['image_search'] ?? null),
                    'image_search_sites' => ($sites = QuestionContent::cleanSearchSites(preg_split('/[\s,;]+/', strtolower($row['image_search_sites'] ?? '')) ?: [])) === null ? null : json_encode($sites, JSON_THROW_ON_ERROR),
                    'system' => $this->nullable($row['system'] ?? ''), 'repeat_count' => (int) ($row['repeat_count'] ?? 0),
                    'source_reference' => $this->nullable($row['source_reference'] ?? ''),
                    'needs_review' => $flag, 'review_note' => $note === '' ? null : mb_substr($note, 0, 500),
                    'is_published' => $import->publish, 'deleted_at' => null, 'created_at' => now(), 'updated_at' => now(),
                ];
            }
            if ($rows) {
                Question::upsert($rows, ['exam_id', 'code'], [
                    'category_id', 'past_paper_id', 'body', 'options', 'correct_option', 'explanation',
                    'key_points', 'mnemonics', 'references', 'youtube_url', 'image_links', 'image_search', 'image_search_sites', 'system', 'repeat_count', 'source_reference',
                    'needs_review', 'review_note', 'is_published', 'deleted_at', 'updated_at',
                ]);
            }
            $import->update([
                'processed' => $import->processed + count($rows) + $rejected, 'imported' => $import->imported + count($rows),
                'rejected' => $import->rejected + $rejected, 'last_row' => max(array_keys($batch)), 'errors' => $errors,
            ]);
        }, 3);
    }

    private function nullable(string $value): ?string
    {
        $value = trim($value);

        return $value === '' ? null : $value;
    }

    /**
     * The NICE, WHO and other citation columns as reference entries, and how
     * many links were dropped because they were not valid http(s) addresses.
     *
     * @param  array<string, string>  $row
     * @return array{0: list<array{source: string, title: string, url: string|null, year: string|null}>|null, 1: int}
     */
    private function references(array $row): array
    {
        $entries = [];
        $dropped = 0;
        foreach (['nice' => 'NICE', 'who' => 'WHO', 'other' => 'Other'] as $prefix => $source) {
            $title = trim($row[$prefix.'_reference'] ?? '');
            $url = trim($row[$prefix.'_url'] ?? '');
            if ($title === '') {
                continue;
            }
            $entries[] = ['source' => $source, 'title' => $title, 'url' => $url];
            $clean = QuestionContent::cleanReferences([['source' => $source, 'title' => $title, 'url' => $url]]);
            if ($url !== '' && ($clean[0]['url'] ?? null) === null) {
                $dropped++;
            }
        }

        return [QuestionContent::cleanReferences($entries), $dropped];
    }

    /**
     * "label | url" pairs separated by new lines or semicolons. A bare URL is also accepted.
     *
     * @return list<array{label: string, url: string}>
     */
    private function imageLinks(string $value): array
    {
        $links = [];
        foreach (preg_split('/[\r\n;]+/', $value) ?: [] as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            [$label, $url] = str_contains($line, '|') ? array_map('trim', explode('|', $line, 2)) : ['', $line];
            $links[] = ['label' => $label, 'url' => $url];
        }

        return $links;
    }

    public function failed(?Throwable $exception): void
    {
        QuestionImport::whereKey($this->importId)->update(['status' => 'failed', 'failure' => 'Import stopped. Rows already committed are retained. Check the worker log, correct the file and upload again.']);
    }
}
