<?php

use App\Models\Category;
use App\Models\Exam;
use App\Models\PracticeSession;
use App\Models\Question;
use App\Models\User;
use App\Services\PracticeService;
use App\Services\ReviewService;
use Inertia\Testing\AssertableInertia as Assert;

function reviewQuestion(Exam $exam, Category $category, array $overrides = []): Question
{
    return Question::create(array_merge([
        'exam_id' => $exam->id, 'category_id' => $category->id, 'code' => fake()->unique()->bothify('R-########'),
        'body' => 'Review question text', 'options' => ['A' => 'First option', 'B' => 'Second option'],
        'correct_option' => 'A', 'explanation' => 'Review explanation', 'is_published' => true,
    ], $overrides));
}

beforeEach(function () {
    $this->exam = Exam::factory()->create();
    $this->category = Category::factory()->create(['exam_id' => $this->exam->id, 'slug' => 'general']);
    $this->user = User::factory()->create();
    $this->practice = app(PracticeService::class);
    $this->review = app(ReviewService::class);
});

it('keeps a wrongly answered question in the queue until it is answered correctly', function () {
    $question = reviewQuestion($this->exam, $this->category);
    $this->actingAs($this->user)->withSession(['exam_id' => $this->exam->id]);
    $session = $this->review->start($this->user, $this->exam, [$question->id]);
    $this->practice->answer($session, 1, 'B');
    $this->get('/questions/review')->assertInertia(fn (Assert $page) => $page
        ->has('items.data', 1)
        ->where('items.data.0.id', $question->id)
        ->where('items.data.0.student_option', 'B')
        ->where('items.data.0.correct_option', 'A')
        ->where('items.data.0.explanation', 'Review explanation')
        ->where('items.data.0.wrong_count', 1)
        ->where('stats.queue', 1)
        ->where('stats.answered', 1)
    );
    $second = $this->review->start($this->user, $this->exam, [$question->id]);
    $this->practice->answer($second, 1, 'A');
    $this->get('/questions/review')->assertInertia(fn (Assert $page) => $page->has('items.data', 0)->where('stats.queue', 0));
});

it('starts a review session from the whole queue', function () {
    $question = reviewQuestion($this->exam, $this->category);
    $this->actingAs($this->user)->withSession(['exam_id' => $this->exam->id]);
    $session = $this->review->start($this->user, $this->exam, [$question->id]);
    $this->practice->answer($session, 1, 'B');
    $this->post('/questions/review/practice')->assertRedirect();
    $review = PracticeSession::where('mode', PracticeSession::MODE_REVIEW)->firstOrFail();
    expect($review->total_questions)->toBe(1)->and($review->user_id)->toBe($this->user->id);
});

it('starts a review session from the chosen questions only', function () {
    $first = reviewQuestion($this->exam, $this->category);
    $second = reviewQuestion($this->exam, $this->category);
    $this->actingAs($this->user)->withSession(['exam_id' => $this->exam->id]);
    $this->post('/questions/review/practice', ['question_ids' => [$first->id, $second->id]])->assertRedirect();
    $review = PracticeSession::where('mode', PracticeSession::MODE_REVIEW)->firstOrFail();
    expect($review->total_questions)->toBe(2);
});

it('ignores questions from another exam and answers from another student', function () {
    $question = reviewQuestion($this->exam, $this->category);
    $otherExam = Exam::factory()->create();
    $otherCategory = Category::factory()->create(['exam_id' => $otherExam->id, 'slug' => 'other']);
    $otherQuestion = reviewQuestion($otherExam, $otherCategory);
    $otherUser = User::factory()->create();
    $session = $this->review->start($otherUser, $otherExam, [$otherQuestion->id]);
    $this->practice->answer($session, 1, 'B');
    $this->actingAs($this->user)->withSession(['exam_id' => $this->exam->id]);
    $this->get('/questions/review')->assertInertia(fn (Assert $page) => $page->has('items.data', 0)->where('stats.queue', 0));
    $this->post('/questions/review/practice', ['question_ids' => [$otherQuestion->id]])->assertSessionHasErrors('question_ids');
    $this->post('/questions/review/practice', ['question_ids' => [$question->id]])->assertRedirect();
});

it('reports an empty queue instead of failing when there is nothing to review', function () {
    $this->actingAs($this->user)->withSession(['exam_id' => $this->exam->id]);
    $this->post('/questions/review/practice')->assertSessionHasErrors('question_ids');
    $this->get('/questions/review')->assertInertia(fn (Assert $page) => $page->has('items.data', 0)->where('stats.queue', 0));
});
