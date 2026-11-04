<?php

use App\Filament\Resources\QuestionResource\Pages\ManageQuestions;
use App\Jobs\ImportQuestions;
use App\Models\Category;
use App\Models\Exam;
use App\Models\PracticeSession;
use App\Models\Question;
use App\Models\QuestionImport;
use App\Models\User;
use App\Services\QuestionFileReader;
use App\Support\QuestionContent;
use Database\Seeders\RoleSeeder;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    $this->exam = Exam::factory()->create();
    $this->category = Category::factory()->create(['exam_id' => $this->exam->id, 'name' => 'Cardiology', 'slug' => 'cardiology']);
    $this->staff = User::factory()->create();
    $this->staff->assignRole('editor');
    Storage::fake('local');
});

function richQuestion(Exam $exam, Category $category, array $overrides = []): Question
{
    return Question::create(array_merge([
        'exam_id' => $exam->id, 'category_id' => $category->id, 'code' => 'RICH-1',
        'body' => "A 64-year-old man.\n\n| Test | Result |\n| --- | --- |\n| Na | 128 |",
        'options' => ['A' => 'SIADH', 'B' => 'Addison disease'], 'correct_option' => 'A',
        'explanation' => 'Euvolaemic **hyponatraemia**.', 'key_points' => '- Check serum osmolality', 'mnemonics' => 'SIADH = Sodium Is Always Diluted Here',
        'references' => [['source' => 'NICE', 'title' => 'Hyponatraemia CKS', 'url' => 'https://cks.nice.org.uk/topics/hyponatraemia/', 'year' => '2024']],
        'youtube_url' => 'https://youtu.be/dQw4w9WgXcQ', 'image_links' => [['label' => 'CXR', 'url' => 'https://example.org/cxr.png']],
        'system' => 'Endocrine', 'repeat_count' => 3, 'source_reference' => 'Sept 2023 recall', 'is_published' => true,
    ], $overrides));
}

it('renders Markdown tables and strips raw HTML and unsafe links', function () {
    $html = QuestionContent::html("| A | B |\n| - | - |\n| 1 | 2 |\n\n<script>alert(1)</script>

[bad](javascript:alert(1)) ![x](https://example.org/a.png)");

    expect($html)->toContain('<table>')->toContain('<td>1</td>')->toContain('<img src="https://example.org/a.png"')
        ->not->toContain('<script>')->not->toContain('javascript:');
    expect(QuestionContent::html('  '))->toBeNull();
});

it('reads YouTube ids and cleans references and image links', function () {
    expect(QuestionContent::youtubeId('https://www.youtube.com/watch?v=dQw4w9WgXcQ&t=10'))->toBe('dQw4w9WgXcQ')
        ->and(QuestionContent::youtubeId('https://youtu.be/dQw4w9WgXcQ'))->toBe('dQw4w9WgXcQ')
        ->and(QuestionContent::youtubeId('https://vimeo.com/123'))->toBeNull();
    expect(QuestionContent::cleanReferences([
        ['source' => 'NICE', 'title' => 'NG136', 'url' => 'javascript:alert(1)', 'year' => '20xx'],
        ['source' => 'Unknown', 'title' => 'Other source'],
        ['title' => ''],
    ]))->toBe([
        ['source' => 'NICE', 'title' => 'NG136', 'url' => null, 'year' => null],
        ['source' => 'Other', 'title' => 'Other source', 'url' => null, 'year' => null],
    ]);
    expect(QuestionContent::cleanLinks([['label' => '', 'url' => 'https://example.org/a.png'], ['url' => 'ftp://x']]))
        ->toBe([['label' => 'example.org', 'url' => 'https://example.org/a.png']]);
    expect(fn () => richQuestion($this->exam, $this->category, ['youtube_url' => 'https://example.org']))->toThrow(ValidationException::class);
});

it('keeps answer content secret until answered, then reveals it with the labels and time', function () {
    richQuestion($this->exam, $this->category);
    $student = User::factory()->create();
    $this->actingAs($student)->post('/questions/sessions', ['count' => 1])->assertRedirect();
    $session = PracticeSession::firstOrFail();
    $url = '/questions/sessions/'.$session->id;

    $this->get($url)->assertInertia(fn (Assert $page) => $page
        ->component('questions/Practice')
        ->missing('question.result')
        ->missing('question.explanation')
        ->missing('question.key_points')
        ->where('question.labels', ['subject' => 'Cardiology', 'system' => 'Endocrine', 'repeats' => 3, 'reference' => 'Sept 2023 recall'])
        ->where('question.body_html', fn (string $html) => str_contains($html, '<table>'))
        ->has('navigator', 1)
        ->where('navigator.0.correct', null));
    $response = $this->get($url)->getContent();
    expect($response)->not->toContain('Sodium Is Always Diluted')->not->toContain('Hyponatraemia CKS');

    $this->post($url.'/answer/1', ['option' => 'A', 'seconds' => 5000])->assertRedirect();
    $this->get($url.'?position=1')->assertInertia(fn (Assert $page) => $page
        ->where('question.result.is_correct', true)
        ->where('question.result.explanation_html', '<p>Euvolaemic <strong>hyponatraemia</strong>.</p>')
        ->where('question.result.key_points_html', fn (string $html) => str_contains($html, '<li>Check serum osmolality</li>'))
        ->where('question.result.mnemonics_html', fn (string $html) => str_contains($html, 'Sodium Is Always Diluted Here'))
        ->where('question.result.references.0.title', 'Hyponatraemia CKS')
        ->where('question.result.youtube_id', 'dQw4w9WgXcQ')
        ->where('question.result.image_links.0.label', 'CXR')
        ->where('question.result.time_seconds', 3600)
        ->where('navigator.0.correct', true));
});

it('keeps the session snapshot when the question is edited later', function () {
    $question = richQuestion($this->exam, $this->category);
    $student = User::factory()->create();
    $this->actingAs($student)->post('/questions/sessions', ['count' => 1]);
    $session = PracticeSession::firstOrFail();
    $question->update(['key_points' => 'Changed later', 'system' => 'Renal']);
    $this->post('/questions/sessions/'.$session->id.'/answer/1', ['option' => 'B']);

    $this->get('/questions/sessions/'.$session->id.'?position=1')->assertInertia(fn (Assert $page) => $page
        ->where('question.labels.system', 'Endocrine')
        ->where('question.result.key_points_html', fn (string $html) => ! str_contains($html, 'Changed later'))
        ->where('question.result.is_correct', false));
});

it('marks a question as read and only for the session owner', function () {
    richQuestion($this->exam, $this->category);
    $student = User::factory()->create();
    $this->actingAs($student)->post('/questions/sessions', ['count' => 1]);
    $session = PracticeSession::firstOrFail();
    $url = '/questions/sessions/'.$session->id;

    $this->post($url.'/read/1', ['read' => true])->assertRedirect();
    $this->get($url)->assertInertia(fn (Assert $page) => $page->where('question.is_read', true)->where('navigator.0.read', true));
    $this->post($url.'/read/1', ['read' => false]);
    $this->get($url)->assertInertia(fn (Assert $page) => $page->where('question.is_read', false));
    $this->post($url.'/read/5', ['read' => true])->assertNotFound();
    $this->actingAs(User::factory()->create())->post($url.'/read/1', ['read' => true])->assertForbidden();
});

it('saves a private note from the practice screen as a bookmark note', function () {
    $question = richQuestion($this->exam, $this->category);
    $student = User::factory()->create();
    $this->actingAs($student)->post('/questions/sessions', ['count' => 1]);
    $session = PracticeSession::firstOrFail();
    $this->post('/revision/bookmarks', ['question_id' => $question->id, 'note' => 'Remember osmolality'])->assertRedirect();

    $this->get('/questions/sessions/'.$session->id)->assertInertia(fn (Assert $page) => $page
        ->where('question.is_bookmarked', true)->where('question.note', 'Remember osmolality'));
});

it('sends an MCQ report tied to the question and rejects other exams', function () {
    $question = richQuestion($this->exam, $this->category);
    $other = Exam::factory()->create();
    $otherQuestion = richQuestion($other, Category::factory()->create(['exam_id' => $other->id]), ['code' => 'OTHER-1']);
    $student = User::factory()->create();
    $this->actingAs($student)->post('/exam', ['exam_id' => $this->exam->id]);

    $this->post('/feedback', ['type' => 'content', 'message' => 'The NICE target changed in 2023.', 'question_id' => $question->id])
        ->assertSessionHas('status', 'Thank you. The MCQ report has been sent to the editors.');
    $this->assertDatabaseHas('feedback', ['user_id' => $student->id, 'question_id' => $question->id, 'type' => 'content']);
    $this->post('/feedback', ['type' => 'content', 'message' => 'Wrong exam question here', 'question_id' => $otherQuestion->id])->assertSessionHasErrors('question_id');
});

it('imports the rich content columns and flags rows with a bad reference link', function () {
    $headers = implode(',', ImportQuestions::HEADERS);
    $good = ['R-1', 'cardiology', 'Stem', 'One', 'Two', '', '', '', 'A', 'Why', 'Key', 'Mnem', 'Cardiovascular', '2', 'Jan 2024',
        'NICE NG185', 'https://www.nice.org.uk/guidance/ng185', 'WHO HEARTS', 'https://www.who.int/publications/i/item/9789240001367', '', '',
        'https://www.youtube.com/watch?v=dQw4w9WgXcQ', 'ECG | https://example.org/ecg.png; https://example.org/b.png', 'erythema  nodosum', 'Radiopaedia, dermnet', 'no', ''];
    $bad = ['R-2', 'cardiology', 'Stem 2', 'One', 'Two', '', '', '', 'B', '', '', '', '', '', '',
        'NICE guideline', 'not-a-url', '', '', '', '', '', '', '', '', '', ''];
    $badVideo = ['R-3', 'cardiology', 'Stem 3', 'One', 'Two', '', '', '', 'B', '', '', '', '', '', '', '', '', '', '', '', '', 'https://vimeo.com/1', '', '', '', '', ''];
    $csv = $headers."\n".implode("\n", array_map(fn (array $row) => implode(',', array_map(fn ($v) => '"'.$v.'"', $row)), [$good, $bad, $badVideo]))."\n";
    Storage::disk('local')->put('question-imports/rich.csv', $csv);
    $import = QuestionImport::create(['user_id' => $this->staff->id, 'exam_id' => $this->exam->id, 'path' => 'question-imports/rich.csv', 'publish' => true]);
    (new ImportQuestions($import->id))->handle(new QuestionFileReader);

    $import->refresh();
    expect($import->status)->toBe('completed')->and($import->imported)->toBe(2)->and($import->rejected)->toBe(1)
        ->and($import->errors[0]['message'])->toContain('youtube_url');
    $first = Question::where('code', 'R-1')->firstOrFail();
    expect($first->key_points)->toBe('Key')->and($first->system)->toBe('Cardiovascular')->and($first->repeat_count)->toBe(2)
        ->and($first->references)->toHaveCount(2)->and($first->references[1]['source'])->toBe('WHO')
        ->and($first->image_links)->toBe([['label' => 'ECG', 'url' => 'https://example.org/ecg.png'], ['label' => 'example.org', 'url' => 'https://example.org/b.png']])
        ->and($first->needs_review)->toBeFalse()
        ->and($first->image_search)->toBe('erythema nodosum')
        ->and($first->image_search_sites)->toBe(['radiopaedia', 'dermnet']);
    expect(Question::where('code', 'R-2')->value('image_search_sites'))->toBeNull();
    $second = Question::where('code', 'R-2')->firstOrFail();
    expect($second->needs_review)->toBeTrue()->and($second->review_note)->toContain('not a valid')
        ->and($second->references)->toBe([['source' => 'NICE', 'title' => 'NICE guideline', 'url' => null, 'year' => null]]);
});

it('rejects unknown import headers and serves the full template', function () {
    Storage::disk('local')->put('question-imports/unknown.csv', "code,category_slug,question,option_a,option_b,correct_option,colour\n");
    $import = QuestionImport::create(['user_id' => $this->staff->id, 'exam_id' => $this->exam->id, 'path' => 'question-imports/unknown.csv']);
    (new ImportQuestions($import->id))->handle(new QuestionFileReader);
    expect($import->refresh()->status)->toBe('failed')->and($import->failure)->toContain('Unknown header: colour');

    $template = $this->actingAs($this->staff)->get('/admin/mcq-template')->assertOk()->streamedContent();
    expect(trim($template))->toBe(implode(',', ImportQuestions::HEADERS));
});

it('creates an MCQ with references and a review flag from the admin form', function () {
    $this->actingAs($this->staff);
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    Livewire::test(ManageQuestions::class)->callAction('create', data: [
        'exam_id' => $this->exam->id, 'category_id' => $this->category->id, 'code' => 'ADMIN-1', 'body' => 'Stem with **bold**',
        'options' => ['A' => 'One', 'B' => 'Two', 'C' => '', 'D' => '', 'E' => ''], 'correct_option' => 'A',
        'references' => [['source' => 'WHO', 'title' => 'Guideline text', 'url' => 'https://www.who.int/', 'year' => '2023']],
        'needs_review' => true, 'review_note' => 'Check the WHO year', 'repeat_count' => 1,
    ])->assertHasNoActionErrors();

    $question = Question::where('code', 'ADMIN-1')->firstOrFail();
    expect($question->needs_review)->toBeTrue()->and($question->review_note)->toBe('Check the WHO year')
        ->and($question->references[0]['source'])->toBe('WHO');
    $this->get('/admin/questions')->assertOk();
});
