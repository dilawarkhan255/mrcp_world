<?php

use App\Models\Category;
use App\Models\Exam;
use App\Models\PastPaper;
use App\Models\PracticeSession;
use App\Models\Question;
use App\Models\User;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

function searchMcq(Exam $exam, Category $category, array $overrides = []): Question
{
    return Question::create(array_merge([
        'exam_id' => $exam->id, 'category_id' => $category->id, 'code' => fake()->unique()->bothify('SRCH-####'),
        'body' => 'Search stem', 'options' => ['A' => 'First option', 'B' => 'Second option'], 'correct_option' => 'A',
        'explanation' => 'Explanation.', 'is_published' => true,
    ], $overrides));
}

function searchIds(PracticeSession $session): array
{
    return $session->items()->orderBy('position')->pluck('question_id')->map(fn ($id) => (int) $id)->all();
}

beforeEach(function () {
    $this->exam = Exam::factory()->create(['is_active' => true]);
    $this->category = Category::factory()->create(['exam_id' => $this->exam->id, 'name' => 'Cardiology', 'slug' => 'cardiology']);
    $this->user = User::factory()->create();
    $this->actingAs($this->user)->withSession(['exam_id' => $this->exam->id]);
});

it('finds published questions by words in the stem and by exact code', function () {
    searchMcq($this->exam, $this->category, ['body' => 'A patient presents with crushing chest pain.', 'code' => 'CARD-0001']);
    searchMcq($this->exam, $this->category, ['body' => 'Renal tubular acidosis causes hypokalaemia.', 'code' => 'REN-0001']);

    $this->get('/questions/search?q='.urlencode('chest pain'))
        ->assertInertia(fn (Assert $page) => $page->component('questions/Search')->where('total', 1)->where('results.data.0.code', 'CARD-0001'));

    $this->get('/questions/search?q=REN-0001')
        ->assertInertia(fn (Assert $page) => $page->where('total', 1)->where('results.data.0.code', 'REN-0001'));
});

it('keeps the LIKE wildcards % and _ literal', function () {
    searchMcq($this->exam, $this->category, ['body' => 'Shows a 100% response to treatment.', 'code' => 'P-1']);
    searchMcq($this->exam, $this->category, ['body' => 'Shows a 100 percent response to treatment.', 'code' => 'P-2']);
    searchMcq($this->exam, $this->category, ['body' => 'Gene a_b expression is raised.', 'code' => 'U-1']);
    searchMcq($this->exam, $this->category, ['body' => 'Gene axb expression is raised.', 'code' => 'U-2']);

    $this->get('/questions/search?q='.urlencode('100%'))
        ->assertInertia(fn (Assert $page) => $page->where('total', 1)->where('results.data.0.code', 'P-1'));

    $this->get('/questions/search?q='.urlencode('a_b'))
        ->assertInertia(fn (Assert $page) => $page->where('total', 1)->where('results.data.0.code', 'U-1'));

    // A bare wildcard pattern matches nothing, because it is searched literally.
    $this->get('/questions/search?q='.urlencode('%%'))
        ->assertInertia(fn (Assert $page) => $page->where('total', 0));
});

it('never returns unpublished questions or questions in an unpublished paper', function () {
    searchMcq($this->exam, $this->category, ['body' => 'Zebrafish stem keyword', 'is_published' => false]);
    $draftPaper = PastPaper::create(['exam_id' => $this->exam->id, 'title' => 'Draft', 'slug' => 'draft', 'year' => 2025, 'is_published' => false]);
    searchMcq($this->exam, $this->category, ['body' => 'Zebrafish stem keyword', 'past_paper_id' => $draftPaper->id]);

    $this->get('/questions/search?q=zebrafish')
        ->assertInertia(fn (Assert $page) => $page->where('total', 0)->has('results.data', 0));
});

it('only searches the selected exam', function () {
    $other = Exam::factory()->create(['is_active' => true]);
    $otherCategory = Category::factory()->create(['exam_id' => $other->id, 'name' => 'Other', 'slug' => 'other']);
    searchMcq($other, $otherCategory, ['body' => 'Otherexam keyword stem']);

    $this->get('/questions/search?q=otherexam')
        ->assertInertia(fn (Assert $page) => $page->where('total', 0));
});

it('requires at least two characters and shows the prompt without a query', function () {
    $this->get('/questions/search?q=a')->assertSessionHasErrors('q');

    $this->get('/questions/search')
        ->assertInertia(fn (Assert $page) => $page->component('questions/Search')->where('results', null)->where('total', 0));
});

it('shows a 220-character excerpt with the subject and the source', function () {
    $paper = PastPaper::create(['exam_id' => $this->exam->id, 'title' => 'Paper 1', 'slug' => 'paper-1', 'year' => 2024, 'is_published' => true]);
    $long = 'Keyword '.str_repeat('lorem ipsum ', 60);
    searchMcq($this->exam, $this->category, ['body' => $long, 'past_paper_id' => $paper->id, 'code' => 'LONG-1']);

    $this->get('/questions/search?q=keyword')
        ->assertInertia(fn (Assert $page) => $page
            ->where('results.data.0.excerpt', Str::limit($long, 220))
            ->where('results.data.0.subject', 'Cardiology')
            ->where('results.data.0.source', 'Past paper'));
});

it('practises the first matches in a practice session', function () {
    $first = searchMcq($this->exam, $this->category, ['body' => 'Cardiology stem one']);
    $second = searchMcq($this->exam, $this->category, ['body' => 'Cardiology stem two']);
    $third = searchMcq($this->exam, $this->category, ['body' => 'Cardiology stem three']);
    searchMcq($this->exam, $this->category, ['body' => 'Unrelated stem']);

    $this->post('/questions/search/practice', ['q' => 'cardiology'])->assertRedirect();

    $session = PracticeSession::latest('id')->firstOrFail();
    expect($session->mode)->toBe(PracticeSession::MODE_PRACTICE)
        ->and(searchIds($session))->toEqualCanonicalizing([$first->id, $second->id, $third->id]);
});

it('reports a validation error when a search has no matches to practise', function () {
    searchMcq($this->exam, $this->category, ['body' => 'Cardiology stem']);

    $this->post('/questions/search/practice', ['q' => 'nomatchxyz'])->assertSessionHasErrors('q');
    expect(PracticeSession::count())->toBe(0);
});

it('does not treat a whitespace-only or padded short search as a match-all query', function () {
    searchMcq($this->exam, $this->category, ['body' => 'Cardiology stem']);

    $this->post('/questions/search/practice', ['q' => '   '])->assertSessionHasErrors('q');
    $this->get('/questions/search?q='.urlencode(' a '))->assertSessionHasErrors('q');
    expect(PracticeSession::count())->toBe(0);
});
