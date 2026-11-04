<?php

use App\Models\Category;
use App\Models\Exam;
use App\Models\PracticeSession;
use App\Models\Question;
use App\Models\User;
use App\Services\AiTutor;
use App\Services\AiTutorException;
use Inertia\Testing\AssertableInertia as Assert;

/** The AI tutor with the request to Claude replaced, so tests never call the API. */
class FakeAiTutor extends AiTutor
{
    /** @var list<string> */
    public array $prompts = [];

    public ?string $failWith = null;

    protected function send(string $prompt): string
    {
        $this->prompts[] = $prompt;
        if ($this->failWith !== null) {
            throw new AiTutorException($this->failWith);
        }

        return "**Key clue:** the stem.\n\n<script>alert(1)</script>";
    }
}

beforeEach(function () {
    config(['services.anthropic.key' => 'test-key', 'services.anthropic.daily_limit' => 3]);
    $this->fake = new FakeAiTutor;
    app()->instance(AiTutor::class, $this->fake);
    $this->exam = Exam::factory()->create();
    $category = Category::factory()->create(['exam_id' => $this->exam->id, 'name' => 'Renal']);
    foreach (range(1, 3) as $index) {
        Question::create([
            'exam_id' => $this->exam->id, 'category_id' => $category->id, 'code' => 'AI-'.$index, 'body' => 'Stem '.$index,
            'options' => ['A' => 'Right', 'B' => 'Wrong'], 'correct_option' => 'A', 'explanation' => 'Because.', 'is_published' => true,
        ]);
    }
    $this->student = User::factory()->create(['name' => 'Private Person', 'email' => 'private@example.test']);
    $this->actingAs($this->student)->withSession(['exam_id' => $this->exam->id]);
});

function aiSession(string $mode = PracticeSession::MODE_PRACTICE): PracticeSession
{
    $route = $mode === PracticeSession::MODE_MOCK ? '/questions/mocks' : '/questions/sessions';
    test()->post($route, ['count' => 2, 'minutes' => 10]);

    return PracticeSession::latest('id')->firstOrFail();
}

it('answers a question about an answered MCQ, strips HTML from the answer and caches it', function () {
    $session = aiSession();
    $this->post('/questions/sessions/'.$session->id.'/answer/1', ['option' => 'B']);
    $url = '/questions/sessions/'.$session->id.'/ask/1';

    $first = $this->postJson($url, ['question' => 'Explain this question'])->assertOk();
    expect($first->json('html'))->toContain('<strong>Key clue:</strong>')->not->toContain('<script>')
        ->and($first->json('remaining'))->toBe(2);
    $this->postJson($url, ['question' => '  explain   THIS question '])->assertOk();

    expect($this->fake->prompts)->toHaveCount(1)
        ->and($this->fake->prompts[0])->toContain('Correct answer: A')->toContain('The student chose: B')->toContain("Editor's explanation")
        ->not->toContain('private@example.test')->not->toContain('Private Person');
});

it('never gives Claude the answer key before the student answers', function () {
    $session = aiSession();
    $this->postJson('/questions/sessions/'.$session->id.'/ask/1', ['question' => 'What is the answer?'])->assertOk();

    expect($this->fake->prompts[0])->toContain('Stem')->toContain('A. Right')
        ->not->toContain('Correct answer')->not->toContain('Because.')
        ->toContain('not given the answer key');
});

it('refuses AI help during an open mock and allows it once the mock is closed', function () {
    $mock = aiSession(PracticeSession::MODE_MOCK);
    $this->post('/questions/mocks/'.$mock->id.'/answer/1', ['option' => 'A']);
    $this->postJson('/questions/sessions/'.$mock->id.'/ask/1', ['question' => 'What is the answer?'])->assertForbidden();
    $this->post('/questions/mocks/'.$mock->id.'/finish');
    $this->postJson('/questions/sessions/'.$mock->id.'/ask/1', ['question' => 'Explain this question'])->assertOk();

    $session = aiSession();
    $this->actingAs(User::factory()->create())->postJson('/questions/sessions/'.$session->id.'/ask/1', ['question' => 'Explain'])->assertForbidden();
    expect($this->fake->prompts)->toHaveCount(1);
});

it('validates the question', function () {
    $session = aiSession();
    $this->postJson('/questions/sessions/'.$session->id.'/ask/1', ['question' => 'x'])->assertUnprocessable();
    $this->postJson('/questions/sessions/'.$session->id.'/ask/1', ['question' => str_repeat('a', 401)])->assertUnprocessable();
});

it('writes study plan advice from subjects and scores only', function () {
    $this->get('/study-plan')->assertInertia(fn (Assert $page) => $page->where('ai.enabled', true));
    $this->postJson('/study-plan/ai')->assertOk()->assertJsonStructure(['html', 'remaining']);

    expect($this->fake->prompts[0])->toContain('Subjects not practised yet')->toContain('Renal')
        ->not->toContain('private@example.test')->not->toContain('Private Person');
});

it('stops at the daily limit and shows a friendly error', function () {
    $session = aiSession();
    $this->post('/questions/sessions/'.$session->id.'/answer/1', ['option' => 'A']);
    $this->post('/questions/sessions/'.$session->id.'/answer/2', ['option' => 'A']);
    $this->postJson('/questions/sessions/'.$session->id.'/ask/1', ['question' => 'Explain this question'])->assertOk();
    $this->postJson('/questions/sessions/'.$session->id.'/ask/2', ['question' => 'Explain this question'])->assertOk();
    $this->postJson('/study-plan/ai')->assertOk();

    $this->travel(1)->minutes();
    Question::query()->update(['explanation' => 'Changed']);
    $third = aiSession();
    $this->post('/questions/sessions/'.$third->id.'/answer/1', ['option' => 'B']);
    $this->postJson('/questions/sessions/'.$third->id.'/ask/1', ['question' => 'Explain this question'])->assertStatus(422)->assertJsonPath('error', "You have used today's AI requests. They reset tomorrow.");
});

it('returns the tutor error message when Claude cannot answer', function () {
    $this->fake->failWith = 'The AI tutor could not answer this one. Please use the explanation above.';
    $this->postJson('/study-plan/ai')->assertStatus(422)->assertJsonPath('error', 'The AI tutor could not answer this one. Please use the explanation above.');
});

it('stays off without an API key', function () {
    config(['services.anthropic.key' => null]);
    $this->get('/study-plan')->assertInertia(fn (Assert $page) => $page->where('ai.enabled', false));
    $this->postJson('/study-plan/ai')->assertNotFound();
    expect($this->fake->prompts)->toBe([]);
});

it('asks Claude Opus 5.5 by default', function () {
    expect(config('services.anthropic.model'))->toBe('claude-opus-5-5');
});
