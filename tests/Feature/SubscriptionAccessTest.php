<?php

use App\Filament\Resources\SubscriptionResource\Pages\ManageSubscriptions;
use App\Filament\Resources\TextbookChapterResource\Pages\ManageTextbookChapters;
use App\Models\Category;
use App\Models\Exam;
use App\Models\Plan;
use App\Models\PracticeSession;
use App\Models\Qbank;
use App\Models\Question;
use App\Models\Subscription;
use App\Models\TextbookChapter;
use App\Models\User;
use App\Services\SubscriptionService;
use Database\Seeders\ExamSeeder;
use Database\Seeders\PlanSeeder;
use Database\Seeders\RoleSeeder;
use Filament\Facades\Filament;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed([ExamSeeder::class, PlanSeeder::class, RoleSeeder::class]);
    $this->mrcp1 = Exam::where('slug', 'mrcp-part-1')->firstOrFail();
    $this->qbank1 = Qbank::where('slug', 'qbank-1')->firstOrFail();
    $this->qbank2 = Qbank::where('slug', 'qbank-2')->firstOrFail();
    // The 3-month plan of Qbank 1 (£8).
    $this->plan1 = $this->qbank1->plans()->where('duration_days', 90)->firstOrFail();
    $this->category = Category::create(['exam_id' => $this->mrcp1->id, 'name' => 'Cardiology', 'slug' => 'cardiology']);
    foreach (['DEMO-1' => true, 'DEMO-2' => true, 'FULL-1' => false, 'FULL-2' => false, 'FULL-3' => false] as $code => $demo) {
        Question::create([
            'exam_id' => $this->mrcp1->id, 'category_id' => $this->category->id, 'code' => $code, 'body' => 'Stem '.$code,
            'options' => ['A' => 'One', 'B' => 'Two'], 'correct_option' => 'A', 'is_published' => true, 'is_demo' => $demo,
        ]);
    }
    $this->student = User::factory()->create();
    $this->student->assignRole('student');
    $this->admin = User::factory()->create();
    $this->admin->assignRole('admin');
});

it('sells every Qbank for 3 months at £8 and 5 months at £12', function () {
    foreach ([$this->qbank1, $this->qbank2] as $qbank) {
        $plans = $qbank->plans()->where('is_active', true)->orderBy('sort_order')->get();
        expect($plans->map(fn (Plan $plan): array => [$plan->durationLabel(), $plan->priceLabel(), $plan->currency])->all())
            ->toBe([['3 months', '£8', 'GBP'], ['5 months', '£12', 'GBP']]);
    }
    $this->get('/')->assertInertia(fn (Assert $page) => $page
        ->has('plans', 2)
        ->where('plans.0.qbank', 'MRCPWorld 1 Qbank')
        ->where('plans.0.exams', ['MRCP 1'])
        ->where('plans.0.plans.0.duration', '3 months')->where('plans.0.plans.0.price', '£8')
        ->where('plans.0.plans.1.duration', '5 months')->where('plans.0.plans.1.price', '£12')
        ->where('plans.1.qbank', 'MRCPWorld 2 Qbank')
        ->where('plans.1.exams', ['MRCP 2']));
    $this->actingAs($this->student)->get('/subscription')->assertInertia(fn (Assert $page) => $page
        ->where('qbanks.0.plans.0.duration', '3 months')->where('qbanks.0.plans.1.duration', '5 months')
        ->where('qbanks.0.plans.1.price', '£12'));
});

it('gives 5 months of access for the £12 plan', function () {
    $five = $this->qbank2->plans()->where('duration_days', 150)->firstOrFail();
    $this->actingAs($this->student)->post('/subscription', ['plan_id' => $five->id, 'payment_reference' => 'TXN-5'])->assertSessionHas('status');
    $request = Subscription::firstOrFail();
    expect($request->amount)->toBe('12.00');
    $active = app(SubscriptionService::class)->activate($request, $this->admin);
    expect($active->ends_at->diffInDays($active->starts_at, true))->toEqualWithDelta(150, 0.01);
});

it('lets a student who paid for the other Qbank practise only the demo questions of this one', function () {
    grantAccess($this->student, $this->qbank2);
    $this->actingAs($this->student);
    $this->get('/questions/bank')->assertInertia(fn (Assert $page) => $page
        ->where('demoOnly', true)->where('availableQuestions', 2)->where('categories.data.0.questions_count', 2)
        ->where('access.has_access', false)->where('access.qbank', 'MRCPWorld 1 Qbank'));

    $this->post('/questions/sessions', ['count' => 5])->assertRedirect();
    $session = PracticeSession::where('user_id', $this->student->id)->firstOrFail();
    expect($session->total_questions)->toBe(2)
        ->and($session->items()->join('questions', 'questions.id', '=', 'practice_items.question_id')->where('questions.is_demo', false)->exists())->toBeFalse();
    $this->get('/questions/sessions/'.$session->id)->assertOk();
    $this->post('/questions/sessions', ['count' => 5, 'source' => 'past'])->assertRedirect('/subscription');
});

it('opens the full bank once an admin activates a request, and locks it again when it ends', function () {
    $this->actingAs($this->student)->post('/subscription', ['plan_id' => $this->plan1->id, 'payment_reference' => 'TXN-123', 'note' => 'Paid by bank'])
        ->assertSessionHas('status');
    $request = Subscription::firstOrFail();
    expect($request->status)->toBe(Subscription::STATUS_PENDING)->and($request->amount)->toBe('8.00')->and($request->currency)->toBe('GBP');
    $this->get('/questions/mocks')->assertRedirect('/subscription');
    $this->post('/subscription', ['plan_id' => $this->plan1->id, 'payment_reference' => 'TXN-124'])->assertSessionHasErrors('plan_id');

    $active = app(SubscriptionService::class)->activate($request, $this->admin);
    expect($active->ends_at->diffInDays($active->starts_at, true))->toEqualWithDelta(90, 0.01);

    $this->get('/questions/bank')->assertInertia(fn (Assert $page) => $page->where('demoOnly', false)->where('availableQuestions', 5));
    $this->get('/questions/mocks')->assertOk();
    $this->post('/questions/sessions', ['count' => 5]);
    $full = PracticeSession::where('user_id', $this->student->id)->latest('id')->firstOrFail();
    expect($full->total_questions)->toBe(5);

    $this->travel(91)->days();
    $this->get('/questions/mocks')->assertRedirect('/subscription');
    $this->get('/questions/sessions/'.$full->id)->assertRedirect('/subscription');
    $this->post('/questions/sessions/'.$full->id.'/answer/1', ['option' => 'A'])->assertRedirect('/subscription');
    expect($request->refresh()->stateLabel())->toBe('Expired');
});

it('extends a running subscription instead of losing the remaining days', function () {
    $running = grantAccess($this->student, $this->qbank1, 30);
    $renewal = Subscription::create(['user_id' => $this->student->id, 'qbank_id' => $this->qbank1->id, 'plan_id' => $this->plan1->id, 'status' => Subscription::STATUS_PENDING]);

    $renewed = app(SubscriptionService::class)->activate($renewal, $this->admin);

    expect($renewed->starts_at->equalTo($running->ends_at))->toBeTrue()
        ->and($renewed->ends_at->equalTo($running->ends_at->copy()->addDays(90)))->toBeTrue();
});

it('keeps each Qbank separate and maps a linked exam to its Qbank subscription', function () {
    grantAccess($this->student, $this->qbank2);
    $service = app(SubscriptionService::class);
    expect($service->hasAccess($this->student, $this->qbank1->id))->toBeFalse()
        ->and($service->hasAccess($this->student, $this->qbank2->id))->toBeTrue();

    $imm = linkedExam($this->mrcp1);
    $other = User::factory()->create();
    grantAccess($other, $this->qbank1);
    $this->actingAs($other)->post('/exam', ['exam_id' => $imm->id]);
    $this->get('/questions/mocks')->assertOk();
    $this->get('/questions/bank')->assertInertia(fn (Assert $page) => $page->where('availableQuestions', 5)->where('exam.name', 'Linked exam'));
});

it('ignores cancelled and future subscriptions, and always lets content staff in', function () {
    Subscription::create(['user_id' => $this->student->id, 'qbank_id' => $this->qbank1->id, 'status' => Subscription::STATUS_CANCELLED, 'starts_at' => now()->subDay(), 'ends_at' => now()->addMonth()]);
    Subscription::create(['user_id' => $this->student->id, 'qbank_id' => $this->qbank1->id, 'status' => Subscription::STATUS_ACTIVE, 'starts_at' => now()->addDay(), 'ends_at' => now()->addMonth()]);
    expect(app(SubscriptionService::class)->hasAccess($this->student, $this->qbank1->id))->toBeFalse();

    $editor = User::factory()->create();
    $editor->assignRole('editor');
    $this->actingAs($editor)->get('/questions/mocks')->assertOk();
});

it('rejects a request for a plan of another Qbank or a plan that is off sale', function () {
    $this->plan1->update(['is_active' => false]);
    $this->actingAs($this->student)->post('/subscription', ['plan_id' => $this->plan1->id, 'payment_reference' => 'TXN-1'])->assertSessionHasErrors('plan_id');
    expect(fn () => app(SubscriptionService::class)->request($this->student, $this->qbank2, $this->plan1, 'x', null))->toThrow(ValidationException::class);
});

it('marks locked sidebar items and hides the discussion of demo questions from non-subscribers', function () {
    grantAccess($this->student, $this->qbank2);
    $this->actingAs($this->student)->get('/dashboard')->assertInertia(fn (Assert $page) => $page
        ->where('navigation', fn ($items) => collect($items)->firstWhere('title', 'Mock Tests')['locked'] === true));
    $this->post('/questions/sessions', ['count' => 1]);
    $session = PracticeSession::where('user_id', $this->student->id)->firstOrFail();
    $this->post('/questions/sessions/'.$session->id.'/answer/1', ['option' => 'A']);
    $this->get('/questions/sessions/'.$session->id.'?position=1')->assertInertia(fn (Assert $page) => $page->where('comments', null));
});

it('lets an admin activate, extend and deactivate from the panel, and hides billing from editors', function () {
    $request = Subscription::create(['user_id' => $this->student->id, 'qbank_id' => $this->qbank1->id, 'plan_id' => $this->plan1->id, 'status' => Subscription::STATUS_PENDING, 'payment_reference' => 'TXN-9']);
    $this->actingAs($this->admin);
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    $this->get('/admin/subscriptions')->assertOk();
    expect(yajraHtml(ManageSubscriptions::class))->toContain('TXN-9');

    Livewire::test(ManageSubscriptions::class)->callTableAction('activate', $request, data: ['days' => 180])->assertHasNoTableActionErrors();
    expect($request->refresh()->isCurrent())->toBeTrue()->and($request->activated_by)->toBe($this->admin->id);
    Livewire::test(ManageSubscriptions::class)->callTableAction('cancel', $request, data: ['note' => 'Refunded']);
    expect($request->refresh()->status)->toBe(Subscription::STATUS_CANCELLED)->and($request->admin_note)->toBe('Refunded');

    Livewire::test(ManageSubscriptions::class)->callAction('create', data: [
        'user_id' => $this->student->id, 'qbank_id' => $this->qbank2->id, 'plan_id' => $this->qbank2->plans()->value('id'),
        'status' => Subscription::STATUS_ACTIVE, 'starts_at' => now()->toDateTimeString(), 'ends_at' => now()->addDays(180)->toDateTimeString(),
    ])->assertHasNoActionErrors();
    expect(app(SubscriptionService::class)->hasAccess($this->student, $this->qbank2->id))->toBeTrue();

    $editor = User::factory()->create();
    $editor->assignRole('editor');
    $this->actingAs($editor)->get('/admin/subscriptions')->assertForbidden();
    $this->get('/admin/plans')->assertForbidden();
});

it('shows published textbook chapters by subject to subscribers, with a linked exam reading the MRCP 1 textbook', function () {
    TextbookChapter::create(['exam_id' => $this->mrcp1->id, 'category_id' => $this->category->id, 'title' => 'Heart failure', 'body' => "## Diagnosis\n\n| Test | Use |\n| --- | --- |\n| NT-proBNP | First line |", 'key_points' => '- Start ACE inhibitor', 'sort_order' => 1, 'is_published' => true]);
    TextbookChapter::create(['exam_id' => $this->mrcp1->id, 'category_id' => $this->category->id, 'title' => 'Atrial fibrillation', 'body' => 'Rate control', 'sort_order' => 2, 'is_published' => true]);
    TextbookChapter::create(['exam_id' => $this->mrcp1->id, 'title' => 'Draft chapter', 'body' => 'Not yet', 'is_published' => false]);
    expect(TextbookChapter::create(['exam_id' => $this->mrcp1->id, 'title' => 'Heart failure', 'body' => 'Duplicate title'])->slug)->toBe('heart-failure-2');

    $this->actingAs($this->student)->get('/textbooks')->assertRedirect('/subscription');
    grantAccess($this->student, $this->qbank1);
    $this->post('/exam', ['exam_id' => linkedExam($this->mrcp1)->id]);
    $this->get('/textbooks')->assertInertia(fn (Assert $page) => $page
        ->component('textbooks/Index')
        ->has('subjects', 1)
        ->where('subjects.0.name', 'Cardiology')
        ->where('subjects.0.chapters.0.title', 'Heart failure')
        ->has('subjects.0.chapters', 2));
    $this->get('/textbooks/heart-failure')->assertInertia(fn (Assert $page) => $page
        ->component('textbooks/Show')
        ->where('chapter.body_html', fn (string $html) => str_contains($html, '<table>'))
        ->where('chapter.key_points_html', fn (string $html) => str_contains($html, 'ACE inhibitor'))
        ->where('previous', null)
        ->where('next.slug', 'atrial-fibrillation'));
    $this->get('/textbooks/draft-chapter')->assertNotFound();
});

it('lets editors write textbook chapters but not on a shared-content exam', function () {
    $editor = User::factory()->create();
    $editor->assignRole('editor');
    $this->actingAs($editor);
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    Livewire::test(ManageTextbookChapters::class)->callAction('create', data: [
        'exam_id' => $this->mrcp1->id, 'category_id' => $this->category->id, 'title' => 'Hypertension', 'body' => 'NICE NG136 text', 'is_published' => true,
    ])->assertHasNoActionErrors();
    expect(TextbookChapter::where('slug', 'hypertension')->exists())->toBeTrue();

    expect(fn () => TextbookChapter::create(['exam_id' => linkedExam($this->mrcp1)->id, 'title' => 'X', 'body' => 'Y']))->toThrow(ValidationException::class);
});

it('switches the old 6-month plans off and keeps them for requests that wait for activation', function () {
    // An existing site: the old £8 / 6 months plan, with a request made before the price change.
    Plan::where('qbank_id', $this->qbank1->id)->whereIn('duration_days', [90, 150])->delete();
    $old = Plan::create(['qbank_id' => $this->qbank1->id, 'name' => 'MRCPWorld 1 Qbank', 'duration_days' => 180, 'price' => 8, 'currency' => 'GBP', 'is_active' => true]);
    $waiting = Subscription::create(['user_id' => $this->student->id, 'qbank_id' => $this->qbank1->id, 'plan_id' => $old->id, 'status' => Subscription::STATUS_PENDING]);

    (require database_path('migrations/2026_11_10_100000_set_three_and_five_month_plans.php'))->up();

    expect($old->refresh()->is_active)->toBeFalse()
        ->and($this->qbank1->plans()->where('is_active', true)->orderBy('sort_order')->pluck('duration_days')->all())->toBe([90, 150]);
    // The waiting request still gets the 6 months it asked for.
    $active = app(SubscriptionService::class)->activate($waiting, $this->admin);
    expect($active->ends_at->diffInDays($active->starts_at, true))->toEqualWithDelta(180, 0.01);
});
