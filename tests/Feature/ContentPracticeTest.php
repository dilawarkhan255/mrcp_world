<?php

use App\Filament\Resources\PastPaperResource\Pages\ManagePastPapers;
use App\Filament\Resources\QuestionImportResource\Pages\ManageQuestionImports;
use App\Filament\Resources\QuestionResource\Pages\ManageQuestions;
use App\Jobs\ImportQuestions;
use App\Models\Category;
use App\Models\Exam;
use App\Models\PastPaper;
use App\Models\PracticeSession;
use App\Models\Question;
use App\Models\QuestionImport;
use App\Models\User;
use App\Services\PracticeService;
use App\Services\QuestionFileReader;
use Database\Seeders\RoleSeeder;
use Filament\Facades\Filament;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;
use Livewire\Livewire;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;

function contentQuestion(Exam $exam, Category $category, array $overrides = []): Question
{
    return Question::create(array_merge([
        'exam_id' => $exam->id, 'category_id' => $category->id, 'code' => fake()->unique()->bothify('Q-########'),
        'body' => 'Question text for an automated test', 'options' => ['A' => 'First option', 'B' => 'Second option'],
        'correct_option' => 'A', 'explanation' => 'Explanation for the saved answer', 'is_published' => true,
    ], $overrides));
}

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    $this->exam = Exam::factory()->create();
    $this->category = Category::factory()->create(['exam_id' => $this->exam->id, 'slug' => 'cardiology']);
    $this->staff = User::factory()->create();
    $this->staff->assignRole('editor');
    Storage::fake('local');
});

it('provides editors with working manual question and paper CRUD', function () {
    $this->actingAs($this->staff);
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    $this->get('/admin')->assertOk()->assertSee('Publishing workflow')->assertSee('Bulk upload');
    Livewire::test(ManagePastPapers::class)->callAction('create', data: ['exam_id' => $this->exam->id, 'title' => 'Test paper', 'slug' => 'test-paper', 'year' => 2025, 'is_published' => true])->assertHasNoActionErrors();
    $paper = PastPaper::firstOrFail();
    $data = ['exam_id' => $this->exam->id, 'category_id' => $this->category->id, 'past_paper_id' => $paper->id, 'code' => 'Q-001', 'body' => 'Manual question', 'options' => ['A' => 'One', 'B' => 'Two', 'C' => '', 'D' => '', 'E' => ''], 'correct_option' => 'B', 'explanation' => 'Manual explanation', 'is_published' => true];
    Livewire::test(ManageQuestions::class)->callAction('create', data: $data)->assertHasNoActionErrors();
    $question = Question::firstOrFail();
    expect($question->options)->toBe(['A' => 'One', 'B' => 'Two']);
    Livewire::test(ManageQuestions::class)->mountTableAction('edit', $question)->assertTableActionDataSet(['correct_option' => 'B', 'explanation' => 'Manual explanation'])->setTableActionData(['body' => 'Updated question'])->callMountedTableAction()->assertHasNoTableActionErrors();
    expect($question->fresh()->body)->toBe('Updated question');
    Livewire::test(ManageQuestions::class)->callTableAction('delete', $question)->assertHasNoTableActionErrors();
    $this->assertSoftDeleted($question);
});

it('queues a private file upload rather than processing it in the web request', function () {
    Queue::fake();
    $this->actingAs($this->staff);
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    $file = UploadedFile::fake()->createWithContent('questions.csv', "code,category_slug,question,option_a,option_b,correct_option\nQ1,cardiology,Test question,One,Two,A\n");
    Livewire::test(ManageQuestionImports::class)->callAction('create', data: ['exam_id' => $this->exam->id, 'path' => $file, 'publish' => true])->assertHasNoActionErrors();
    $import = QuestionImport::firstOrFail();
    expect($import->status)->toBe('pending')->and($import->user_id)->toBe($this->staff->id);
    Storage::disk('local')->assertExists($import->path);
    Queue::assertPushed(ImportQuestions::class, fn ($job) => $job->importId === $import->id && $job->queue === 'imports');
    expect(Question::count())->toBe(0);
    (new ImportQuestions($import->id))->handle(new QuestionFileReader);
    expect(Question::count())->toBe(1)->and($import->fresh()->status)->toBe('completed');
});

it('imports CSV with bounded bulk queries, rejects invalid rows and is retry safe', function () {
    $csv = "code,category_slug,question,option_a,option_b,correct_option,explanation\n";
    for ($i = 1; $i <= 1200; $i++) {
        $csv .= "Q$i,cardiology,Question $i,One,Two,A,Explanation\n";
    }
    $csv .= "BAD,wrong-exam-category,Invalid,One,Two,A,Explanation\n";
    Storage::disk('local')->put('question-imports/batch.csv', $csv);
    $import = QuestionImport::create(['user_id' => $this->staff->id, 'exam_id' => $this->exam->id, 'path' => 'question-imports/batch.csv', 'publish' => true]);
    DB::enableQueryLog();
    (new ImportQuestions($import->id))->handle(new QuestionFileReader);
    $inserts = collect(DB::getQueryLog())->filter(fn ($query) => str_starts_with($query['query'], 'insert into "questions"'))->count();
    DB::disableQueryLog();
    DB::flushQueryLog();
    expect(Question::count())->toBe(1200)->and($inserts)->toBe(12);
    $import->refresh();
    expect($import->status)->toBe('completed')->and($import->imported)->toBe(1200)->and($import->rejected)->toBe(1)->and($import->errors[0]['row'])->toBe(1202);
    (new ImportQuestions($import->id))->handle(new QuestionFileReader);
    expect(Question::count())->toBe(1200)->and($import->fresh()->imported)->toBe(1200);
});

it('streams XLSX rows and updates matching codes without duplicating questions', function () {
    $question = contentQuestion($this->exam, $this->category, ['code' => 'EXISTING']);
    Storage::disk('local')->makeDirectory('question-imports');
    $writer = new Writer;
    $writer->openToFile(Storage::disk('local')->path('question-imports/book.xlsx'));
    $writer->addRow(Row::fromValues(['code', 'category_slug', 'question', 'option_a', 'option_b', 'correct_option', 'explanation']));
    $writer->addRow(Row::fromValues(['EXISTING', 'cardiology', 'Updated from XLSX', 'One', 'Two', 'B', 'New explanation']));
    $writer->close();
    $import = QuestionImport::create(['user_id' => $this->staff->id, 'exam_id' => $this->exam->id, 'path' => 'question-imports/book.xlsx', 'publish' => true]);
    (new ImportQuestions($import->id))->handle(new QuestionFileReader);
    expect(Question::count())->toBe(1)->and($question->fresh()->body)->toBe('Updated from XLSX')->and($question->fresh()->correct_option)->toBe('B');
});

it('shows published content only and rejects cross-exam session filters', function () {
    $user = User::factory()->create();
    $this->actingAs($user)->withSession(['exam_id' => $this->exam->id]);
    contentQuestion($this->exam, $this->category);
    contentQuestion($this->exam, $this->category, ['is_published' => false]);
    $paper = PastPaper::create(['exam_id' => $this->exam->id, 'title' => 'Hidden paper', 'slug' => 'hidden', 'year' => 2025]);
    contentQuestion($this->exam, $this->category, ['past_paper_id' => $paper->id]);
    $this->get('/questions/bank')->assertInertia(fn (Assert $page) => $page->where('availableQuestions', 1)->where('categories.data.0.questions_count', 1));
    $this->get('/questions/past-papers')->assertInertia(fn (Assert $page) => $page->has('papers.data', 0));
    $otherCategory = Category::factory()->create();
    $this->post('/questions/sessions', ['count' => 10, 'category_id' => $otherCategory->id])->assertSessionHasErrors('category_id');
    $this->post('/questions/sessions', ['count' => 251])->assertSessionHasErrors('count');
});

it('persists sessions, hides answers until submission, and scores idempotently', function () {
    $user = User::factory()->create();
    $question = contentQuestion($this->exam, $this->category);
    $this->actingAs($user)->withSession(['exam_id' => $this->exam->id])->post('/questions/sessions', ['count' => 10])->assertRedirect();
    $session = PracticeSession::firstOrFail();
    expect($session->total_questions)->toBe(1);
    $url = '/questions/sessions/'.$session->id;
    $this->get($url)->assertInertia(fn (Assert $page) => $page->missing('question.result')->missing('question.correct_option')->missing('question.explanation')->where('question.body', $question->body));
    $question->update(['correct_option' => 'B', 'explanation' => 'Changed after session creation']);
    $this->post($url.'/answer/1', ['option' => 'E'])->assertSessionHasErrors('option');
    $this->post($url.'/answer/1', ['option' => 'A'])->assertRedirect($url.'?position=1');
    $this->post($url.'/answer/1', ['option' => 'A'])->assertRedirect();
    $this->post($url.'/answer/1', ['option' => 'B'])->assertSessionHasErrors('option');
    $session->refresh();
    expect($session->answered_count)->toBe(1)->and($session->correct_count)->toBe(1)->and($session->completed_at)->not->toBeNull();
    $this->get($url)->assertInertia(fn (Assert $page) => $page->where('question.result.correct_option', 'A')->where('question.result.explanation', 'Explanation for the saved answer'));
    $this->get('/questions/sessions')->assertInertia(fn (Assert $page) => $page->component('questions/Sessions')->missing('sessions'));
    $this->getJson('/questions/sessions/data?draw=1&start=0&length=10')->assertOk()
        ->assertJsonPath('recordsTotal', 1)->assertJsonPath('data.0.id', $session->id)
        ->assertJsonPath('data.0.status', 'Completed')->assertJsonPath('data.0.accuracy', 100);
    $this->get('/dashboard')->assertInertia(fn (Assert $page) => $page->where('stats.answered', 1)->where('stats.correct', 1)->where('stats.completed', 1));
});

it('prevents students accessing other sessions and all content admin screens', function () {
    contentQuestion($this->exam, $this->category);
    $owner = User::factory()->create();
    $session = app(PracticeService::class)->start($owner, $this->exam, 1, null, null);
    $this->actingAs(User::factory()->create());
    $this->get('/questions/sessions/'.$session->id)->assertForbidden();
    $this->post('/questions/sessions/'.$session->id.'/answer/1', ['option' => 'A'])->assertForbidden();
    foreach (['/admin/questions', '/admin/past-papers', '/admin/question-imports', '/admin/mcq-template'] as $url) {
        $this->get($url)->assertForbidden();
    }
});

it('rejects missing headers, invalid answer keys and cross-exam question data', function () {
    Storage::disk('local')->put('question-imports/bad.csv', "wrong,headers\ninvalid,row\n");
    $import = QuestionImport::create(['user_id' => $this->staff->id, 'exam_id' => $this->exam->id, 'path' => 'question-imports/bad.csv']);
    (new ImportQuestions($import->id))->handle(new QuestionFileReader);
    expect($import->fresh()->status)->toBe('failed')->and($import->fresh()->failure)->toContain('headers');
    expect(fn () => contentQuestion($this->exam, $this->category, ['correct_option' => 'E']))->toThrow(ValidationException::class);
    expect(fn () => contentQuestion($this->exam, Category::factory()->create()))->toThrow(ValidationException::class);
});

it('resumes the next unanswered question and restricts paper sessions to published papers', function () {
    $paper = PastPaper::create(['exam_id' => $this->exam->id, 'title' => 'Paper practice', 'slug' => 'paper-practice', 'year' => 2025, 'is_published' => false]);
    contentQuestion($this->exam, $this->category, ['past_paper_id' => $paper->id]);
    contentQuestion($this->exam, $this->category, ['past_paper_id' => $paper->id]);
    contentQuestion($this->exam, $this->category);
    $this->actingAs(User::factory()->create())->withSession(['exam_id' => $this->exam->id]);
    $this->post('/questions/sessions', ['count' => 10, 'past_paper_id' => $paper->id])->assertSessionHasErrors('past_paper_id');
    $paper->update(['is_published' => true]);
    $this->post('/questions/sessions', ['count' => 10, 'past_paper_id' => $paper->id])->assertRedirect();
    $session = PracticeSession::firstOrFail();
    expect($session->total_questions)->toBe(2);
    $this->post('/questions/sessions/'.$session->id.'/answer/1', ['option' => 'A'])->assertRedirect();
    $this->get('/questions/sessions/'.$session->id)->assertInertia(fn (Assert $page) => $page->where('question.position', 2)->missing('question.result')->where('session.answered_count', 1));
    expect($session->fresh()->completed_at)->toBeNull();
    $this->post('/questions/sessions/'.$session->id.'/answer/2', ['option' => 'B'])->assertRedirect();
    expect($session->fresh()->answered_count)->toBe(2)->and($session->fresh()->correct_count)->toBe(1)->and($session->fresh()->completed_at)->not->toBeNull();
});
