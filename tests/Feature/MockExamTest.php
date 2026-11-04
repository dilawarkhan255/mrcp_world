<?php

use App\Models\Category;
use App\Models\Exam;
use App\Models\PracticeSession;
use App\Models\Question;
use App\Models\User;
use App\Services\PracticeService;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;

function mockQuestion(Exam $exam, Category $category, array $overrides = []): Question
{
    return Question::create(array_merge([
        'exam_id' => $exam->id, 'category_id' => $category->id, 'code' => fake()->unique()->bothify('M-########'),
        'body' => 'Mock question text', 'options' => ['A' => 'First option', 'B' => 'Second option'],
        'correct_option' => 'A', 'explanation' => 'Mock explanation', 'is_published' => true,
    ], $overrides));
}

beforeEach(function () {
    $this->exam = Exam::factory()->create();
    $this->category = Category::factory()->create(['exam_id' => $this->exam->id, 'slug' => 'general']);
    $this->user = User::factory()->create();
});

it('starts a timed mock that only counts published questions', function () {
    mockQuestion($this->exam, $this->category);
    mockQuestion($this->exam, $this->category, ['is_published' => false]);
    Carbon::setTestNow('2026-10-21 10:00:00');
    $this->actingAs($this->user)->withSession(['exam_id' => $this->exam->id])
        ->post('/questions/mocks', ['count' => 5, 'minutes' => 10])->assertRedirect();
    $session = PracticeSession::firstOrFail();
    expect($session->mode)->toBe(PracticeSession::MODE_MOCK)
        ->and($session->total_questions)->toBe(1)
        ->and($session->duration_seconds)->toBe(600)
        ->and($session->expires_at?->toDateTimeString())->toBe('2026-10-21 10:10:00');
});

it('closes the mock on the server when the countdown reaches zero', function () {
    mockQuestion($this->exam, $this->category);
    Carbon::setTestNow('2026-10-21 10:00:00');
    $this->actingAs($this->user)->withSession(['exam_id' => $this->exam->id])
        ->post('/questions/mocks', ['count' => 5, 'minutes' => 10])->assertRedirect();
    $session = PracticeSession::firstOrFail();
    Carbon::setTestNow('2026-10-21 10:11:00');
    $this->post('/questions/mocks/'.$session->id.'/answer/1', ['option' => 'A'])->assertSessionHasErrors('option');
    $this->get('/questions/mocks/'.$session->id)->assertInertia(fn (Assert $page) => $page->where('session.completed_at', fn ($value) => $value !== null));
    $session->refresh();
    expect($session->completed_at)->not->toBeNull()->and($session->answered_count)->toBe(0)->and($session->correct_count)->toBe(0);
});

it('keeps the correct answer hidden until the mock is closed', function () {
    mockQuestion($this->exam, $this->category);
    mockQuestion($this->exam, $this->category);
    $this->actingAs($this->user)->withSession(['exam_id' => $this->exam->id]);
    $this->post('/questions/mocks', ['count' => 5, 'minutes' => 10])->assertRedirect();
    $session = PracticeSession::firstOrFail();
    $url = '/questions/mocks/'.$session->id;
    $this->get($url)->assertInertia(fn (Assert $page) => $page->missing('question.result')->where('question.body', 'Mock question text'));
    $this->post($url.'/answer/1', ['option' => 'A'])->assertRedirect();
    $this->get($url.'?position=1')->assertInertia(fn (Assert $page) => $page->missing('question.result')->where('session.answered_count', 1));
    $this->post($url.'/finish')->assertRedirect();
    $this->get($url.'?position=1')->assertInertia(fn (Assert $page) => $page
        ->has('question.result', fn (Assert $result) => $result->where('is_correct', true)->where('correct_option', 'A')->where('explanation', 'Mock explanation')->etc())
        ->where('session.correct_count', 1)
    );
});

it('rejects answers outside the allowed options and closes on request', function () {
    mockQuestion($this->exam, $this->category);
    $this->actingAs($this->user)->withSession(['exam_id' => $this->exam->id]);
    $this->post('/questions/mocks', ['count' => 5, 'minutes' => 10])->assertRedirect();
    $session = PracticeSession::firstOrFail();
    $url = '/questions/mocks/'.$session->id;
    $this->post($url.'/answer/1', ['option' => 'E'])->assertSessionHasErrors('option');
    $this->post($url.'/finish')->assertRedirect();
    expect($session->fresh()->completed_at)->not->toBeNull();
});

it('validates the mock limits and lists the attempts of the selected exam', function () {
    mockQuestion($this->exam, $this->category);
    $this->actingAs($this->user)->withSession(['exam_id' => $this->exam->id]);
    $this->post('/questions/mocks', ['count' => 0, 'minutes' => 10])->assertSessionHasErrors('count');
    $this->post('/questions/mocks', ['count' => 10, 'minutes' => 500])->assertSessionHasErrors('minutes');
    $this->post('/questions/mocks', ['count' => 10, 'minutes' => 30])->assertRedirect();
    $this->getJson('/questions/mocks/data?draw=1&start=0&length=10')->assertOk()
        ->assertJsonPath('recordsTotal', 1)->assertJsonPath('data.0.kind', 'Mock')->assertJsonPath('data.0.status', 'In progress');
    $this->get('/questions/mocks')->assertInertia(fn (Assert $page) => $page
        ->missing('attempts')
        ->where('presets.0.title', 'Quick check')
        ->where('limits.maxMinutes', 180)
    );
});

it('prevents students from opening or answering another student mock', function () {
    mockQuestion($this->exam, $this->category);
    $owner = User::factory()->create();
    $session = app(PracticeService::class)->start($owner, $this->exam, 1, null, null, PracticeSession::MODE_MOCK, 600);
    $this->actingAs(User::factory()->create());
    $this->get('/questions/mocks/'.$session->id)->assertForbidden();
    $this->post('/questions/mocks/'.$session->id.'/answer/1', ['option' => 'A'])->assertForbidden();
    $this->post('/questions/mocks/'.$session->id.'/finish')->assertForbidden();
});
