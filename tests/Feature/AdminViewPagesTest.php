<?php

use App\Filament\Resources\AtlasImageResource;
use App\Filament\Resources\PastPaperResource;
use App\Filament\Resources\QuestionResource;
use App\Filament\Resources\TextbookChapterResource;
use App\Filament\Resources\UserResource;
use App\Models\AtlasImage;
use App\Models\Category;
use App\Models\Exam;
use App\Models\PastPaper;
use App\Models\Question;
use App\Models\TextbookChapter;
use App\Models\User;
use Database\Seeders\ExamSeeder;
use Database\Seeders\RoleSeeder;
use Filament\Facades\Filament;

beforeEach(function () {
    $this->seed([ExamSeeder::class, RoleSeeder::class]);
    $this->admin = User::factory()->create();
    $this->admin->assignRole('admin');
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    $this->exam = Exam::where('slug', 'mrcp-part-1')->firstOrFail();
    $this->category = Category::create(['exam_id' => $this->exam->id, 'name' => 'Cardiology', 'slug' => 'cardiology']);
});

it('shows an MCQ on its own page with the answer, explanation and references', function () {
    $paper = PastPaper::create(['exam_id' => $this->exam->id, 'title' => 'Cardio paper', 'slug' => 'cardio-paper', 'year' => 2024]);
    $question = Question::create([
        'exam_id' => $this->exam->id, 'category_id' => $this->category->id, 'past_paper_id' => $paper->id, 'code' => 'V-1',
        'body' => 'A man with **palpitations**', 'options' => ['A' => 'Adenosine', 'B' => 'Verapamil'], 'correct_option' => 'B',
        'explanation' => 'Rate control first', 'key_points' => 'Check the ECG', 'references' => [['source' => 'NICE', 'title' => 'Atrial fibrillation', 'year' => '2021']],
    ]);

    $this->actingAs($this->admin)->get(QuestionResource::getUrl('view', ['record' => $question]))->assertOk()
        ->assertSee('V-1')->assertSee('<strong>palpitations</strong>', false)->assertSee('B. Verapamil')
        ->assertSee('Rate control first')->assertSee('Check the ECG')->assertSee('Atrial fibrillation')
        ->assertSee('Cardio paper')->assertSee('Back to the list');
});

it('opens the View page from the MCQ list instead of a modal', function () {
    $question = Question::create(['exam_id' => $this->exam->id, 'category_id' => $this->category->id, 'code' => 'V-2', 'body' => 'Stem', 'options' => ['A' => 'One', 'B' => 'Two'], 'correct_option' => 'A']);

    expect(QuestionResource::hasPage('view'))->toBeTrue()
        ->and(QuestionResource::getUrl('view', ['record' => $question]))->toEndWith('/admin/questions/'.$question->id);
});

it('shows past papers, chapters, users and atlas images on their own pages', function () {
    $paper = PastPaper::create(['exam_id' => $this->exam->id, 'title' => 'Sept paper', 'slug' => 'sept-paper', 'year' => 2023, 'description' => 'The September sitting']);
    Question::create(['exam_id' => $this->exam->id, 'category_id' => $this->category->id, 'past_paper_id' => $paper->id, 'code' => 'P-1', 'body' => 'Stem', 'options' => ['A' => 'One', 'B' => 'Two'], 'correct_option' => 'A']);
    $chapter = TextbookChapter::create(['exam_id' => $this->exam->id, 'category_id' => $this->category->id, 'title' => 'Heart failure', 'summary' => 'Short summary', 'body' => 'Chapter **text**']);
    $student = User::factory()->create(['name' => 'Student One']);
    grantAccess($student, $this->exam->qbank_id);
    $image = AtlasImage::create(['title' => 'Janeway lesions', 'diagnosis' => 'Endocarditis', 'image_path' => 'atlas/x.jpg', 'license' => 'unknown']);

    $this->actingAs($this->admin);
    $this->get(PastPaperResource::getUrl('view', ['record' => $paper]))->assertOk()->assertSee('Sept paper')->assertSee('The September sitting')->assertSee('Cardiology (1)');
    $this->get(TextbookChapterResource::getUrl('view', ['record' => $chapter]))->assertOk()->assertSee('Heart failure')->assertSee('<strong>text</strong>', false);
    $this->get(UserResource::getUrl('view', ['record' => $student]))->assertOk()->assertSee('Student One')->assertSee('MRCPWorld 1');
    $this->get(AtlasImageResource::getUrl('view', ['record' => $image]))->assertOk()->assertSee('Janeway lesions')->assertSee('Endocarditis');
});

it('keeps the View pages closed to students', function () {
    $student = User::factory()->create();
    $student->assignRole('student');
    $chapter = TextbookChapter::create(['exam_id' => $this->exam->id, 'title' => 'Hidden', 'body' => 'Body']);

    $this->actingAs($student)->get(TextbookChapterResource::getUrl('view', ['record' => $chapter]))->assertForbidden();
});
