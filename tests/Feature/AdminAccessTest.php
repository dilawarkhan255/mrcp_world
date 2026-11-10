<?php

use App\Filament\Pages\Dashboard;
use App\Filament\Resources\UserResource\Pages\ManageUsers;
use App\Filament\Widgets\AnswerTrendChart;
use App\Filament\Widgets\ContentStatsWidget;
use App\Filament\Widgets\ContentWorkflow;
use App\Filament\Widgets\LatestFeedbackWidget;
use App\Filament\Widgets\LatestImportsWidget;
use App\Filament\Widgets\QuestionsByExamChart;
use App\Filament\Widgets\StudentActivityWidget;
use App\Models\Category;
use App\Models\Exam;
use App\Models\Feedback;
use App\Models\Question;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Filament\Facades\Filament;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\EditAction;
use Inertia\Testing\AssertableInertia as Assert;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    $this->admin = User::factory()->create();
    $this->admin->assignRole('admin');
    $this->editor = User::factory()->create();
    $this->editor->assignRole('editor');
    $this->student = User::factory()->create();
    $this->student->assignRole('student');
});

it('keeps the user list for administrators only', function () {
    $this->actingAs($this->editor)->get('/admin/users')->assertForbidden();
    $this->actingAs($this->student)->get('/admin/users')->assertForbidden();
    $this->actingAs($this->admin)->get('/admin/users')->assertOk();
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    expect(collect(yajraRows(ManageUsers::class))->pluck('_key')->sort()->values()->all())
        ->toBe(collect([$this->admin, $this->editor, $this->student])->map(fn (User $user): string => (string) $user->id)->sort()->values()->all());
});

it('masks the email address of a student for a panel user without the manage permission', function () {
    User::factory()->create(['email' => 'student@example.test']);
    $this->editor->givePermissionTo('billing.users.view');
    $this->actingAs($this->editor);
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    expect(yajraHtml(ManageUsers::class))->toContain('st***@example.test')->not->toContain('student@example.test');
});

it('searches the users on the server', function () {
    $findable = User::factory()->create(['name' => 'Findable Student', 'email' => 'findable@example.test']);
    $this->actingAs($this->admin);
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    expect(collect(yajraRows(ManageUsers::class, ['search' => 'Findable']))->pluck('_key')->all())->toBe([(string) $findable->id]);
});

it('renders the admin dashboard with the content stats widget', function () {
    $this->actingAs($this->admin);

    Livewire::test(Dashboard::class)->assertSuccessful();
});

it('renders every dashboard widget for an admin with real data', function () {
    $exam = Exam::factory()->create();
    $category = Category::create(['exam_id' => $exam->id, 'name' => 'Cardiology', 'slug' => 'cardiology']);
    Question::create(['exam_id' => $exam->id, 'category_id' => $category->id, 'code' => 'Q1', 'body' => 'Body', 'options' => ['A' => 'a', 'B' => 'b'], 'correct_option' => 'A', 'is_published' => true]);
    Feedback::create(['user_id' => $this->student->id, 'exam_id' => $exam->id, 'type' => Feedback::TYPE_BUG, 'message' => 'Something is broken here', 'status' => Feedback::STATUS_NEW]);
    $this->actingAs($this->admin);

    Livewire::test(ContentStatsWidget::class)->assertSee('Questions')->assertSee('100% of the library is live');
    Livewire::test(StudentActivityWidget::class)->assertSee('Students')->assertSee('Open feedback');
    Livewire::test(AnswerTrendChart::class)->assertSuccessful();
    Livewire::test(QuestionsByExamChart::class)->assertSuccessful();
    Livewire::test(LatestImportsWidget::class)->assertSee('No uploads yet');
    Livewire::test(LatestFeedbackWidget::class)->assertSee('Something is broken');
    Livewire::test(ContentWorkflow::class)->assertSee('Publishing workflow');
    $this->get('/admin')->assertOk()->assertSeeLivewire(StudentActivityWidget::class)->assertSeeLivewire(LatestFeedbackWidget::class);
});

it('hides student and billing figures from editors on the dashboard', function () {
    $this->actingAs($this->editor);

    expect(StudentActivityWidget::canView())->toBeFalse()
        ->and(AnswerTrendChart::canView())->toBeFalse()
        ->and(LatestImportsWidget::canView())->toBeTrue();
    $this->get('/admin')->assertOk()->assertDontSeeLivewire(StudentActivityWidget::class)->assertSeeLivewire(ContentStatsWidget::class);
});

it('shows the privacy notice to the panel staff and hides it from students', function () {
    $this->actingAs($this->editor)->get('/admin/privacy')->assertOk()->assertSee('Privacy');
    $this->actingAs($this->admin)->get('/admin/privacy')->assertOk();
    $this->actingAs($this->student)->get('/admin/privacy')->assertForbidden();
});

it('publishes the privacy notice on the public site', function () {
    $this->get('/privacy')->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('Legal/Privacy')
        ->has('sections', 11)
        ->where('sections.0.title', 'Who is responsible')
        ->where('sections.1.title', 'What we store')
    );
});

it('gives an editor the content permissions and keeps the billing permissions for an admin', function () {
    expect($this->editor->can('content.questions.create'))->toBeTrue()
        ->and($this->editor->can('content.questions.publish'))->toBeFalse()
        ->and($this->editor->can('billing.users.view'))->toBeFalse()
        ->and($this->admin->can('content.questions.publish'))->toBeTrue()
        ->and($this->admin->can('billing.users.manage'))->toBeTrue();
});

it('shows the Day / Dark switch in the admin sidebar, synced with the student app', function () {
    $this->actingAs($this->admin)->get('/admin')->assertOk()
        ->assertSee('mw-appearance', false)
        ->assertSee("localStorage.setItem('appearance', value)", false)
        ->assertSee("localStorage.getItem('appearance')", false);
    expect(file_get_contents(resource_path('js/composables/useAppearance.ts')))->toContain("localStorage.setItem('theme', value)");
});

it('links from the admin panel to the student app', function () {
    $this->actingAs($this->admin)->get('/admin')->assertOk()->assertSee('Student app')->assertSee('/dashboard', false);
    // An admin can open the student app; content staff need no subscription (see SubscriptionAccessTest).
    $this->get('/dashboard')->assertOk();
});

it('uses the same trimmed, transparent logo and favicon as the student app', function () {
    $this->actingAs($this->admin)->get('/admin')->assertOk()
        ->assertSee('brand/mrcp-world-logo.trim.png', false)
        ->assertSee('brand/mrcp-world-mark.trim.png', false);
    expect(file_get_contents(resource_path('js/components/AppSidebar.vue')))->toContain('/brand/mrcp-world-logo.trim.png');
});

it('shows table row actions as icon buttons with their name as a tooltip', function () {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    $edit = EditAction::make();
    $delete = DeleteAction::make();

    expect($edit->isIconButton())->toBeTrue()
        ->and($edit->getIcon())->toBe('heroicon-m-pencil-square')
        ->and($edit->getTooltip())->toBe($edit->getLabel())
        ->and($delete->isIconButton())->toBeTrue()
        ->and($delete->getIcon())->toBe('heroicon-m-trash')
        ->and($delete->getTooltip())->toBe($delete->getLabel());
});

it('joins the admin top bar border with the logo row and scrolls only the menu', function () {
    $this->actingAs($this->admin)->get('/admin')->assertOk()
        ->assertDontSee('.fi-topbar::after', false)
        ->assertSee('.fi-sidebar-header { position: relative; z-index: 10; flex-shrink: 0; }', false)
        ->assertSee('.fi-sidebar-nav { overscroll-behavior: contain;', false);
    $sidebar = file_get_contents(resource_path('js/components/AppSidebar.vue'));
    expect($sidebar)->toContain('mw-sidebar-scroll')->toContain('relative z-10 h-20 shrink-0')
        ->and(file_get_contents(resource_path('js/components/AppSidebarHeader.vue')))->not->toContain('brand-rainbow');
});
