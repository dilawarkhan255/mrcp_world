<?php

use App\Filament\Resources\CategoryResource\Pages\ManageCategories;
use App\Filament\Resources\ExamResource\Pages\ManageExams;
use App\Models\Category;
use App\Models\Exam;
use App\Models\User;
use Database\Seeders\ExamSeeder;
use Database\Seeders\RoleSeeder;
use Filament\Facades\Filament;
use Inertia\Testing\AssertableInertia as Assert;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed([ExamSeeder::class, RoleSeeder::class]);
});

it('seeds exactly the two exams idempotently', function () {
    $this->seed(ExamSeeder::class);
    expect(Exam::orderBy('id')->pluck('name')->all())->toBe(['MRCP Part 1', 'MRCP Part 2 Written']);
});

it('shares database exams and persists the selected exam', function () {
    $this->actingAs(User::factory()->create());
    $exams = Exam::orderBy('id')->get();
    $this->get('/dashboard')->assertInertia(fn (Assert $page) => $page->component('Dashboard')->has('exams', 2)->where('currentExam.id', $exams[0]->id));
    $this->post('/exam', ['exam_id' => $exams[1]->id])->assertSessionHas('exam_id', $exams[1]->id);
    $this->get('/dashboard')->assertInertia(fn (Assert $page) => $page->where('currentExam.id', $exams[1]->id));
    $exams[1]->update(['is_active' => false]);
    $this->post('/exam', ['exam_id' => $exams[1]->id])->assertSessionHasErrors('exam_id');
    $this->post('/exam', ['exam_id' => 99999])->assertSessionHasErrors('exam_id');
    $this->get('/dashboard')->assertInertia(fn (Assert $page) => $page->has('exams', 1)->where('currentExam.id', $exams[0]->id));
});

it('protects exam selection and all student placeholders', function () {
    $this->post('/exam', ['exam_id' => Exam::first()->id])->assertRedirect('/login');
    $this->actingAs(User::factory()->unverified()->create())->get('/questions/bank')->assertRedirect('/verify-email');
    $this->actingAs(User::factory()->create());
    foreach (config('student.groups') as $group) {
        foreach ($group['items'] as $item) {
            $this->get($item['href'])->assertOk();
        }
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
