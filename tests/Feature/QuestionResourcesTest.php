<?php

use App\Filament\Resources\QuestionResource\Pages\ManageQuestions;
use App\Models\AtlasImage;
use App\Models\Category;
use App\Models\Exam;
use App\Models\PracticeSession;
use App\Models\Question;
use App\Models\TextbookChapter;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Filament\Facades\Filament;
use Inertia\Testing\AssertableInertia as Assert;
use Livewire\Livewire;

function resourceQuestion(Exam $exam, Category $category, array $overrides = []): Question
{
    return Question::create(array_merge([
        'exam_id' => $exam->id, 'category_id' => $category->id, 'code' => fake()->unique()->bothify('R-########'),
        'body' => 'A patient with a rash', 'options' => ['A' => 'Psoriasis', 'B' => 'Eczema'], 'correct_option' => 'A',
        'explanation' => 'Explanation', 'is_published' => true,
    ], $overrides));
}

function atlasImage(string $title, array $overrides = []): AtlasImage
{
    return AtlasImage::create(array_merge([
        'title' => $title, 'image_path' => 'atlas/'.str($title)->slug().'.png', 'license' => 'own',
        'copyright_confirmed' => true, 'is_published' => true,
    ], $overrides));
}

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    $this->exam = Exam::factory()->create();
    $this->category = Category::factory()->create(['exam_id' => $this->exam->id, 'name' => 'Dermatology', 'slug' => 'dermatology']);
    $this->student = User::factory()->create();
});

/** Starts a one-question practice session and returns its URL. */
function practiseOnly(Question $question, User $student, Exam $exam): string
{
    test()->actingAs($student)->withSession(['exam_id' => $exam->id])->post('/questions/sessions', ['count' => 1])->assertRedirect();

    return '/questions/sessions/'.PracticeSession::latest('id')->firstOrFail()->id;
}

it('keeps the resources out of an unanswered question and shows the linked ones after the answer', function () {
    $question = resourceQuestion($this->exam, $this->category, [
        'image_search' => 'psoriasis plaques',
        'references' => [['source' => 'NICE', 'title' => 'Psoriasis: assessment and management', 'url' => 'https://www.nice.org.uk/guidance/cg153', 'year' => '2012']],
        'youtube_url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
    ]);
    $chapter = TextbookChapter::create(['exam_id' => $this->exam->id, 'category_id' => $this->category->id, 'title' => 'Psoriasis', 'body' => 'Text', 'is_published' => true]);
    TextbookChapter::create(['exam_id' => $this->exam->id, 'category_id' => $this->category->id, 'title' => 'Draft chapter', 'body' => 'Text', 'is_published' => false]);
    $image = atlasImage('Plaque psoriasis', ['diagnosis' => 'Psoriasis']);
    $hidden = atlasImage('Unpublished image', ['is_published' => false]);
    $question->textbookChapters()->attach($chapter->id, ['sort_order' => 1]);
    $question->atlasImages()->attach([$image->id => ['sort_order' => 1], $hidden->id => ['sort_order' => 2]]);

    $url = practiseOnly($question, $this->student, $this->exam);
    // Before the answer: nothing that could give the diagnosis away.
    $this->get($url)->assertInertia(fn (Assert $page) => $page->missing('question.result'));
    expect(json_encode($this->get($url)->viewData('page')))->not->toContain('Plaque psoriasis')->not->toContain('cg153');

    $this->post($url.'/answer/1', ['option' => 'B']);
    $this->get($url)->assertInertia(fn (Assert $page) => $page
        ->where('question.result.resources.textbook.linked', true)
        ->has('question.result.resources.textbook.items', 1)
        ->where('question.result.resources.textbook.items.0.title', 'Psoriasis')
        ->where('question.result.resources.textbook.items.0.url', '/textbooks/'.$chapter->slug)
        ->where('question.result.resources.atlas.linked', true)
        ->has('question.result.resources.atlas.items', 1)
        ->where('question.result.resources.atlas.items.0.title', 'Plaque psoriasis')
        ->where('question.result.resources.web.google_images', 'https://www.google.com/search?tbm=isch&q=psoriasis%20plaques')
        ->where('question.result.resources.web.youtube', 'https://www.youtube.com/results?search_query=psoriasis%20plaques')
        ->where('question.result.references.0.url', 'https://www.nice.org.uk/guidance/cg153')
        ->where('question.result.youtube_id', 'dQw4w9WgXcQ'));
});

it('gives every question its own resources and falls back to the subject and the search term', function () {
    $first = resourceQuestion($this->exam, $this->category, ['image_search' => 'erythema nodosum']);
    $other = Category::factory()->create(['exam_id' => $this->exam->id, 'name' => 'Cardiology', 'slug' => 'cardiology']);
    TextbookChapter::create(['exam_id' => $this->exam->id, 'category_id' => $this->category->id, 'title' => 'Skin basics', 'body' => 'Text', 'is_published' => true]);
    TextbookChapter::create(['exam_id' => $this->exam->id, 'category_id' => $other->id, 'title' => 'Heart failure', 'body' => 'Text', 'is_published' => true]);
    atlasImage('Erythema nodosum on the shins');
    atlasImage('Atrial fibrillation ECG');

    $url = practiseOnly($first, $this->student, $this->exam);
    $this->post($url.'/answer/1', ['option' => 'A']);
    $this->get($url)->assertInertia(fn (Assert $page) => $page
        ->where('question.result.resources.textbook.linked', false)
        ->where('question.result.resources.textbook.subject', 'Dermatology')
        ->has('question.result.resources.textbook.items', 1)
        ->where('question.result.resources.textbook.items.0.title', 'Skin basics')
        ->where('question.result.resources.atlas.linked', false)
        ->has('question.result.resources.atlas.items', 1)
        ->where('question.result.resources.atlas.items.0.title', 'Erythema nodosum on the shins'));

    // A question without a search term gets no invented search links.
    $first->delete();
    $second = resourceQuestion($this->exam, $other);
    $url = practiseOnly($second, $this->student, $this->exam);
    $this->post($url.'/answer/1', ['option' => 'A']);
    $this->get($url)->assertInertia(fn (Assert $page) => $page
        ->where('question.result.resources.textbook.items.0.title', 'Heart failure')
        ->has('question.result.resources.atlas.items', 0)
        ->where('question.result.resources.web.google_images', null)
        ->where('question.result.resources.web.youtube', null));
});

it('shows no resources during an open mock', function () {
    $question = resourceQuestion($this->exam, $this->category, ['image_search' => 'psoriasis']);
    $question->atlasImages()->attach(atlasImage('Psoriasis elbow')->id);
    resourceQuestion($this->exam, $this->category);
    // Two questions: answering the last one would close the mock.
    $this->actingAs($this->student)->withSession(['exam_id' => $this->exam->id])->post('/questions/mocks', ['count' => 2, 'minutes' => 10])->assertRedirect();
    $mock = PracticeSession::where('mode', PracticeSession::MODE_MOCK)->firstOrFail();
    $position = $mock->items()->where('question_id', $question->id)->value('position');
    $this->post('/questions/mocks/'.$mock->id.'/answer/'.$position, ['option' => 'A']);

    expect($mock->fresh()->completed_at)->toBeNull();
    $this->get('/questions/mocks/'.$mock->id.'?position='.$position)->assertInertia(fn (Assert $page) => $page->missing('question.result'));
    $this->post('/questions/mocks/'.$mock->id.'/finish');
    $this->get('/questions/mocks/'.$mock->id.'?position='.$position)->assertInertia(fn (Assert $page) => $page
        ->where('question.result.resources.atlas.items.0.title', 'Psoriasis elbow'));
});

it('lets an editor link textbook chapters and atlas images to an MCQ', function () {
    $editor = User::factory()->create();
    $editor->assignRole('editor');
    $question = resourceQuestion($this->exam, $this->category);
    $chapter = TextbookChapter::create(['exam_id' => $this->exam->id, 'category_id' => $this->category->id, 'title' => 'Psoriasis', 'body' => 'Text', 'is_published' => true]);
    $image = atlasImage('Nail pitting');
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    $this->actingAs($editor);

    Livewire::test(ManageQuestions::class)
        ->mountTableAction('edit', $question)
        ->setTableActionData(['textbookChapters' => [$chapter->id], 'atlasImages' => [$image->id]])
        ->callMountedTableAction()
        ->assertHasNoTableActionErrors();

    expect($question->textbookChapters()->pluck('textbook_chapters.id')->all())->toBe([$chapter->id])
        ->and($question->atlasImages()->pluck('atlas_images.id')->all())->toBe([$image->id]);
});

it('removes the links when a question is deleted for good', function () {
    $question = resourceQuestion($this->exam, $this->category);
    $question->atlasImages()->attach(atlasImage('Guttate psoriasis')->id);
    $question->forceDelete();
    expect(DB::table('atlas_image_question')->count())->toBe(0);
});
