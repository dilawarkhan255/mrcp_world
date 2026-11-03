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

it('counts only main bank questions on the question bank', function () {
    subjectQuestion($this->exam, $this->category);
    subjectQuestion($this->exam, $this->category, $this->paper);

    $this->actingAs($this->user)->withSession(['exam_id' => $this->exam->id])
        ->get('/questions/bank')
        ->assertInertia(fn (Assert $page) => $page
            ->component('questions/Bank')
            ->where('availableQuestions', 1)
            ->where('categories.data.0.slug', 'cardiology')
            ->where('categories.data.0.questions_count', 1)
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

it('practises only main bank questions when the source is main', function () {
    $main = subjectQuestion($this->exam, $this->category);
    subjectQuestion($this->exam, $this->category, $this->paper);

    $this->actingAs($this->user)->withSession(['exam_id' => $this->exam->id])
        ->post('/questions/sessions', ['count' => 10, 'source' => 'main'])
        ->assertRedirect();

    expect(sessionQuestionIds(PracticeSession::latest('id')->firstOrFail()))->toBe([$main->id]);
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
            ->where('counts.main', 1)
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
