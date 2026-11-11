<?php

use App\Filament\Resources\CategoryResource\Pages\ManageCategories;
use App\Filament\Resources\ExamResource\Pages\ManageExams;
use App\Filament\Resources\QbankResource\Pages\ManageQbanks;
use App\Filament\Resources\QuestionImportResource\Pages\ManageQuestionImports;
use App\Filament\Resources\QuestionResource\Pages\ManageQuestions;
use App\Models\AtlasImage;
use App\Models\Category;
use App\Models\Exam;
use App\Models\Flashcard;
use App\Models\PracticeSession;
use App\Models\Qbank;
use App\Models\Question;
use App\Models\QuestionComment;
use App\Models\QuestionImport;
use App\Models\TextbookChapter;
use App\Models\User;
use Database\Seeders\ExamSeeder;
use Database\Seeders\PlanSeeder;
use Database\Seeders\RoleSeeder;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed([ExamSeeder::class, PlanSeeder::class, RoleSeeder::class]);
    $this->admin = User::factory()->create();
    $this->admin->assignRole('admin');
    $this->editor = User::factory()->create();
    $this->editor->assignRole('editor');
    $this->exam = Exam::where('slug', 'mrcp-part-1')->firstOrFail();
    $this->category = Category::create(['exam_id' => $this->exam->id, 'name' => 'Cardiology', 'slug' => 'cardiology']);
    $this->question = Question::create([
        'exam_id' => $this->exam->id, 'category_id' => $this->category->id, 'code' => 'DEL-1', 'body' => 'Stem',
        'options' => ['A' => 'One', 'B' => 'Two'], 'correct_option' => 'A', 'is_published' => true,
    ]);
    Filament::setCurrentPanel(Filament::getPanel('admin'));
});

it('lets an admin delete a subject with its MCQs after typing its name', function () {
    $this->actingAs($this->admin);

    Livewire::test(ManageCategories::class)
        ->assertTableActionVisible('deleteWithContents', $this->category)
        ->callTableAction('deleteWithContents', $this->category, data: ['confirm' => 'wrong'])
        ->assertHasTableActionErrors(['confirm']);
    expect(Category::whereKey($this->category->id)->exists())->toBeTrue();

    Livewire::test(ManageCategories::class)
        ->callTableAction('deleteWithContents', $this->category, data: ['confirm' => 'Cardiology'])
        ->assertHasNoTableActionErrors();
    expect(Category::whereKey($this->category->id)->exists())->toBeFalse()
        ->and(DB::table('questions')->where('id', $this->question->id)->exists())->toBeFalse();
});

it('keeps the delete-with-contents action from editors', function () {
    $this->actingAs($this->editor);
    Livewire::test(ManageCategories::class)->assertTableActionHidden('deleteWithContents', $this->category);
});

it('deletes an exam with its subjects, MCQs, chapters, flashcards, sessions and uploads', function () {
    $student = User::factory()->create();
    PracticeSession::create(['user_id' => $student->id, 'exam_id' => $this->exam->id, 'total_questions' => 1]);
    TextbookChapter::create(['exam_id' => $this->exam->id, 'title' => 'Chapter', 'body' => 'Text']);
    Flashcard::create(['exam_id' => $this->exam->id, 'front' => 'F', 'back' => 'B']);
    Storage::fake('local');
    Storage::disk('local')->put('question-imports/a.csv', 'x');
    QuestionImport::create(['user_id' => $this->admin->id, 'exam_id' => $this->exam->id, 'path' => 'question-imports/a.csv']);
    $this->actingAs($this->admin);

    Livewire::test(ManageExams::class)->callTableAction('deleteWithContents', $this->exam, data: ['confirm' => 'MRCP 1'])->assertHasNoTableActionErrors();

    expect(Exam::whereKey($this->exam->id)->exists())->toBeFalse()
        ->and(DB::table('categories')->where('exam_id', $this->exam->id)->count())->toBe(0)
        ->and(DB::table('questions')->where('exam_id', $this->exam->id)->count())->toBe(0)
        ->and(DB::table('practice_sessions')->where('exam_id', $this->exam->id)->count())->toBe(0)
        ->and(DB::table('textbook_chapters')->count())->toBe(0)
        ->and(DB::table('flashcards')->count())->toBe(0)
        ->and(QuestionImport::count())->toBe(0)
        ->and(Storage::disk('local')->exists('question-imports/a.csv'))->toBeFalse()
        ->and(User::whereKey($student->id)->exists())->toBeTrue();
});

it('deletes a Qbank with its exams, plans and subscriptions', function () {
    $qbank = Qbank::where('slug', 'qbank-1')->firstOrFail();
    grantAccess(User::factory()->create(), $qbank);
    $this->actingAs($this->admin);

    Livewire::test(ManageQbanks::class)->callTableAction('deleteWithContents', $qbank, data: ['confirm' => $qbank->name])->assertHasNoTableActionErrors();

    expect(Qbank::whereKey($qbank->id)->exists())->toBeFalse()
        ->and(Exam::where('qbank_id', $qbank->id)->exists())->toBeFalse()
        ->and(DB::table('plans')->where('qbank_id', $qbank->id)->count())->toBe(0)
        ->and(DB::table('subscriptions')->where('qbank_id', $qbank->id)->count())->toBe(0)
        ->and(Exam::where('slug', 'mrcp-part-2-written')->exists())->toBeTrue();
});

it('archives, restores and permanently deletes an MCQ', function () {
    $this->actingAs($this->admin);
    Livewire::test(ManageQuestions::class)->callTableAction('delete', $this->question);
    expect($this->question->fresh()->trashed())->toBeTrue();

    // Archived MCQs are listed with the Archived filter.
    $archived = Question::withTrashed()->findOrFail($this->question->id);
    Livewire::test(ManageQuestions::class)->filterTable('trashed', true)->callTableAction('restore', $archived);
    expect($this->question->fresh()->trashed())->toBeFalse();

    Livewire::test(ManageQuestions::class)->callTableAction('deleteWithContents', $this->question, data: ['confirm' => 'DEL-1'])->assertHasNoTableActionErrors();
    expect(Question::withTrashed()->whereKey($this->question->id)->exists())->toBeFalse();
});

it('lets an admin delete an upload record and its file, but the MCQs stay', function () {
    Storage::fake('local');
    Storage::disk('local')->put('question-imports/b.csv', 'x');
    $import = QuestionImport::create(['user_id' => $this->admin->id, 'exam_id' => $this->exam->id, 'path' => 'question-imports/b.csv']);

    $this->actingAs($this->editor);
    Livewire::test(ManageQuestionImports::class)->assertTableActionHidden('delete', $import);

    $this->actingAs($this->admin);
    Livewire::test(ManageQuestionImports::class)->callTableAction('delete', $import);
    expect(QuestionImport::count())->toBe(0)->and(Storage::disk('local')->exists('question-imports/b.csv'))->toBeFalse()
        ->and(Question::count())->toBe(1);
});

it('gives admins Delete buttons in the student app and refuses everyone else', function () {
    $student = User::factory()->create();
    grantAccess($student, $this->exam->qbank_id);
    $chapter = TextbookChapter::create(['exam_id' => $this->exam->id, 'title' => 'Chapter one', 'body' => 'Text', 'is_published' => true]);
    $card = Flashcard::create(['exam_id' => $this->exam->id, 'front' => 'F', 'back' => 'B', 'is_published' => true]);
    $image = AtlasImage::create(['title' => 'Rash', 'image_path' => 'atlas/r.png', 'license' => 'cc-by']);
    $thread = QuestionComment::create(['question_id' => $this->question->id, 'user_id' => $student->id, 'body' => 'A comment']);
    $reply = QuestionComment::create(['question_id' => $this->question->id, 'user_id' => $student->id, 'parent_id' => $thread->id, 'body' => 'A reply']);

    foreach ([$student, $this->editor] as $user) {
        $this->actingAs($user);
        $this->delete('/staff/questions/'.$this->question->id)->assertForbidden();
        $this->delete('/staff/comments/'.$thread->id)->assertForbidden();
        $this->delete('/staff/textbooks/'.$chapter->slug)->assertForbidden();
        $this->delete('/staff/flashcards/'.$card->id)->assertForbidden();
        $this->delete('/staff/image-atlas/'.$image->id)->assertForbidden();
    }
    $this->actingAs($student)->get('/dashboard')->assertInertia(fn ($page) => $page->where('auth.isAdmin', false));

    $this->actingAs($this->admin)->get('/dashboard')->assertInertia(fn ($page) => $page->where('auth.isAdmin', true));
    $this->delete('/staff/questions/'.$this->question->id)->assertRedirect();
    $this->delete('/staff/comments/'.$thread->id)->assertRedirect();
    $this->delete('/staff/textbooks/'.$chapter->slug)->assertRedirect('/textbooks');
    $this->delete('/staff/flashcards/'.$card->id)->assertRedirect();
    $this->delete('/staff/image-atlas/'.$image->id)->assertRedirect();

    expect($this->question->fresh()->trashed())->toBeTrue()
        ->and(QuestionComment::whereKey([$thread->id, $reply->id])->count())->toBe(0)
        ->and(TextbookChapter::count())->toBe(0)
        ->and(Flashcard::count())->toBe(0)
        ->and(AtlasImage::count())->toBe(0);
});
