<?php

use App\Filament\Resources\FlashcardResource\Pages\ManageFlashcards;
use App\Filament\Resources\QuestionResource\Pages\ManageQuestions;
use App\Models\Category;
use App\Models\Exam;
use App\Models\Flashcard;
use App\Models\Question;
use App\Models\TutorConversation;
use App\Models\TutorMessage;
use App\Models\TutorRun;
use App\Models\User;
use App\Services\AiTutor;
use App\Services\AiTutorException;
use App\Services\FlashcardService;
use Database\Seeders\RoleSeeder;
use Filament\Facades\Filament;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Livewire\Livewire;

/** The tutor with Claude replaced: it records the conversation it was sent. */
class FakeKnowledgeTutor extends AiTutor
{
    /** @var list<list<array{role: string, content: string}>> */
    public array $conversations = [];

    public ?string $failWith = null;

    protected function sendMessages(array $messages, string $system): string
    {
        $this->conversations[] = $messages;
        if ($this->failWith !== null) {
            throw new AiTutorException($this->failWith);
        }

        return "**Hyponatraemia** — start with serum osmolality.\n\n<script>alert(1)</script>";
    }
}

function card(Exam $exam, ?Category $category, array $overrides = []): Flashcard
{
    return Flashcard::create(array_merge([
        'exam_id' => $exam->id, 'category_id' => $category?->id, 'front' => 'Front text', 'back' => 'Back text', 'is_published' => true,
    ], $overrides));
}

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    $this->exam = Exam::factory()->create();
    $this->category = Category::factory()->create(['exam_id' => $this->exam->id, 'name' => 'Renal medicine', 'slug' => 'renal']);
    $this->student = User::factory()->create();
    $this->actingAs($this->student)->withSession(['exam_id' => $this->exam->id]);
});

it('shows the Knowledge Tutor with progress by subject, only published facts', function () {
    card($this->exam, $this->category, ['front' => 'One']);
    card($this->exam, $this->category, ['front' => 'Two']);
    card($this->exam, null, ['front' => 'General card']);
    card($this->exam, $this->category, ['front' => 'Draft', 'is_published' => false]);

    $this->get('/tutor')->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('tutor/Index')
        ->has('overview.subjects', 2)
        ->where('overview.subjects.0.name', 'Renal medicine')->where('overview.subjects.0.total', 2)->where('overview.subjects.0.new', 2)
        ->where('overview.subjects.1.name', 'General')->where('overview.subjects.1.id', 0)
        ->where('overview.totals.total', 3)->where('overview.totals.top_total', 3)
        ->where('count.total', 3)->where('count.ready', 3));
    // The old Flashcards pages lead to the tutor.
    $this->get('/flashcards')->assertRedirect('/tutor');
});

it('runs a tutor session: new fact first, rating schedules it, then the next fact', function () {
    $first = card($this->exam, $this->category, ['front' => '**First**', 'sort_order' => 1]);
    $second = card($this->exam, $this->category, ['front' => 'Second', 'sort_order' => 2]);

    $this->post('/tutor/start', ['categories' => [$this->category->id]])->assertRedirect();
    $run = TutorRun::firstOrFail();
    expect($run->label)->toContain('Renal medicine');

    $this->get('/tutor/study/'.$run->id)->assertInertia(fn (Assert $page) => $page
        ->component('tutor/Study')
        ->where('card.id', $first->id)->where('card.status', 'new')->where('card.subject', 'Renal medicine')
        ->where('card.front_html', fn (string $html) => str_contains($html, '<strong>First</strong>'))
        ->where('card.previews.again', '10 min')->where('card.previews.good', '1 day')->where('card.previews.easy', '2 days')
        ->where('run.remaining', 2));

    $this->post('/tutor/study/'.$run->id.'/'.$first->id, ['rating' => 'good'])->assertRedirect('/tutor/study/'.$run->id);
    $review = DB::table('flashcard_reviews')->where('user_id', $this->student->id)->where('flashcard_id', $first->id)->first();
    expect((int) $review->interval_days)->toBe(1)->and((int) $review->repetitions)->toBe(1)
        ->and((int) $review->answers)->toBe(1)->and($review->last_rating)->toBe('good')
        ->and($run->fresh()->answered)->toBe(1);

    $this->get('/tutor/study/'.$run->id)->assertInertia(fn (Assert $page) => $page->where('card.id', $second->id)->where('run.remaining', 1));

    // A day later the first fact is due again and comes before new ones.
    $this->travel(2)->days();
    $this->get('/tutor/study/'.$run->id)->assertInertia(fn (Assert $page) => $page->where('card.id', $first->id)->where('card.status', 'learning'));
});

it('spaces the reviews out like SM-2', function () {
    $now = Carbon::parse('2026-11-10 09:00');
    $state = null;
    $intervals = [];
    foreach (['good', 'good', 'good'] as $rating) {
        $state = FlashcardService::schedule($state, $rating, $now);
        $intervals[] = $state['interval_days'];
    }
    expect($intervals)->toBe([1, 3, 8]);

    $lapsed = FlashcardService::schedule($state, 'again', $now);
    expect($lapsed['interval_days'])->toBe(0)->and($lapsed['repetitions'])->toBe(0)->and($lapsed['lapses'])->toBe(1)
        ->and($lapsed['due_at']->equalTo($now->copy()->addMinutes(10)))->toBeTrue()
        ->and($lapsed['ease'])->toBeLessThan($state['ease']);
});

it('refuses a rating for an unpublished card or one of another exam', function () {
    $draft = card($this->exam, $this->category, ['is_published' => false]);
    $other = card(Exam::factory()->create(), null);
    $this->post('/flashcards/'.$draft->id.'/review', ['rating' => 'good'])->assertNotFound();
    $this->post('/flashcards/'.$other->id.'/review', ['rating' => 'good'])->assertNotFound();
    $this->post('/flashcards/'.card($this->exam, null)->id.'/review', ['rating' => 'perfect'])->assertSessionHasErrors('rating');
});

it('lets an editor manage flashcards and make them from MCQs', function () {
    $editor = User::factory()->create();
    $editor->assignRole('editor');
    $question = Question::create([
        'exam_id' => $this->exam->id, 'category_id' => $this->category->id, 'code' => 'FC-1', 'body' => 'Which drug?',
        'options' => ['A' => 'Furosemide', 'B' => 'Amiloride'], 'correct_option' => 'B', 'explanation' => 'Because of potassium.',
        'key_points' => 'Amiloride spares potassium.', 'is_published' => true,
    ]);
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    $this->actingAs($editor);

    $this->get('/admin/flashcards')->assertOk()->assertSee('mwYajraTable', false);
    Livewire::test(ManageQuestions::class)->callTableBulkAction('makeFlashcards', [$question])->assertNotified('1 flashcards made');
    Livewire::test(ManageQuestions::class)->callTableBulkAction('makeFlashcards', [$question])->assertNotified('0 flashcards made, 1 already had one');

    $made = Flashcard::where('question_id', $question->id)->firstOrFail();
    expect($made->front)->toBe('Which drug?')->and($made->back)->toContain('**Answer: B. Amiloride**')->toContain('Amiloride spares potassium.')
        ->and($made->category_id)->toBe($this->category->id)->and($made->is_published)->toBeTrue();

    Livewire::test(ManageFlashcards::class)->callTableBulkAction('unpublish', [$made]);
    expect($made->fresh()->is_published)->toBeFalse();
    Livewire::test(ManageFlashcards::class)->callTableBulkAction('deleteSelected', [$made]);
    expect(Flashcard::count())->toBe(0);

    $student = User::factory()->create();
    $this->actingAs($student)->get('/admin/flashcards')->assertForbidden();
});

it('saves AI tutor conversations and sends the earlier turns from the database', function () {
    config(['services.anthropic.key' => 'test-key', 'services.anthropic.daily_limit' => 3]);
    $fake = new FakeKnowledgeTutor;
    $this->app->instance(AiTutor::class, $fake);

    $this->get('/tutor/ai')->assertOk()->assertInertia(fn (Assert $page) => $page->component('tutor/Ai')->where('enabled', true)->where('remaining', 3)->has('conversations', 0));

    $first = $this->postJson('/tutor/ai/send', ['message' => 'Explain hyponatraemia'])->assertOk()->assertJsonPath('remaining', 2)
        ->assertJsonPath('html', fn (string $html) => str_contains($html, '<strong>Hyponatraemia</strong>') && ! str_contains($html, '<script>'));
    $conversation = TutorConversation::findOrFail($first->json('conversation.id'));
    expect($conversation->title)->toBe('Explain hyponatraemia')->and($conversation->messages()->count())->toBe(2);

    $this->postJson('/tutor/ai/send', ['message' => 'And the treatment?', 'conversation_id' => $conversation->id])->assertOk();
    expect($fake->conversations[1][0])->toBe(['role' => 'user', 'content' => 'Explain hyponatraemia'])
        ->and($fake->conversations[1][1]['role'])->toBe('assistant')
        ->and($fake->conversations[1][2])->toBe(['role' => 'user', 'content' => 'And the treatment?'])
        ->and($conversation->messages()->count())->toBe(4);

    $this->get('/tutor/ai/'.$conversation->id)->assertOk()->assertInertia(fn (Assert $page) => $page
        ->has('conversations', 1)->where('conversation.id', $conversation->id)->has('conversation.messages', 4)
        ->where('conversation.messages.1.html', fn (string $html) => str_contains($html, '<strong>Hyponatraemia</strong>')));

    $this->patch('/tutor/ai/'.$conversation->id, ['title' => 'Sodium'])->assertRedirect();
    expect($conversation->fresh()->title)->toBe('Sodium');

    // At the daily limit nothing is saved.
    $this->postJson('/tutor/ai/send', ['message' => 'Third question'])->assertOk();
    $this->postJson('/tutor/ai/send', ['message' => 'Fourth question'])->assertStatus(422)
        ->assertJsonPath('error', fn (string $error) => str_contains($error, 'AI requests'));
    expect(TutorConversation::count())->toBe(2);

    $this->postJson('/tutor/ai/send', ['message' => 'x'])->assertJsonValidationErrors('message');
    $this->delete('/tutor/ai/'.$conversation->id)->assertRedirect('/tutor/ai');
    expect(TutorConversation::find($conversation->id))->toBeNull()
        ->and(TutorMessage::where('tutor_conversation_id', $conversation->id)->count())->toBe(0);
});

it('keeps each student AI conversations private', function () {
    config(['services.anthropic.key' => 'test-key']);
    $this->app->instance(AiTutor::class, new FakeKnowledgeTutor);
    $other = User::factory()->create();
    $theirs = TutorConversation::create(['user_id' => $other->id, 'exam_id' => $this->exam->id, 'title' => 'Private']);
    $theirs->messages()->create(['role' => 'user', 'content' => 'Secret']);

    $this->get('/tutor/ai/'.$theirs->id)->assertNotFound();
    $this->postJson('/tutor/ai/send', ['message' => 'Hello there', 'conversation_id' => $theirs->id])->assertNotFound();
    $this->patch('/tutor/ai/'.$theirs->id, ['title' => 'Mine now'])->assertNotFound();
    $this->delete('/tutor/ai/'.$theirs->id)->assertNotFound();
    $this->get('/tutor/ai')->assertInertia(fn (Assert $page) => $page->has('conversations', 0));
    expect($theirs->fresh()->title)->toBe('Private');
});

it('shows the AI tutor switched off without an API key and refuses the chat', function () {
    config(['services.anthropic.key' => null]);
    $this->get('/tutor/ai')->assertOk()->assertInertia(fn (Assert $page) => $page->where('enabled', false));
    $this->postJson('/tutor/ai/send', ['message' => 'Hello there'])->assertNotFound();
});

it('lists the Knowledge Tutor, Review Facts and the AI Tutor in the student sidebar', function () {
    $this->get('/dashboard')->assertInertia(fn (Assert $page) => $page
        ->where('navigation', function ($items): bool {
            $tutor = collect($items)->firstWhere('title', 'Knowledge Tutor');

            return $tutor !== null && collect($tutor['children'])->pluck('href')->all() === ['/tutor', '/tutor/facts', '/tutor/ai'];
        }));
});
