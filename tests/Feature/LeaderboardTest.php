<?php

use App\Filament\Resources\UserResource\Pages\ManageUsers;
use App\Models\Category;
use App\Models\Exam;
use App\Models\PracticeItem;
use App\Models\PracticeSession;
use App\Models\Qbank;
use App\Models\Question;
use App\Models\User;
use App\Services\LeaderboardService;
use App\Services\PracticeService;
use Carbon\CarbonImmutable;
use Database\Seeders\RoleSeeder;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Cache;
use Inertia\Testing\AssertableInertia as Assert;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    $this->exam = Exam::factory()->create();
    $category = Category::factory()->create(['exam_id' => $this->exam->id]);
    foreach (range(1, 40) as $index) {
        Question::create([
            'exam_id' => $this->exam->id, 'category_id' => $category->id, 'code' => 'LB-'.$index, 'body' => 'Stem '.$index,
            'options' => ['A' => 'Right', 'B' => 'Wrong'], 'correct_option' => 'A', 'is_published' => true,
        ]);
    }
    Cache::flush();
});

/** A student who opted in under a display name. */
function ranked(string $name, bool $optIn = true): User
{
    return User::factory()->create(['leaderboard_opt_in' => $optIn, 'leaderboard_name' => $optIn ? $name : null]);
}

/** Answer $total questions in one session, the first $correct of them correctly. */
function answer(User $user, Exam $exam, int $total, int $correct, string $mode = PracticeSession::MODE_PRACTICE): PracticeSession
{
    $practice = app(PracticeService::class);
    $session = $practice->start($user, $exam, $total, null, null, $mode, $mode === PracticeSession::MODE_MOCK ? 3600 : null);
    foreach (range(1, $total) as $position) {
        $practice->answer($session, $position, $position <= $correct ? 'A' : 'B');
    }

    return $session->refresh();
}

function board(Exam $exam, ?User $me = null, string $period = LeaderboardService::ALL_TIME): array
{
    return app(LeaderboardService::class)->board($exam->id, $period, $me?->id);
}

it('lists only students who opted in, by display name', function () {
    $shown = ranked('Dr Visible');
    $hidden = ranked('', optIn: false);
    answer($shown, $this->exam, 25, 20);
    answer($hidden, $this->exam, 30, 30);

    $result = board($this->exam);
    expect(array_column($result['entries'], 'name'))->toBe(['Dr Visible'])->and($result['qualified'])->toBe(1);

    $this->actingAs($hidden)->withSession(['exam_id' => $this->exam->id])->get('/leaderboard?period=all')
        ->assertInertia(fn (Assert $page) => $page->component('Leaderboard')->where('optedIn', false)->where('me', null)->has('entries', 1))
        ->assertDontSee($shown->email)->assertDontSee((string) $shown->name);
});

it('ranks by correct first answers, then accuracy, and shares a rank on a tie', function () {
    answer($a = ranked('Alpha'), $this->exam, 30, 22);
    answer($b = ranked('Bravo'), $this->exam, 24, 22);
    answer($c = ranked('Charlie'), $this->exam, 40, 24);
    answer($d = ranked('Delta'), $this->exam, 24, 22);

    $entries = board($this->exam)['entries'];
    expect(array_column($entries, 'name'))->toBe(['Charlie', 'Bravo', 'Delta', 'Alpha'])
        ->and(array_column($entries, 'rank'))->toBe([1, 2, 2, 4])
        ->and($entries[1])->toMatchArray(['correct' => 22, 'answered' => 24, 'accuracy' => 92]);
});

it('needs at least 20 first answers to qualify', function () {
    answer($under = ranked('Nineteen'), $this->exam, 19, 19);
    answer(ranked('Twenty'), $this->exam, 20, 5);

    $result = board($this->exam, $under);
    expect(array_column($result['entries'], 'name'))->toBe(['Twenty'])->and($result['me'])->toBeNull();
});

it('counts only the first answer to a question, so repeats cannot raise a rank', function () {
    $student = ranked('Repeater');
    answer($student, $this->exam, 20, 10);
    // Practise the same 20 questions again, all correct this time.
    $ids = PracticeItem::whereIn('practice_session_id', PracticeSession::where('user_id', $student->id)->pluck('id'))->pluck('question_id')->all();
    $again = app(PracticeService::class)->startFromQuestions($student, $this->exam, $ids, PracticeSession::MODE_PRACTICE);
    foreach (range(1, 20) as $position) {
        app(PracticeService::class)->answer($again, $position, 'A');
    }

    expect(board($this->exam)['entries'][0])->toMatchArray(['correct' => 10, 'answered' => 20]);
});

it('ignores review sessions and open mocks but counts closed mocks', function () {
    $student = ranked('Mocker');
    answer($student, $this->exam, 25, 25, PracticeSession::MODE_REVIEW);
    expect(board($this->exam)['entries'])->toBe([]);

    $mock = answer($student, $this->exam, 25, 20, PracticeSession::MODE_MOCK);
    expect($mock->completed_at)->not->toBeNull(); // Every question answered closes the mock.
    Cache::flush();
    expect(board($this->exam)['entries'][0])->toMatchArray(['correct' => 20, 'answered' => 25]);

    $open = app(PracticeService::class)->start(ranked('Open mock'), $this->exam, 30, null, null, PracticeSession::MODE_MOCK, 3600);
    foreach (range(1, 25) as $position) {
        app(PracticeService::class)->answer($open, $position, 'A');
    }
    Cache::flush();
    expect(array_column(board($this->exam)['entries'], 'name'))->toBe(['Mocker']);
});

it('starts the week on Monday 00:00 UK time in winter and in summer', function () {
    $student = ranked('Weekly');
    answer($student, $this->exam, 30, 30);
    $items = PracticeItem::whereIn('practice_session_id', PracticeSession::where('user_id', $student->id)->pluck('id'))->orderBy('id')->get();

    // Winter (GMT): Monday 9 November 2026 00:00 London is 00:00 UTC.
    $this->travelTo(CarbonImmutable::parse('2026-11-11 12:00', 'UTC'));
    expect(app(LeaderboardService::class)->weekStart()->toDateTimeString())->toBe('2026-11-09 00:00:00');
    $items->take(10)->each(fn (PracticeItem $item) => $item->update(['answered_at' => '2026-11-08 23:59:00']));
    $items->slice(10)->each(fn (PracticeItem $item) => $item->update(['answered_at' => '2026-11-09 00:00:00']));
    Cache::flush();
    expect(board($this->exam, $student, LeaderboardService::WEEKLY)['me'])->toMatchArray(['answered' => 20])
        ->and(board($this->exam, $student, LeaderboardService::ALL_TIME)['me'])->toMatchArray(['answered' => 30]);

    // Summer (BST): Monday 6 July 2026 00:00 London is Sunday 5 July 23:00 UTC.
    $this->travelTo(CarbonImmutable::parse('2026-07-08 12:00', 'UTC'));
    expect(app(LeaderboardService::class)->weekStart()->toDateTimeString())->toBe('2026-07-05 23:00:00');
    $items->take(10)->each(fn (PracticeItem $item) => $item->update(['answered_at' => '2026-07-05 22:59:00']));
    $items->slice(10)->each(fn (PracticeItem $item) => $item->update(['answered_at' => '2026-07-05 23:30:00']));
    Cache::flush();
    expect(board($this->exam, $student, LeaderboardService::WEEKLY)['me'])->toMatchArray(['answered' => 20]);
});

it('shows the top entries plus the student\'s own rank, and caches the board', function () {
    config(['student.leaderboard.size' => 2]);
    answer(ranked('First'), $this->exam, 30, 30);
    answer(ranked('Second'), $this->exam, 30, 28);
    answer($me = ranked('Third'), $this->exam, 30, 21);

    $this->actingAs($me)->withSession(['exam_id' => $this->exam->id])->get('/leaderboard?period=all')
        ->assertInertia(fn (Assert $page) => $page
            ->has('entries', 2)->where('entries.0.name', 'First')->where('entries.0.is_me', false)
            ->where('me.rank', 3)->where('me.is_me', true)->where('qualified', 3)
            ->missing('entries.0.user_id'));

    answer(ranked('Late'), $this->exam, 30, 30);
    expect(board($this->exam)['qualified'])->toBe(3); // Cached for ten minutes.
    $this->travel(11)->minutes();
    expect(board($this->exam)['qualified'])->toBe(4);
});

it('saves the opt-in with a display name and refuses an email address', function () {
    $student = User::factory()->create();
    $this->actingAs($student);
    $this->patch('/settings/leaderboard', ['leaderboard_opt_in' => true, 'leaderboard_name' => ''])->assertSessionHasErrors('leaderboard_name');
    $this->patch('/settings/leaderboard', ['leaderboard_opt_in' => true, 'leaderboard_name' => 'me@example.com'])->assertSessionHasErrors('leaderboard_name');
    $this->patch('/settings/leaderboard', ['leaderboard_opt_in' => true, 'leaderboard_name' => '  Dr   Sara  '])->assertSessionHasNoErrors();
    expect($student->refresh()->leaderboard_opt_in)->toBeTrue()->and($student->leaderboard_name)->toBe('Dr Sara');

    $this->patch('/settings/leaderboard', ['leaderboard_opt_in' => false])->assertSessionHasNoErrors();
    expect($student->refresh()->leaderboard_opt_in)->toBeFalse();
});

it('lets an admin take a student off the leaderboard', function () {
    $student = ranked('Bad name');
    $admin = User::factory()->create();
    $admin->assignRole('admin');
    $this->actingAs($admin);
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    Livewire::test(ManageUsers::class)->callTableAction('removeFromLeaderboard', $student)->assertHasNoTableActionErrors();
    expect($student->refresh()->leaderboard_opt_in)->toBeFalse()->and($student->leaderboard_name)->toBeNull();
});

it('needs a subscription when the exam belongs to a Qbank', function () {
    $exam = Exam::factory()->create(['qbank_id' => Qbank::create(['name' => 'Q', 'slug' => 'q'])->id]);
    $this->actingAs(User::factory()->create())->withSession(['exam_id' => $exam->id]);
    $this->get('/leaderboard')->assertRedirect('/subscription');
    $this->get('/study-plan')->assertRedirect('/subscription');
});
