<?php

use App\Filament\Resources\FeedbackResource\Pages\ListFeedback;
use App\Jobs\ImportQuestions;
use App\Models\Category;
use App\Models\Exam;
use App\Models\Feedback;
use App\Models\Plan;
use App\Models\Qbank;
use App\Models\Question;
use App\Models\QuestionComment;
use App\Models\QuestionImport;
use App\Models\Subscription;
use App\Models\TextbookChapter;
use App\Models\User;
use App\Notifications\StudentNotice;
use App\Services\QuestionFileReader;
use App\Services\SubscriptionService;
use App\Support\Notify;
use Database\Seeders\ExamSeeder;
use Database\Seeders\PlanSeeder;
use Database\Seeders\RoleSeeder;
use Filament\Facades\Filament;
use Filament\Notifications\DatabaseNotification as FilamentNotification;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Livewire\Livewire;

/** Admin panel (Filament) notifications of one user. */
function panelNotifications(int $userId): Builder
{
    return DB::table('notifications')->where('notifiable_id', $userId)->where('type', FilamentNotification::class);
}

beforeEach(function () {
    $this->seed([ExamSeeder::class, PlanSeeder::class, RoleSeeder::class]);
    $this->exam = Exam::where('slug', 'mrcp-part-1')->firstOrFail();
    $this->qbank = Qbank::where('slug', 'qbank-1')->firstOrFail();
    $this->category = Category::create(['exam_id' => $this->exam->id, 'name' => 'Cardiology', 'slug' => 'cardiology']);
    Question::create([
        'exam_id' => $this->exam->id, 'category_id' => $this->category->id, 'code' => 'CARD-77', 'body' => 'A **patient** with atrial fibrillation',
        'options' => ['A' => 'One', 'B' => 'Two'], 'correct_option' => 'A', 'is_published' => true,
    ]);
    TextbookChapter::create(['exam_id' => $this->exam->id, 'title' => 'Atrial fibrillation', 'body' => 'Text', 'is_published' => true]);
    $this->student = User::factory()->create();
    $this->student->assignRole('student');
    $this->admin = User::factory()->create();
    $this->admin->assignRole('admin');
});

it('finds MCQs, subjects, chapters and pages from the top bar for a subscriber', function () {
    grantAccess($this->student, $this->qbank);
    $response = $this->actingAs($this->student)->getJson('/search/quick?q=atrial')->assertOk();

    $groups = collect($response->json('groups'))->keyBy('label');
    expect($response->json('locked'))->toBeFalse()
        ->and($groups['Questions']['items'][0])->toMatchArray(['title' => 'CARD-77', 'href' => '/questions/search?q=CARD-77'])
        ->and($groups['Questions']['items'][0]['excerpt'])->toBe('A patient with atrial fibrillation')
        ->and($groups['Textbook']['items'][0]['href'])->toBe('/textbooks/atrial-fibrillation');

    $this->getJson('/search/quick?q=cardio')->assertOk()->assertJsonPath('groups.0.label', 'Subjects');
    $this->getJson('/search/quick?q=leader')->assertOk()->assertJsonPath('groups.0.items.0.href', '/leaderboard');
    $this->getJson('/search/quick?q=a')->assertUnprocessable();
});

it('does not search locked content for a student without a subscription', function () {
    $this->actingAs($this->student)->getJson('/search/quick?q=atrial')->assertForbidden();
    // Paid for the other Qbank only: the selected exam's content stays locked.
    grantAccess($this->student, Qbank::where('slug', 'qbank-2')->firstOrFail());
    $response = $this->getJson('/search/quick?q=atrial')->assertOk();

    expect($response->json('locked'))->toBeTrue()
        ->and(collect($response->json('groups'))->pluck('label')->all())->not->toContain('Questions')->not->toContain('Textbook');
});

it('tells the student when an admin activates the subscription, and lists it in the bell', function () {
    $plan = Plan::where('qbank_id', $this->qbank->id)->firstOrFail();
    $request = app(SubscriptionService::class)->request($this->student, $this->qbank, $plan, 'TXN-1', null);

    // The admin was told about the request in the panel bell.
    expect(panelNotifications($this->admin->id)->count())->toBe(1)
        ->and($this->student->notifications()->count())->toBe(0);

    app(SubscriptionService::class)->activate($request, $this->admin);

    $this->actingAs($this->student)->get('/dashboard')->assertInertia(fn (Assert $page) => $page->where('notifications.unread', 1));
    $this->getJson('/notifications')->assertOk()
        ->assertJsonPath('unread', 1)
        ->assertJsonPath('items.0.title', 'Your subscription is active')
        ->assertJsonPath('items.0.read', false);
});

it('marks a notification read when it is opened and follows its link', function () {
    Notify::student($this->student, 'Hello', 'Body', '/subscription', 'Bell');
    $id = $this->student->notifications()->value('id');

    $this->actingAs($this->student)->post('/notifications/'.$id.'/read')->assertRedirect('/subscription');
    expect($this->student->unreadNotifications()->count())->toBe(0);

    Notify::student($this->student, 'One', 'Body');
    Notify::student($this->student, 'Two', 'Body');
    $this->post('/notifications/read-all')->assertRedirect();
    expect($this->student->unreadNotifications()->count())->toBe(0);

    $other = User::factory()->create();
    Notify::student($other, 'Private', 'Body');
    $this->post('/notifications/'.$other->notifications()->value('id').'/read')->assertNotFound();
});

it('keeps admin panel notifications out of the student bell', function () {
    Notify::staff([$this->admin], 'Panel only', 'Body', '/admin');
    $this->actingAs($this->admin)->getJson('/notifications')->assertOk()->assertJsonPath('unread', 0)->assertJsonCount(0, 'items');
});

it('tells a comment author about a reply, but not about their own', function () {
    grantAccess($this->student, $this->qbank);
    $replier = User::factory()->create();
    grantAccess($replier, $this->qbank);
    $thread = QuestionComment::create(['question_id' => Question::value('id'), 'user_id' => $this->student->id, 'body' => 'Question?']);

    $this->actingAs($this->student)->post('/questions/comments/'.$thread->id.'/reply', ['body' => 'My own follow-up']);
    expect($this->student->notifications()->count())->toBe(0);

    $this->actingAs($replier)->post('/questions/comments/'.$thread->id.'/reply', ['body' => 'Here is the answer']);
    expect($this->student->notifications()->where('type', StudentNotice::class)->count())->toBe(1);
});

it('tells moderators about an MCQ report and the student when it is reviewed', function () {
    grantAccess($this->student, $this->qbank);
    $this->actingAs($this->student)->post('/feedback', ['type' => 'content', 'message' => 'The answer key looks wrong here.', 'question_id' => Question::value('id')]);
    expect(panelNotifications($this->admin->id)->count())->toBe(1);

    $this->actingAs($this->admin);
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    Livewire::test(ListFeedback::class)->callTableAction('markReviewed', Feedback::firstOrFail());
    expect($this->student->notifications()->where('type', StudentNotice::class)->value('data')['title'])->toBe('Your MCQ report was reviewed');
});

it('reminds a student once when the subscription ends within a week', function () {
    $soon = grantAccess($this->student, $this->qbank, 5);
    $later = User::factory()->create();
    grantAccess($later, $this->qbank, 40);

    $this->artisan('subscriptions:remind')->expectsOutputToContain('Reminded 1 students')->assertSuccessful();
    $this->artisan('subscriptions:remind')->expectsOutputToContain('Reminded 0 students');

    expect($this->student->notifications()->count())->toBe(1)
        ->and($later->notifications()->count())->toBe(0)
        ->and($soon->refresh()->reminded_at)->not->toBeNull();
});

it('does not remind a student who already renewed', function () {
    grantAccess($this->student, $this->qbank, 5);
    Subscription::create(['user_id' => $this->student->id, 'qbank_id' => $this->qbank->id, 'status' => Subscription::STATUS_PENDING]);

    app(SubscriptionService::class)->remindExpiring();

    expect($this->student->notifications()->count())->toBe(0);
});

it('tells the uploader in the panel when a bulk upload finishes', function () {
    Storage::fake('local');
    $editor = User::factory()->create();
    $editor->assignRole('editor');
    Storage::disk('local')->put('question-imports/n.csv', "code,category_slug,question,option_a,option_b,correct_option\nN-1,cardiology,Stem,One,Two,A\n");
    $import = QuestionImport::create(['user_id' => $editor->id, 'exam_id' => $this->exam->id, 'path' => 'question-imports/n.csv']);

    (new ImportQuestions($import->id))->handle(new QuestionFileReader);

    $data = json_decode((string) panelNotifications($editor->id)->value('data'), true);
    expect($data['title'])->toContain('Bulk upload finished');
});

it('shows the panel bell, the top bar toggle and Ctrl+K search in the admin panel', function () {
    $this->actingAs($this->admin)->get('/admin')->assertOk()
        ->assertSee('fi-topbar', false)
        ->assertSee('mw-topbar-btn', false)
        ->assertSee('Ctrl K');
});
