<?php

use App\Models\Category;
use App\Models\Exam;
use App\Models\PastPaper;
use App\Models\PracticeSession;
use App\Models\Question;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

function subjectQuestion(Exam $exam, Category $category, ?PastPaper $paper = null, array $overrides = []): Question
{
    return Question::create(array_merge([
        'exam_id' => $exam->id, 'category_id' => $category->id, 'past_paper_id' => $paper?->id,
        'code' => fake()->unique()->bothify('SP-########'), 'body' => 'Subject question',
        'options' => ['A' => 'First option', 'B' => 'Second option'], 'correct_option' => 'A',
        'explanation' => 'Because of the guideline.', 'is_published' => true,
    ], $overrides));
}

function sessionQuestionIds(PracticeSession $session): array
{
    return $session->items()->orderBy('position')->pluck('question_id')->map(fn ($id) => (int) $id)->all();
}

beforeEach(function () {
    $this->exam = Exam::factory()->create(['is_active' => true]);
    $this->category = Category::factory()->create(['exam_id' => $this->exam->id, 'name' => 'Cardiology', 'slug' => 'cardiology']);
    $this->paper = PastPaper::create(['exam_id' => $this->exam->id, 'title' => 'Paper 1', 'slug' => 'paper-1', 'year' => 2024, 'is_published' => true]);
    $this->user = User::factory()->create();
});

it('counts every MCQ, past paper ones included, on the question bank', function () {
    subjectQuestion($this->exam, $this->category);
    subjectQuestion($this->exam, $this->category, $this->paper);

    $this->actingAs($this->user)->withSession(['exam_id' => $this->exam->id])
        ->get('/questions/bank')
        ->assertInertia(fn (Assert $page) => $page
            ->component('questions/Bank')
            ->where('availableQuestions', 2)
            ->where('categories.data.0.slug', 'cardiology')
            ->where('categories.data.0.questions_count', 2)
        );
});

it('counts past paper questions per subject on the past papers page', function () {
    subjectQuestion($this->exam, $this->category);
    subjectQuestion($this->exam, $this->category, $this->paper);
    subjectQuestion($this->exam, $this->category, $this->paper);

    $this->actingAs($this->user)->withSession(['exam_id' => $this->exam->id])
        ->get('/questions/past-papers')
        ->assertInertia(fn (Assert $page) => $page
            ->component('questions/Papers')
            ->where('subjects.data.0.slug', 'cardiology')
            ->where('subjects.data.0.questions_count', 2)
            ->has('papers.data', 1)
        );
});

it('practises the whole bank, past paper MCQs included, when the source is main', function () {
    $main = subjectQuestion($this->exam, $this->category);
    $past = subjectQuestion($this->exam, $this->category, $this->paper);

    $this->actingAs($this->user)->withSession(['exam_id' => $this->exam->id])
        ->post('/questions/sessions', ['count' => 10, 'source' => 'main'])
        ->assertRedirect();

    expect(collect(sessionQuestionIds(PracticeSession::latest('id')->firstOrFail()))->sort()->values()->all())->toBe([$main->id, $past->id]);
});

it('practises only past paper questions when the source is past', function () {
    subjectQuestion($this->exam, $this->category);
    $past = subjectQuestion($this->exam, $this->category, $this->paper);

    $this->actingAs($this->user)->withSession(['exam_id' => $this->exam->id])
        ->post('/questions/sessions', ['count' => 10, 'source' => 'past'])
        ->assertRedirect();

    expect(sessionQuestionIds(PracticeSession::latest('id')->firstOrFail()))->toBe([$past->id]);
});

it('rejects an unknown practice source', function () {
    subjectQuestion($this->exam, $this->category);

    $this->actingAs($this->user)->withSession(['exam_id' => $this->exam->id])
        ->post('/questions/sessions', ['count' => 10, 'source' => 'everything'])
        ->assertSessionHasErrors('source');
    expect(PracticeSession::count())->toBe(0);
});

it('shows the main bank of a subject with both counts', function () {
    subjectQuestion($this->exam, $this->category);
    subjectQuestion($this->exam, $this->category, $this->paper);

    $this->actingAs($this->user)->withSession(['exam_id' => $this->exam->id])
        ->get('/questions/subjects/cardiology')
        ->assertInertia(fn (Assert $page) => $page
            ->component('questions/Subject')
            ->where('subject.slug', 'cardiology')
            ->where('subject.name', 'Cardiology')
            ->where('source', 'main')
            ->where('counts.main', 2)
            ->where('counts.past', 1)
        );
});

it('shows the past paper MCQs of a subject', function () {
    subjectQuestion($this->exam, $this->category, $this->paper);

    $this->actingAs($this->user)->withSession(['exam_id' => $this->exam->id])
        ->get('/questions/subjects/cardiology/past-papers')
        ->assertInertia(fn (Assert $page) => $page
            ->component('questions/Subject')
            ->where('source', 'past')
            ->where('counts.past', 1)
        );
});

it('starts a subject practice from the subject page with its source', function () {
    subjectQuestion($this->exam, $this->category);
    $past = subjectQuestion($this->exam, $this->category, $this->paper);

    $this->actingAs($this->user)->withSession(['exam_id' => $this->exam->id])
        ->post('/questions/sessions', ['count' => 5, 'category_id' => $this->category->id, 'source' => 'past'])
        ->assertRedirect();

    expect(sessionQuestionIds(PracticeSession::latest('id')->firstOrFail()))->toBe([$past->id]);
});

it('redirects a subject that belongs to another exam to the bank', function () {
    $other = Exam::factory()->create(['is_active' => true]);
    Category::factory()->create(['exam_id' => $other->id, 'name' => 'Renal', 'slug' => 'renal']);

    $this->actingAs($this->user)->withSession(['exam_id' => $this->exam->id]);
    $this->get('/questions/subjects/renal')->assertRedirect('/questions/bank');
    $this->get('/questions/subjects/renal/past-papers')->assertRedirect('/questions/bank');
});

it('prefers the selected exam when the same subject slug exists in two exams', function () {
    $other = Exam::factory()->create(['is_active' => true]);
    Category::factory()->create(['exam_id' => $other->id, 'name' => 'Cardiology', 'slug' => 'cardiology']);

    $this->actingAs($this->user)->withSession(['exam_id' => $this->exam->id])
        ->get('/questions/subjects/cardiology')
        ->assertInertia(fn (Assert $page) => $page->where('subject.id', $this->category->id));
});

it('returns 404 for an unknown subject or a slug outside the allowed pattern', function () {
    $this->actingAs($this->user)->withSession(['exam_id' => $this->exam->id]);

    $this->get('/questions/subjects/does-not-exist')->assertNotFound();
    $this->get('/questions/subjects/does-not-exist/past-papers')->assertNotFound();
    $this->get('/questions/subjects/Cardiology')->assertNotFound();
    $this->get('/questions/subjects/bad_slug')->assertNotFound();
});

it('lists the MCQs of a subject with the case and the question, but never the answer or the topic', function () {
    subjectQuestion($this->exam, $this->category, $this->paper, [
        'code' => 'LIST-1', 'image_search' => 'Hypertrophic cardiomyopathy',
        'body' => "A 24-year-old man faints while running.\n\n**Investigations**\n\n| Investigation | Result |\n| --- | --- |\n| ECG | Normal |\n\nWhich of the following is the most appropriate intervention?",
    ]);

    $this->actingAs($this->user)->withSession(['exam_id' => $this->exam->id])
        ->get('/questions/subjects/cardiology')
        ->assertInertia(fn (Assert $page) => $page
            ->where('questions.data.0.code', 'LIST-1')
            ->where('questions.data.0.excerpt', 'A 24-year-old man faints while running.')
            ->where('questions.data.0.prompt', 'Which of the following is the most appropriate intervention?')
            ->where('questions.data.0.paper', 'Paper 1')
            ->missing('questions.data.0.topic')
            ->missing('questions.data.0.correct_option')
            ->missing('questions.data.0.explanation')
            ->missing('questions.data.0.options'));
});

it('practises one MCQ from the subject list, and only one of this subject', function () {
    $question = subjectQuestion($this->exam, $this->category);
    $other = subjectQuestion($this->exam, Category::factory()->create(['exam_id' => $this->exam->id, 'slug' => 'neurology']));

    $this->actingAs($this->user)->withSession(['exam_id' => $this->exam->id])
        ->post('/questions/subjects/cardiology/practise-one', ['question_id' => $question->id])
        ->assertRedirect();
    expect(sessionQuestionIds(PracticeSession::latest('id')->firstOrFail()))->toBe([$question->id]);

    $this->post('/questions/subjects/cardiology/practise-one', ['question_id' => $other->id])->assertNotFound();
});
