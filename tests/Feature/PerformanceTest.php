<?php

use App\Models\Category;
use App\Models\Exam;
use App\Models\Question;
use App\Models\User;
use App\Services\PracticeService;
use App\Services\ReviewService;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;

function performanceQuestion(Exam $exam, Category $category, string $body): Question
{
    return Question::create([
        'exam_id' => $exam->id, 'category_id' => $category->id, 'code' => fake()->unique()->bothify('P-########'),
        'body' => $body, 'options' => ['A' => 'First option', 'B' => 'Second option'],
        'correct_option' => 'A', 'explanation' => 'Performance explanation', 'is_published' => true,
    ]);
}

beforeEach(function () {
    $this->exam = Exam::factory()->create();
    $this->category = Category::factory()->create(['exam_id' => $this->exam->id, 'slug' => 'cardiology', 'name' => 'Cardiology']);
    $this->otherCategory = Category::factory()->create(['exam_id' => $this->exam->id, 'slug' => 'respiratory', 'name' => 'Respiratory']);
    $this->user = User::factory()->create();
    $this->practice = app(PracticeService::class);
    $this->review = app(ReviewService::class);
});

it('reports the accuracy, the category breakdown and the weakest questions', function () {
    $cardiology = performanceQuestion($this->exam, $this->category, 'Cardiology question text');
    $respiratory = performanceQuestion($this->exam, $this->otherCategory, 'Respiratory question text');
    $this->actingAs($this->user)->withSession(['exam_id' => $this->exam->id]);
    $first = $this->review->start($this->user, $this->exam, [$cardiology->id]);
    $this->practice->answer($first, 1, 'A');
    $second = $this->review->start($this->user, $this->exam, [$cardiology->id, $respiratory->id]);
    $cardiologyPosition = (int) $second->items()->where('question_id', $cardiology->id)->value('position');
    $respiratoryPosition = (int) $second->items()->where('question_id', $respiratory->id)->value('position');
    $this->practice->answer($second, $cardiologyPosition, 'B');
    $this->practice->answer($second, $respiratoryPosition, 'A');
    $this->get('/questions/performance')->assertInertia(fn (Assert $page) => $page
        ->where('summary.answered', 3)
        ->where('summary.correct', 2)
        ->where('summary.accuracy', 67)
        ->where('summary.sessions', 2)
        ->where('summary.completed', 2)
        ->where('categories.0.name', 'Cardiology')
        ->where('categories.0.answered', 2)
        ->where('categories.0.accuracy', 50)
        ->where('categories.1.name', 'Respiratory')
        ->where('categories.1.answered', 1)
        ->where('categories.1.accuracy', 100)
        ->where('weakest.0.body', 'Cardiology question text')
        ->where('weakest.0.wrong_count', 1)
        ->has('trend', 14)
    );
});

it('counts a study streak of consecutive days', function () {
    $question = performanceQuestion($this->exam, $this->category, 'Streak question text');
    $this->actingAs($this->user)->withSession(['exam_id' => $this->exam->id]);
    foreach (['2026-10-19 09:00:00', '2026-10-20 09:00:00', '2026-10-21 09:00:00'] as $day) {
        Carbon::setTestNow($day);
        $session = $this->review->start($this->user, $this->exam, [$question->id]);
        $this->practice->answer($session, 1, 'A');
    }
    $this->get('/questions/performance')->assertInertia(fn (Assert $page) => $page->where('streak', 3)->where('summary.answered', 3));
    Carbon::setTestNow('2026-10-24 09:00:00');
    $this->get('/questions/performance')->assertInertia(fn (Assert $page) => $page->where('streak', 0));
});

it('keeps the performance figures of one student out of another account', function () {
    $question = performanceQuestion($this->exam, $this->category, 'Private question text');
    $other = User::factory()->create();
    $session = $this->review->start($other, $this->exam, [$question->id]);
    $this->practice->answer($session, 1, 'A');
    $this->actingAs($this->user)->withSession(['exam_id' => $this->exam->id]);
    $this->get('/questions/performance')->assertInertia(fn (Assert $page) => $page->where('summary.answered', 0)->where('summary.accuracy', 0)->where('streak', 0));
});
