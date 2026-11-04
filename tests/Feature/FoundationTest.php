<?php

use App\Filament\Resources\CategoryResource\Pages\ManageCategories;
use App\Filament\Resources\ExamResource\Pages\ManageExams;
use App\Models\Category;
use App\Models\Exam;
use App\Models\Qbank;
use App\Models\User;
use App\Support\StudentNavigation;
use Database\Seeders\ExamSeeder;
use Database\Seeders\RoleSeeder;
use Filament\Facades\Filament;
use Inertia\Testing\AssertableInertia as Assert;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed([ExamSeeder::class, RoleSeeder::class]);
});

it('seeds the two Qbanks and their four exams idempotently', function () {
    $this->seed(ExamSeeder::class);
    expect(Exam::orderBy('sort_order')->pluck('name')->all())->toBe(['MRCP 1 UK/Irish', 'IMM', 'MRCP 2 UK/Irish', 'FCPS Part 2'])
        ->and(Qbank::orderBy('sort_order')->pluck('name')->all())->toBe(['MRCPWorld 1 Qbank', 'MRCPWorld 2 Qbank']);
    $mrcp1 = Exam::where('slug', 'mrcp-part-1')->firstOrFail();
    $mrcp2 = Exam::where('slug', 'mrcp-part-2-written')->firstOrFail();
    expect(Exam::where('slug', 'imm')->value('content_exam_id'))->toBe($mrcp1->id)
        ->and(Exam::where('slug', 'fcps-part-2')->value('content_exam_id'))->toBe($mrcp2->id)
        ->and($mrcp1->content_exam_id)->toBeNull();
});

it('shares database exams and persists the selected exam', function () {
    $this->actingAs(User::factory()->create());
    $exams = Exam::whereNull('content_exam_id')->orderBy('id')->get();
    $this->get('/dashboard')->assertInertia(fn (Assert $page) => $page->component('Dashboard')->has('exams', 4)->where('exams.1.qbank', 'MRCPWorld 1 Qbank')->where('currentExam.id', $exams[0]->id));
    $this->post('/exam', ['exam_id' => $exams[1]->id])->assertSessionHas('exam_id', $exams[1]->id);
    $this->get('/dashboard')->assertInertia(fn (Assert $page) => $page->where('currentExam.id', $exams[1]->id));
    $exams[1]->update(['is_active' => false]);
    $this->post('/exam', ['exam_id' => $exams[1]->id])->assertSessionHasErrors('exam_id');
    $this->post('/exam', ['exam_id' => 99999])->assertSessionHasErrors('exam_id');
    $this->get('/dashboard')->assertInertia(fn (Assert $page) => $page->has('exams', 3)->where('currentExam.id', $exams[0]->id));
});

it('protects exam selection and opens every sidebar page to a subscriber', function () {
    $this->post('/exam', ['exam_id' => Exam::first()->id])->assertRedirect('/login');
    $this->actingAs(User::factory()->unverified()->create())->get('/questions/bank')->assertRedirect('/verify-email');
    $student = User::factory()->create();
    grantAccess($student, Exam::orderBy('sort_order')->firstOrFail()->qbank_id);
    $this->actingAs($student);
    foreach (StudentNavigation::staticHrefs() as $href) {
        $this->get($href)->assertOk();
    }
});

it('sends a student without a subscription from locked pages to the subscription page', function () {
    $this->actingAs(User::factory()->create());
    foreach (['/questions/past-papers', '/questions/search', '/questions/mocks', '/questions/review', '/revision/notes', '/image-atlas', '/textbooks', '/questions/comments'] as $href) {
        $this->get($href)->assertRedirect('/subscription');
    }
    foreach (['/dashboard', '/questions/bank', '/questions/sessions', '/questions/performance', '/subscription', '/feedback'] as $href) {
        $this->get($href)->assertOk();
    }
});

it('allows only verified editors and admins into every panel resource', function (string $role, bool $verified, int $status) {
    $user = User::factory()->create(['email_verified_at' => $verified ? now() : null]);
    $user->assignRole($role);
    foreach (['/admin', '/admin/exams', '/admin/categories'] as $url) {
        $this->actingAs($user)->get($url)->assertStatus($status);
    }
})->with([
    ['student', true, 403], ['editor', true, 200], ['admin', true, 200], ['admin', false, 403],
]);

it('redirects guests from the admin panel', function () {
    $this->get('/admin')->assertRedirect('/admin/login');
});

it('allows editors to create and edit exams and categories', function () {
    $editor = User::factory()->create();
    $editor->assignRole('editor');
    $this->actingAs($editor);
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    Livewire::test(ManageExams::class)->callAction('create', data: ['name' => 'Foundation exam', 'slug' => 'foundation-exam', 'is_active' => true])->assertHasNoActionErrors();
    $exam = Exam::where('slug', 'foundation-exam')->firstOrFail();
    Livewire::test(ManageExams::class)->callTableAction('edit', $exam, data: ['name' => 'Updated exam', 'slug' => 'foundation-exam', 'is_active' => true])->assertHasNoTableActionErrors();
    expect($exam->fresh()->name)->toBe('Updated exam');
    Livewire::test(ManageCategories::class)->callAction('create', data: ['exam_id' => $exam->id, 'name' => 'Cardiology', 'slug' => 'cardiology'])->assertHasNoActionErrors();
    $category = Category::where('slug', 'cardiology')->firstOrFail();
    Livewire::test(ManageCategories::class)->callTableAction('edit', $category, data: ['exam_id' => $exam->id, 'name' => 'Clinical cardiology', 'slug' => 'cardiology'])->assertHasNoTableActionErrors();
    expect($category->fresh()->name)->toBe('Clinical cardiology')->and($category->exam->is($exam))->toBeTrue();
});

it('denies students direct resource component access', function () {
    $student = User::factory()->create();
    $student->assignRole('student');
    $this->actingAs($student);
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    Livewire::test(ManageExams::class)->assertForbidden();
    Livewire::test(ManageCategories::class)->assertForbidden();
});

it('routes staff logins to the panel and exposes management navigation only to staff', function (string $role, string $destination, bool $canManage) {
    $user = User::factory()->create();
    $user->assignRole($role);
    $this->post('/login', ['email' => $user->email, 'password' => 'password'])->assertRedirect($destination);
    $this->get('/dashboard')->assertInertia(fn (Assert $page) => $page->where('auth.canManageContent', $canManage));
})->with([
    ['admin', '/admin', true],
    ['editor', '/admin', true],
    ['student', '/dashboard', false],
]);

it('makes an Inertia login into the admin panel a full browser visit', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $this->withHeaders(['X-Inertia' => 'true'])
        ->post('/login', ['email' => $admin->email, 'password' => 'password'])
        ->assertStatus(409)
        ->assertHeader('X-Inertia-Location', '/admin');
});
