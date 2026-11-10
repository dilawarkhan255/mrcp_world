<?php

use App\Filament\Resources\CategoryResource\Pages\ManageCategories;
use App\Filament\Resources\ExamResource\Pages\ManageExams;
use App\Filament\Resources\QuestionResource\Pages\ManageQuestions;
use App\Filament\Resources\UserResource\Pages\ManageUsers;
use App\Models\Category;
use App\Models\Exam;
use App\Models\Question;
use App\Models\User;
use Database\Seeders\ExamSeeder;
use Database\Seeders\PlanSeeder;
use Database\Seeders\RoleSeeder;
use Filament\Facades\Filament;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

/** @return array<string, mixed> */
function yajraRequest(array $overrides = []): array
{
    return array_merge(['draw' => 1, 'start' => 0, 'length' => 25, 'search' => '', 'sort' => '', 'dir' => ''], $overrides);
}

function adminQuestion(Exam $exam, Category $category, string $code, array $overrides = []): Question
{
    return Question::create(array_merge([
        'exam_id' => $exam->id, 'category_id' => $category->id, 'code' => $code, 'body' => 'Stem '.$code,
        'options' => ['A' => 'One', 'B' => 'Two'], 'correct_option' => 'A', 'is_published' => true,
    ], $overrides));
}

beforeEach(function () {
    $this->seed([ExamSeeder::class, PlanSeeder::class, RoleSeeder::class]);
    $this->admin = User::factory()->create(['name' => 'Admin person']);
    $this->admin->assignRole('admin');
    $this->exam = Exam::where('slug', 'mrcp-part-1')->firstOrFail();
    $this->category = Category::create(['exam_id' => $this->exam->id, 'name' => 'Cardiology', 'slug' => 'cardiology']);
    Filament::setCurrentPanel(Filament::getPanel('admin'));
});

it('renders every admin list as a Yajra DataTable instead of the Filament table', function (string $url) {
    $this->actingAs($this->admin)->get($url)->assertOk()
        ->assertSee('mwYajraTable', false)
        ->assertSee('admin-yajra', false)
        ->assertDontSee('fi-ta-table', false);
})->with([
    '/admin/questions', '/admin/question-imports', '/admin/past-papers', '/admin/exams', '/admin/categories',
    '/admin/qbanks', '/admin/plans', '/admin/subscriptions', '/admin/users', '/admin/roles',
    '/admin/feedback', '/admin/textbook-chapters', '/admin/atlas-images',
]);

it('returns one page of server-side rows with Filament cells and only DataTables keys', function () {
    adminQuestion($this->exam, $this->category, 'Q-1', ['body' => 'Stem with <b>markup</b>']);
    $this->actingAs($this->admin);

    $rows = Livewire::test(ManageQuestions::class)->instance()->yajraRows(yajraRequest());

    expect(array_keys($rows))->toBe(['draw', 'recordsTotal', 'recordsFiltered', 'data'])
        ->and($rows['recordsTotal'])->toBe(1)
        ->and($rows['data'][0]['c0'])->toContain('Q-1')->toContain('fi-ta-col-wrp')
        // Cell text is escaped by the Filament column.
        ->and($rows['data'][0]['c1'])->toContain('&lt;b&gt;markup&lt;/b&gt;')->not->toContain('<b>markup</b>')
        // Row actions are the Filament buttons that open the usual modals.
        ->and($rows['data'][0]['_actions'])->toContain('mountTableAction(&#039;edit&#039;');
});

it('searches, sorts and pages through the resource query', function () {
    foreach (['CARD-1', 'CARD-2', 'CARD-3', 'NEPH-1'] as $code) {
        adminQuestion($this->exam, $this->category, $code);
    }
    $this->actingAs($this->admin);
    $page = Livewire::test(ManageQuestions::class)->instance();

    $search = $page->yajraRows(yajraRequest(['search' => 'CARD', 'sort' => 'c0', 'dir' => 'desc']));
    expect($search['recordsTotal'])->toBe(4)->and($search['recordsFiltered'])->toBe(3)
        ->and($search['data'][0]['c0'])->toContain('CARD-3');

    $second = $page->yajraRows(yajraRequest(['sort' => 'c0', 'dir' => 'asc', 'start' => 2, 'length' => 2]));
    expect($second['data'])->toHaveCount(2)->and($second['data'][0]['c0'])->toContain('CARD-3');

    // An oversized page is capped; an unknown sort key falls back to the default sort.
    expect($page->yajraRows(yajraRequest(['length' => 100000, 'sort' => 'c99']))['data'])->toHaveCount(4);
});

it('applies the Filament filters to the rows', function () {
    adminQuestion($this->exam, $this->category, 'LIVE-1');
    adminQuestion($this->exam, $this->category, 'DRAFT-1', ['is_published' => false]);
    $this->actingAs($this->admin);

    $rows = Livewire::test(ManageQuestions::class)
        ->set('tableFilters.is_published.value', '0')
        ->instance()->yajraRows(yajraRequest());

    expect($rows['recordsFiltered'])->toBe(1)->and($rows['data'][0]['c0'])->toContain('DRAFT-1');
});

it('only shows the row actions a record allows', function () {
    $this->actingAs($this->admin);
    $rows = collect(Livewire::test(ManageExams::class)->instance()->yajraRows(yajraRequest(['length' => 50]))['data']);

    $withSubjects = $rows->firstWhere('_key', (string) $this->exam->id);
    $empty = $rows->firstWhere('_key', (string) Exam::where('slug', 'imm')->value('id'));
    expect($withSubjects['_actions'])->not->toContain('mountTableAction(&#039;delete&#039;')
        ->and($empty['_actions'])->toContain('mountTableAction(&#039;delete&#039;');
});

it('keeps the Filament row actions working behind the Yajra table', function () {
    $student = User::factory()->create(['name' => 'Old name']);
    $student->assignRole('student');
    $this->actingAs($this->admin);

    Livewire::test(ManageUsers::class)
        ->callTableAction('edit', $student, ['name' => 'New name', 'email' => $student->email, 'roles' => [Role::findByName('student')->id]])
        ->assertHasNoTableActionErrors();
    expect($student->fresh()->name)->toBe('New name');
});

it('lists bulk actions as toolbar buttons', function () {
    $this->actingAs($this->admin);
    $this->get('/admin/atlas-images')->assertOk()->assertSee('Publish')->assertSee('Unpublish')->assertSee('bulk(', false);
});

it('keeps the yajra rows behind the admin panel', function () {
    $student = User::factory()->create();
    $student->assignRole('student');
    $this->actingAs($student)->get('/admin/questions')->assertForbidden();
});

it('renders the action modals on every Yajra list so New, Edit and Delete can open', function (string $url) {
    // Regression: the Filament page leaves its modals to the table markup, which the
    // Yajra page replaces; without them no create, edit or delete modal could open.
    $this->actingAs($this->admin)->get($url)->assertOk()
        ->assertSee('wire:submit.prevent="callMountedAction"', false)
        ->assertSee('wire:submit.prevent="callMountedTableAction"', false)
        ->assertSee('wire:submit.prevent="callMountedTableBulkAction"', false);
})->with(['/admin/categories', '/admin/questions', '/admin/subscriptions', '/admin/atlas-images']);

it('compiles the bulk action buttons into valid Alpine expressions', function () {
    $this->actingAs($this->admin)->get('/admin/atlas-images')->assertOk()
        ->assertDontSee('@js(', false)
        ->assertSee("x-on:click=\"bulk('publish')\"", false);
});

it('offers View, Edit and Delete on the rows and a bulk delete on every list', function () {
    $this->actingAs($this->admin);
    $rows = yajraRows(ManageCategories::class);
    $empty = collect($rows)->firstWhere('_key', (string) $this->category->id);
    expect($empty['_actions'])->toContain('mountTableAction(&#039;view&#039;')
        ->toContain('mountTableAction(&#039;edit&#039;')
        ->toContain('mountTableAction(&#039;delete&#039;');
    $this->get('/admin/categories')->assertOk()->assertSee('Delete selected')->assertSee('bulk(', false);
});

it('bulk deletes only what can safely go and reports the rest', function () {
    $used = $this->category;
    adminQuestion($this->exam, $used, 'KEEP-1');
    $free = Category::create(['exam_id' => $this->exam->id, 'name' => 'Empty subject', 'slug' => 'empty-subject']);
    $this->actingAs($this->admin);

    Livewire::test(ManageCategories::class)
        ->callTableBulkAction('deleteSelected', [$used, $free])
        ->assertNotified('1 deleted, 1 skipped (still in use or not allowed)');

    expect(Category::find($used->id))->not->toBeNull()->and(Category::find($free->id))->toBeNull();
});

it('never bulk deletes your own account or an admin', function () {
    $other = User::factory()->create();
    $other->assignRole('student');
    $this->actingAs($this->admin);

    Livewire::test(ManageUsers::class)->callTableBulkAction('deleteSelected', [$this->admin, $other]);

    expect(User::find($this->admin->id))->not->toBeNull()->and(User::find($other->id))->toBeNull();
});

it('archives selected MCQs instead of deleting them', function () {
    $question = adminQuestion($this->exam, $this->category, 'ARCH-1');
    $this->actingAs($this->admin);

    Livewire::test(ManageQuestions::class)->callTableBulkAction('deleteSelected', [$question]);

    expect(Question::find($question->id))->toBeNull()->and(Question::withTrashed()->find($question->id))->not->toBeNull();
});
