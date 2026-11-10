<?php

use App\Filament\Resources\ExamResource\Pages\ManageExams;
use App\Filament\Resources\QbankResource\Pages\ManageQbanks;
use App\Models\Category;
use App\Models\Exam;
use App\Models\PastPaper;
use App\Models\Qbank;
use App\Models\Question;
use App\Models\User;
use Database\Seeders\ExamSeeder;
use Database\Seeders\RoleSeeder;
use Filament\Facades\Filament;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed([ExamSeeder::class, RoleSeeder::class]);
    $this->mrcp1 = Exam::where('slug', 'mrcp-part-1')->firstOrFail();
    $this->imm = Exam::where('slug', 'imm')->firstOrFail();
    $this->category = Category::create(['exam_id' => $this->mrcp1->id, 'name' => 'Cardiology', 'slug' => 'cardiology']);
    foreach (range(1, 3) as $index) {
        Question::create([
            'exam_id' => $this->mrcp1->id, 'category_id' => $this->category->id, 'code' => 'CARD-'.$index,
            'body' => 'Question '.$index, 'options' => ['A' => 'One', 'B' => 'Two'], 'correct_option' => 'A', 'is_published' => true,
        ]);
    }
});

it('shows the MRCP 1 bank to a student who selects IMM, under the IMM name', function () {
    $student = User::factory()->create();
    grantAccess($student, $this->imm->qbank_id);
    $this->actingAs($student);
    $this->post('/exam', ['exam_id' => $this->imm->id])->assertSessionHas('exam_id', $this->imm->id);

    $this->get('/questions/bank')->assertInertia(fn (Assert $page) => $page
        ->component('questions/Bank')
        ->where('exam.name', 'IMM')
        ->where('exam.id', $this->mrcp1->id)
        ->where('availableQuestions', 3)
        ->where('currentExam.id', $this->imm->id));
    $this->get('/questions/subjects/cardiology')->assertOk();
});

it('stores a session started from IMM against the shared MRCP 1 bank', function () {
    $user = User::factory()->create();
    grantAccess($user, $this->imm->qbank_id);
    $this->actingAs($user)->post('/exam', ['exam_id' => $this->imm->id]);
    $this->post('/questions/sessions', ['count' => 2])->assertRedirect();

    $this->assertDatabaseHas('practice_sessions', ['user_id' => $user->id, 'exam_id' => $this->mrcp1->id, 'total_questions' => 2]);
});

it('counts the shared bank on the landing page for an exam without its own content', function () {
    $this->get('/')->assertInertia(fn (Assert $page) => $page
        ->where('exams.0.name', 'MRCP 1')->where('exams.0.question_count', 3)
        ->where('exams.1.name', 'IMM')->where('exams.1.question_count', 3));
});

it('refuses subjects and papers on an exam that shares another bank', function () {
    expect(fn () => Category::create(['exam_id' => $this->imm->id, 'name' => 'X', 'slug' => 'x']))->toThrow(ValidationException::class)
        ->and(fn () => PastPaper::create(['exam_id' => $this->imm->id, 'title' => 'P', 'slug' => 'p', 'year' => 2024]))->toThrow(ValidationException::class);
});

it('validates the shared-content link of an exam', function () {
    $other = Qbank::create(['name' => 'Other', 'slug' => 'other']);
    $mrcp2 = Exam::where('slug', 'mrcp-part-2-written')->firstOrFail();

    // Must share within the same Qbank.
    expect(fn () => Exam::create(['name' => 'Wrong bank', 'slug' => 'wrong-bank', 'qbank_id' => $other->id, 'content_exam_id' => $this->mrcp1->id]))
        ->toThrow(ValidationException::class);
    // Cannot chain through another shared exam.
    expect(fn () => Exam::create(['name' => 'Chain', 'slug' => 'chain', 'qbank_id' => $this->imm->qbank_id, 'content_exam_id' => $this->imm->id]))
        ->toThrow(ValidationException::class);
    // An exam with its own subjects cannot start sharing another bank.
    expect(fn () => $this->mrcp1->update(['content_exam_id' => $mrcp2->id]))->toThrow(ValidationException::class);
});

it('lets content staff manage Qbanks and exams from the admin panel', function () {
    $editor = User::factory()->create();
    $editor->assignRole('editor');
    $this->actingAs($editor);
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    $this->get('/admin/qbanks')->assertOk();
    $this->get('/admin/exams')->assertOk();
    expect(yajraHtml(ManageQbanks::class))->toContain('MRCPWorld 1 Qbank')
        ->and(yajraHtml(ManageExams::class))->toContain('FCPS Part 2');
    Livewire::test(ManageQbanks::class)
        ->callAction('create', data: ['name' => 'PACES', 'slug' => 'paces', 'sort_order' => 3, 'is_active' => false])
        ->assertHasNoActionErrors();
    Livewire::test(ManageExams::class)
        ->callAction('create', data: ['name' => 'PACES UK', 'slug' => 'paces-uk', 'qbank_id' => Qbank::where('slug', 'paces')->value('id'), 'is_active' => true])
        ->assertHasNoActionErrors();
    expect(Exam::where('slug', 'paces-uk')->value('qbank_id'))->toBe(Qbank::where('slug', 'paces')->value('id'));
});

it('hides Qbanks from students', function () {
    $student = User::factory()->create();
    $student->assignRole('student');
    $this->actingAs($student)->get('/admin/qbanks')->assertForbidden();
});
