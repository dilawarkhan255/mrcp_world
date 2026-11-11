<?php

use App\Models\AtlasImage;
use App\Models\Category;
use App\Models\Exam;
use App\Models\Flashcard;
use App\Models\PracticeSession;
use App\Models\Question;
use App\Models\TextbookChapter;
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

it('answers the student own revision question with the exam date and study hours', function () {
    $examDate = now()->addDays(30)->toDateString();
    $this->postJson('/study-plan/ai', ['question' => 'How should I revise renal?', 'exam_date' => $examDate, 'hours' => 3])
        ->assertOk()->assertJsonStructure(['html', 'remaining']);

    expect($this->fake->prompts[0])->toContain('How should I revise renal?')
        ->toContain('Exam date: '.$examDate.' (30 days from today)')
        ->toContain('about 3 hours a day')
        ->toContain('Renal')
        ->not->toContain('private@example.test');
});

it('validates the study plan question, exam date and hours', function () {
    $this->postJson('/study-plan/ai', ['question' => 'x'])->assertUnprocessable();
    $this->postJson('/study-plan/ai', ['exam_date' => now()->subDay()->toDateString()])->assertUnprocessable();
    $this->postJson('/study-plan/ai', ['exam_date' => 'soon'])->assertUnprocessable();
    $this->postJson('/study-plan/ai', ['hours' => 30])->assertUnprocessable();
    expect($this->fake->prompts)->toBe([]);
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

it('answers a question about a published textbook chapter from the chapter text', function () {
    $chapter = TextbookChapter::create(['exam_id' => $this->exam->id, 'title' => 'Nephrotic syndrome', 'body' => 'Membranous nephropathy is the commonest cause in adults.', 'is_published' => true]);
    TextbookChapter::create(['exam_id' => $this->exam->id, 'title' => 'Draft chapter', 'body' => 'Secret draft', 'is_published' => false]);

    $this->postJson('/textbooks/'.$chapter->slug.'/ask', ['question' => 'Summarise this chapter'])->assertOk()->assertJsonStructure(['html', 'remaining']);
    expect($this->fake->prompts[0])->toContain('Nephrotic syndrome')->toContain('Membranous nephropathy')->toContain('Summarise this chapter');

    $this->postJson('/textbooks/draft-chapter/ask', ['question' => 'Summarise this chapter'])->assertNotFound();
    $this->postJson('/textbooks/'.$chapter->slug.'/ask', ['question' => 'x'])->assertUnprocessable();
});

it('keeps the back of a flashcard from Claude until the card is turned', function () {
    $card = Flashcard::create(['exam_id' => $this->exam->id, 'front' => 'First-line drug for absence seizures?', 'back' => 'Ethosuximide', 'is_published' => true]);

    $this->postJson('/flashcards/'.$card->id.'/ask', ['question' => 'Give me a hint', 'revealed' => false])->assertOk();
    expect($this->fake->prompts[0])->toContain('absence seizures')->not->toContain('Ethosuximide')->toContain('Do not give the answer away');

    $this->postJson('/flashcards/'.$card->id.'/ask', ['question' => 'Explain the answer', 'revealed' => true])->assertOk();
    expect($this->fake->prompts[1])->toContain('Ethosuximide');

    $other = Flashcard::create(['exam_id' => Exam::factory()->create()->id, 'front' => 'Other', 'back' => 'Other', 'is_published' => true]);
    $this->postJson('/flashcards/'.$other->id.'/ask', ['question' => 'Give me a hint'])->assertNotFound();
});

it('answers about a published atlas picture and refuses an unpublished one', function () {
    $image = AtlasImage::create(['title' => 'Erythema nodosum', 'diagnosis' => 'Erythema nodosum', 'description' => 'Tender red nodules on the shins.', 'image_path' => 'atlas/en.png', 'license' => 'cc-by', 'copyright_confirmed' => true, 'is_published' => true]);
    $hidden = AtlasImage::create(['title' => 'Hidden', 'image_path' => 'atlas/h.png', 'license' => 'cc-by', 'is_published' => false]);

    $this->postJson('/image-atlas/'.$image->id.'/ask', ['question' => 'What are the differentials?'])->assertOk();
    expect($this->fake->prompts[0])->toContain('Erythema nodosum')->toContain('Tender red nodules')->toContain('You cannot see the picture');

    $this->postJson('/image-atlas/'.$hidden->id.'/ask', ['question' => 'What are the differentials?'])->assertNotFound();
});

it('refuses AI help on the material pages without an API key', function () {
    config(['services.anthropic.key' => null]);
    $chapter = TextbookChapter::create(['exam_id' => $this->exam->id, 'title' => 'Off', 'body' => 'Text', 'is_published' => true]);
    $this->postJson('/textbooks/'.$chapter->slug.'/ask', ['question' => 'Summarise this chapter'])->assertNotFound();
    expect($this->fake->prompts)->toBe([]);
});
