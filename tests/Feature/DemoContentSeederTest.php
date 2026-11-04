<?php

use App\Models\Exam;
use App\Models\PracticeSession;
use App\Models\Question;
use App\Models\QuestionComment;
use App\Models\Subscription;
use App\Models\TextbookChapter;
use App\Models\User;
use App\Services\LeaderboardService;
use Database\Seeders\DemoContentSeeder;
use Database\Seeders\ExamSeeder;
use Illuminate\Support\Facades\DB;

it('fills the site with flagged sample content and demo activity', function () {
    $this->seed(DemoContentSeeder::class);

    $samples = Question::where('code', 'like', 'SAMPLE-%');
    expect((clone $samples)->count())->toBe(36)
        ->and((clone $samples)->where('needs_review', false)->count())->toBe(0)
        ->and((clone $samples)->where('is_sample', false)->count())->toBe(0)
        ->and((clone $samples)->where('is_demo', true)->count())->toBeGreaterThan(0)
        ->and((clone $samples)->whereNotNull('past_paper_id')->count())->toBe(9)
        ->and(TextbookChapter::where('is_published', true)->count())->toBe(3)
        ->and(User::where('email', 'like', 'demo.student%')->count())->toBe(10)
        ->and(Subscription::query()->current()->count())->toBeGreaterThanOrEqual(20)
        ->and(QuestionComment::count())->toBe(5)
        ->and(DB::table('question_stats')->count())->toBeGreaterThan(0);

    // Every citation is a NICE guideline with its URL, or a WHO title without an invented URL.
    foreach ((clone $samples)->get() as $question) {
        foreach ($question->references ?? [] as $reference) {
            expect($reference['source'])->toBeIn(['NICE', 'WHO']);
            if ($reference['source'] === 'NICE') {
                expect($reference['url'])->toStartWith('https://www.nice.org.uk/guidance/');
            } else {
                expect($reference['url'])->toBeNull();
            }
        }
    }

    $mrcp1 = Exam::where('slug', 'mrcp-part-1')->value('id');
    expect(app(LeaderboardService::class)->board($mrcp1, LeaderboardService::ALL_TIME, null)['qualified'])->toBe(10);
});

it('can run again without duplicating anything', function () {
    $this->seed(DemoContentSeeder::class);
    $counts = [Question::count(), PracticeSession::count(), Subscription::count(), QuestionComment::count(), User::count()];

    $this->seed(DemoContentSeeder::class);

    expect([Question::count(), PracticeSession::count(), Subscription::count(), QuestionComment::count(), User::count()])->toBe($counts);
});

it('keeps an admin edit of a sample question when it runs again', function () {
    $this->seed(DemoContentSeeder::class);
    $question = Question::where('code', 'SAMPLE-MP1-001')->firstOrFail();
    $question->update(['explanation' => 'Edited by an editor']);

    $this->seed(DemoContentSeeder::class);

    expect($question->refresh()->explanation)->toBe('Edited by an editor');
});

it('never runs in production', function () {
    app()->detectEnvironment(fn (): string => 'production');

    $this->artisan('db:seed', ['--class' => DemoContentSeeder::class, '--force' => true])
        ->expectsOutputToContain('only runs in the local and testing environments')
        ->assertSuccessful();

    expect(Question::count())->toBe(0)->and(User::count())->toBe(0);
});

it('uses fixed sample codes even when the exams have no tile code', function () {
    $this->seed(ExamSeeder::class);
    Exam::query()->update(['code' => null]);

    $this->seed(DemoContentSeeder::class);

    expect(Question::where('code', 'SAMPLE-MP1-001')->exists())->toBeTrue()
        ->and(Question::where('code', 'SAMPLE-MP2-001')->exists())->toBeTrue()
        ->and(Question::where('code', 'like', 'SAMPLE-EX-%')->exists())->toBeFalse()
        ->and(QuestionComment::count())->toBe(5);
});
