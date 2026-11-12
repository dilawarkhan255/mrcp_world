<?php

use App\Filament\Resources\FlashcardResource\Pages\ManageFlashcards;
use App\Models\Category;
use App\Models\Exam;
use App\Models\Flashcard;
use App\Models\Question;
use App\Models\TutorRun;
use App\Models\User;
use App\Services\KnowledgeTutorService;
use Database\Seeders\RoleSeeder;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Livewire\Livewire;

function fact(Exam $exam, ?Category $category, array $overrides = []): Flashcard
{
    $card = Flashcard::create(array_merge([
        'exam_id' => $exam->id, 'category_id' => $category?->id, 'front' => 'Front', 'back' => 'Back', 'is_published' => true,
    ], $overrides));
    // Difficulty is worked out by stats:refresh, not typed in, so it is not fillable.
    if (array_key_exists('difficulty', $overrides)) {
        $card->forceFill(['difficulty' => $overrides['difficulty']])->save();
    }

    return $card;
}

/** Writes a review row as if the student had answered the fact. */
function answered(User $user, Flashcard $card, array $state): void
{
    DB::table('flashcard_reviews')->insert(array_merge([
        'user_id' => $user->id, 'flashcard_id' => $card->id, 'ease' => 2.5, 'interval_days' => 1, 'repetitions' => 1, 'lapses' => 0,
        'answers' => 1, 'last_rating' => 'good', 'due_at' => now()->addDay(), 'reviewed_at' => now()->subHour(),
    ], $state));
}

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    $this->exam = Exam::factory()->create();
    $this->renal = Category::factory()->create(['exam_id' => $this->exam->id, 'name' => 'Renal medicine', 'slug' => 'renal']);
    $this->cardio = Category::factory()->create(['exam_id' => $this->exam->id, 'name' => 'Cardiology', 'slug' => 'cardiology']);
    $this->student = User::factory()->create();
    $this->actingAs($this->student)->withSession(['exam_id' => $this->exam->id]);
});

it('counts facts by subject, high-yield rating and difficulty', function () {
    fact($this->exam, $this->renal, ['high_yield' => 'A++', 'difficulty' => 80]);
    fact($this->exam, $this->renal, ['high_yield' => 'B', 'difficulty' => 10]);
    fact($this->exam, $this->cardio, ['high_yield' => 'A+']);
    fact($this->exam, null, ['high_yield' => 'C']);

    $count = fn (array $query) => $this->getJson('/tutor/count?'.http_build_query($query))->assertOk()->json('total');

    expect($count([]))->toBe(4)
        ->and($count(['categories' => [$this->renal->id]]))->toBe(2)
        ->and($count(['categories' => [0]]))->toBe(1)
        ->and($count(['rating' => 'A+']))->toBe(2)
        ->and($count(['rating' => 'A++']))->toBe(1)
        // Facts without a difficulty yet count as 50%.
        ->and($count(['difficulty' => [60, 100]]))->toBe(1)
        ->and($count(['difficulty' => [40, 60]]))->toBe(2);

    $this->getJson('/tutor/count?rating=Z')->assertJsonValidationErrors('rating');
    // A subject of another exam is ignored.
    $foreign = Category::factory()->create(['exam_id' => Exam::factory()->create()->id]);
    expect($count(['categories' => [$foreign->id]]))->toBe(4);
});

it('asks the most high-yield new facts first', function () {
    $c = fact($this->exam, $this->renal, ['high_yield' => 'C', 'front' => 'C fact']);
    $top = fact($this->exam, $this->renal, ['high_yield' => 'A++', 'front' => 'Top fact']);

    $this->post('/tutor/start', [])->assertRedirect();
    $run = TutorRun::latest('id')->firstOrFail();
    $this->get('/tutor/study/'.$run->id)->assertInertia(fn (Assert $page) => $page->where('card.id', $top->id)->where('card.high_yield', 'A++'));
    expect($c->id)->toBeInt();
});

it('picks only new, only due or only weak facts when asked', function () {
    $new = fact($this->exam, $this->renal, ['front' => 'New']);
    $due = fact($this->exam, $this->renal, ['front' => 'Due']);
    $later = fact($this->exam, $this->renal, ['front' => 'Later']);
    $weak = fact($this->exam, $this->renal, ['front' => 'Weak']);
    answered($this->student, $due, ['due_at' => now()->subHour()]);
    answered($this->student, $later, []);
    answered($this->student, $weak, ['lapses' => 2, 'repetitions' => 0, 'interval_days' => 0]);

    $ready = fn (string $mode) => $this->getJson('/tutor/count?mode='.$mode)->json('ready');
    expect($ready('new'))->toBe(1)->and($ready('due'))->toBe(1)->and($ready('weak'))->toBe(1)->and($ready('spaced'))->toBe(2);

    // Weak facts: each one once per session.
    $this->post('/tutor/start', ['mode' => 'weak']);
    $run = TutorRun::latest('id')->firstOrFail();
    $this->get('/tutor/study/'.$run->id)->assertInertia(fn (Assert $page) => $page->where('card.id', $weak->id)->where('card.status', 'weak'));
    $this->travel(1)->seconds();
    $this->post('/tutor/study/'.$run->id.'/'.$weak->id, ['rating' => 'good']);
    $this->get('/tutor/study/'.$run->id)->assertInertia(fn (Assert $page) => $page->where('card', null)->where('run.answered', 1));
    expect($new->id)->toBeInt();
});

it('shows progress: tested and recalled A++ to A facts, weak and mastered', function () {
    $a = fact($this->exam, $this->renal, ['high_yield' => 'A++']);
    $b = fact($this->exam, $this->renal, ['high_yield' => 'A']);
    fact($this->exam, $this->renal, ['high_yield' => 'A+']);
    fact($this->exam, $this->renal, ['high_yield' => 'C']);
    answered($this->student, $a, ['repetitions' => 4, 'interval_days' => 30, 'reviewed_at' => now()]);
    answered($this->student, $b, ['repetitions' => 0, 'lapses' => 1, 'due_at' => now()->subMinute(), 'reviewed_at' => now()->subDays(2)]);

    $this->get('/tutor')->assertInertia(fn (Assert $page) => $page
        ->where('overview.totals.top_total', 3)->where('overview.totals.top_seen', 2)->where('overview.totals.top_recalled', 1)
        ->where('overview.totals.mastered', 1)->where('overview.totals.weak', 1)->where('overview.totals.due', 1)
        ->where('overview.totals.today', 1)->where('overview.totals.new', 2));
});

it('lists answered facts by status in Review facts and resets one', function () {
    $mastered = fact($this->exam, $this->renal, ['front' => 'Mastered fact']);
    $weak = fact($this->exam, $this->cardio, ['front' => 'Weak fact']);
    fact($this->exam, $this->renal, ['front' => 'Never answered']);
    answered($this->student, $mastered, ['repetitions' => 3, 'interval_days' => 25]);
    answered($this->student, $weak, ['repetitions' => 0, 'lapses' => 1]);
    answered(User::factory()->create(), $mastered, []);

    $this->get('/tutor/facts')->assertOk()->assertInertia(fn (Assert $page) => $page->component('tutor/Facts')->where('facts.total', 2));
    $this->get('/tutor/facts?status=weak')->assertInertia(fn (Assert $page) => $page
        ->where('facts.total', 1)->where('facts.data.0.id', $weak->id)->where('facts.data.0.status', 'weak')->where('facts.data.0.subject', 'Cardiology'));
    $this->get('/tutor/facts?status=mastered')->assertInertia(fn (Assert $page) => $page->where('facts.total', 1)->where('facts.data.0.id', $mastered->id));
    $this->get('/tutor/facts?subject='.$this->renal->id)->assertInertia(fn (Assert $page) => $page->where('facts.total', 1));
    $this->get('/tutor/facts?search=Weak')->assertInertia(fn (Assert $page) => $page->where('facts.total', 1));

    $this->delete('/tutor/facts/'.$weak->id)->assertRedirect();
    expect(DB::table('flashcard_reviews')->where('user_id', $this->student->id)->where('flashcard_id', $weak->id)->exists())->toBeFalse()
        ->and(DB::table('flashcard_reviews')->count())->toBe(2);
});

it('keeps tutor sessions to their student and exam', function () {
    $card = fact($this->exam, $this->renal);
    $run = TutorRun::create(['user_id' => User::factory()->create()->id, 'exam_id' => $this->exam->id, 'label' => 'Theirs', 'filters' => []]);
    $this->get('/tutor/study/'.$run->id)->assertNotFound();
    $this->post('/tutor/study/'.$run->id.'/'.$card->id, ['rating' => 'good'])->assertNotFound();

    $mine = TutorRun::create(['user_id' => $this->student->id, 'exam_id' => $this->exam->id, 'label' => 'Mine', 'filters' => []]);
    $draft = fact($this->exam, $this->renal, ['is_published' => false]);
    $this->post('/tutor/study/'.$mine->id.'/'.$draft->id, ['rating' => 'good'])->assertNotFound();
    $this->post('/tutor/study/'.$mine->id.'/'.$card->id, ['rating' => 'perfect'])->assertSessionHasErrors('rating');
    // Last activity lists only the student's own sessions.
    $this->get('/tutor')->assertInertia(fn (Assert $page) => $page->has('runs', 1)->where('runs.0.label', 'Mine'));
});

it('works out each fact difficulty from all students answers', function () {
    $hard = fact($this->exam, $this->renal);
    $few = fact($this->exam, $this->renal);
    $updated = $hard->updated_at;
    answered($this->student, $hard, ['answers' => 4, 'lapses' => 3]);
    answered(User::factory()->create(), $hard, ['answers' => 4, 'lapses' => 1]);
    answered($this->student, $few, ['answers' => 2, 'lapses' => 2]);

    expect(app(KnowledgeTutorService::class)->refreshDifficulty())->toBe(1);
    expect($hard->fresh()->difficulty)->toBe(50)->and($hard->fresh()->updated_at->equalTo($updated))->toBeTrue()
        ->and($few->fresh()->difficulty)->toBeNull();
});

it('lets an editor make facts from the MCQs of a subject, rated by how often they come up', function () {
    $editor = User::factory()->create();
    $editor->assignRole('editor');
    $make = fn (string $code, array $extra = []) => Question::create(array_merge([
        'exam_id' => $this->exam->id, 'category_id' => $this->renal->id, 'code' => $code, 'body' => 'Stem '.$code,
        'options' => ['A' => 'One', 'B' => 'Two'], 'correct_option' => 'B', 'key_points' => 'Key point '.$code, 'is_published' => true,
    ], $extra));
    $repeated = $make('K-1', ['repeat_count' => 3]);
    $make('K-2');
    $make('K-3', ['key_points' => null]);
    $make('K-4', ['is_published' => false]);
    $make('K-5', ['category_id' => $this->cardio->id]);
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    $this->actingAs($editor);

    Livewire::test(ManageFlashcards::class)
        ->callAction('fromQuestions', ['exam_id' => $this->exam->id, 'category_id' => $this->renal->id, 'only_key_points' => true])
        ->assertNotified('2 facts made');
    Livewire::test(ManageFlashcards::class)
        ->callAction('fromQuestions', ['exam_id' => $this->exam->id, 'category_id' => $this->renal->id, 'only_key_points' => false])
        ->assertNotified('1 facts made, 2 MCQs already had one');

    expect(Flashcard::count())->toBe(3)
        ->and(Flashcard::where('question_id', $repeated->id)->value('high_yield'))->toBe('A++');

    $card = Flashcard::where('question_id', $repeated->id)->firstOrFail();
    Livewire::test(ManageFlashcards::class)->callTableBulkAction('setHighYield', [$card], ['high_yield' => 'B']);
    expect($card->fresh()->high_yield)->toBe('B');
});
