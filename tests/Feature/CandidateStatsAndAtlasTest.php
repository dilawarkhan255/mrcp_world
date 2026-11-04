<?php

use App\Filament\Resources\AtlasImageResource\Pages\ManageAtlasImages;
use App\Models\AtlasImage;
use App\Models\Category;
use App\Models\Exam;
use App\Models\PracticeSession;
use App\Models\Qbank;
use App\Models\Question;
use App\Models\User;
use App\Services\CandidateStatsService;
use App\Services\PracticeService;
use Database\Seeders\RoleSeeder;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    $this->qbank = Qbank::create(['name' => 'Qbank 1', 'slug' => 'qbank-1']);
    $this->exam = Exam::factory()->create(['qbank_id' => $this->qbank->id]);
    $this->category = Category::factory()->create(['exam_id' => $this->exam->id, 'name' => 'Renal']);
    $this->question = Question::create([
        'exam_id' => $this->exam->id, 'category_id' => $this->category->id, 'code' => 'STAT-1', 'body' => 'Stem',
        'options' => ['A' => 'One', 'B' => 'Two', 'C' => 'Three'], 'correct_option' => 'A', 'is_published' => true,
    ]);
    Cache::forget(CandidateStatsService::WATERMARK);
});

/** Six students answer: four choose A (correct), one B and one C. */
function answerByCandidates(Question $question, Exam $exam): void
{
    $practice = app(PracticeService::class);
    foreach (['A', 'A', 'A', 'A', 'B', 'C'] as $index => $option) {
        $session = $practice->start(User::factory()->create(), $exam, 1, null, null);
        $practice->answer($session, 1, $option, 30 + $index * 10);
    }
}

it('aggregates candidate correct %, option spread, average time and the topic average', function () {
    answerByCandidates($this->question, $this->exam);

    expect(app(CandidateStatsService::class)->refresh())->toBe(1);
    $stats = app(CandidateStatsService::class)->forQuestion($this->question->id);

    expect($stats)->toBe([
        'attempts' => 6, 'correct_percent' => 67, 'average_seconds' => 55,
        'options' => ['A' => 67, 'B' => 17, 'C' => 17], 'topic_percent' => 67,
    ]);
});

it('recomputes instead of adding, so repeated refreshes never double count', function () {
    answerByCandidates($this->question, $this->exam);
    $service = app(CandidateStatsService::class);
    $service->refresh();
    $service->refresh();
    $service->refresh(full: true);

    expect($service->forQuestion($this->question->id)['attempts'])->toBe(6);
    $this->assertDatabaseHas('category_stats', ['category_id' => $this->category->id, 'attempts' => 6, 'correct' => 4]);
});

it('hides candidate figures until enough students answered and runs from artisan', function () {
    $practice = app(PracticeService::class);
    $session = $practice->start(User::factory()->create(), $this->exam, 1, null, null);
    $practice->answer($session, 1, 'A');
    $this->artisan('stats:refresh')->expectsOutputToContain('refreshed for 1 questions')->assertSuccessful();

    expect(app(CandidateStatsService::class)->forQuestion($this->question->id))->toBeNull();
});

it('shows candidate figures on the practice result', function () {
    answerByCandidates($this->question, $this->exam);
    app(CandidateStatsService::class)->refresh();
    $student = User::factory()->create();
    grantAccess($student, $this->qbank);
    $this->actingAs($student)->post('/questions/sessions', ['count' => 1]);
    $session = PracticeSession::where('user_id', $student->id)->firstOrFail();
    $this->post('/questions/sessions/'.$session->id.'/answer/1', ['option' => 'A', 'seconds' => 20]);

    $this->get('/questions/sessions/'.$session->id.'?position=1')->assertInertia(fn (Assert $page) => $page
        ->where('question.result.candidates.correct_percent', 67)
        ->where('question.result.candidates.topic_percent', 67));
});

it('never reveals correctness in the grid of an open mock', function () {
    Question::create([
        'exam_id' => $this->exam->id, 'category_id' => $this->category->id, 'code' => 'STAT-2', 'body' => 'Second',
        'options' => ['A' => 'One', 'B' => 'Two'], 'correct_option' => 'A', 'is_published' => true,
    ]);
    $student = User::factory()->create();
    grantAccess($student, $this->qbank);
    $this->actingAs($student)->post('/questions/mocks', ['count' => 2, 'minutes' => 10]);
    $session = PracticeSession::where('user_id', $student->id)->firstOrFail();
    $this->post('/questions/mocks/'.$session->id.'/answer/1', ['option' => 'B']);

    $this->get('/questions/mocks/'.$session->id)->assertInertia(fn (Assert $page) => $page
        ->where('navigator.0.answered', true)->where('navigator.0.correct', null)->where('navigator.1.correct', null));
    $this->post('/questions/mocks/'.$session->id.'/finish');
    $this->get('/questions/mocks/'.$session->id)->assertInertia(fn (Assert $page) => $page->where('navigator.0.correct', fn ($value) => is_bool($value))->where('navigator.1.correct', null));
});

it('refuses to publish an atlas image without a copyright confirmation', function () {
    expect(fn () => AtlasImage::create(['title' => 'Psoriasis', 'image_path' => 'atlas/a.png', 'license' => 'cc-by', 'is_published' => true]))
        ->toThrow(ValidationException::class);
    expect(fn () => AtlasImage::create(['title' => 'Psoriasis', 'image_path' => 'atlas/a.png', 'license' => 'unknown', 'copyright_confirmed' => true, 'is_published' => true]))
        ->toThrow(ValidationException::class);
    expect(fn () => AtlasImage::create(['title' => 'Bad path', 'image_path' => '../etc/passwd', 'license' => 'own']))->toThrow(ValidationException::class);

    $this->actingAs($admin = User::factory()->create());
    $image = AtlasImage::create(['title' => 'Psoriasis', 'image_path' => 'atlas/a.png', 'license' => 'own', 'copyright_confirmed' => true, 'is_published' => true]);
    expect($image->slug)->toBe('psoriasis')->and($image->confirmed_by)->toBe($admin->id)->and($image->confirmed_at)->not->toBeNull();
    expect(AtlasImage::create(['title' => 'Psoriasis', 'image_path' => 'atlas/b.png', 'license' => 'own'])->slug)->toBe('psoriasis-2');
});

it('shows students only confirmed, published images of their Qbank, with search', function () {
    $otherQbank = Qbank::create(['name' => 'Qbank 2', 'slug' => 'qbank-2']);
    $live = ['license' => 'own', 'copyright_confirmed' => true, 'is_published' => true];
    AtlasImage::create(['title' => 'Erythema nodosum', 'diagnosis' => 'Sarcoidosis', 'subject' => 'Dermatology', 'image_path' => 'atlas/1.png', ...$live]);
    AtlasImage::create(['title' => 'Bilateral hilar lymphadenopathy', 'subject' => 'Respiratory', 'image_path' => 'atlas/2.png', 'qbank_id' => $this->qbank->id, ...$live]);
    AtlasImage::create(['title' => 'Other bank image', 'image_path' => 'atlas/3.png', 'qbank_id' => $otherQbank->id, ...$live]);
    AtlasImage::create(['title' => 'Draft image', 'image_path' => 'atlas/4.png', 'license' => 'own']);
    $student = User::factory()->create();
    grantAccess($student, $this->qbank);
    $this->actingAs($student)->post('/exam', ['exam_id' => $this->exam->id]);

    $this->get('/image-atlas')->assertInertia(fn (Assert $page) => $page
        ->component('atlas/Index')
        ->has('images.data', 2)
        ->where('subjects', ['Dermatology', 'Respiratory']));
    $this->get('/image-atlas?q=sarco')->assertInertia(fn (Assert $page) => $page->has('images.data', 1)->where('images.data.0.title', 'Erythema nodosum'));
    $this->get('/image-atlas?subject=Respiratory')->assertInertia(fn (Assert $page) => $page->has('images.data', 1));
    $this->get('/image-atlas?q=100%25')->assertInertia(fn (Assert $page) => $page->has('images.data', 0));
});

it('lets editors manage the atlas and bulk publish only confirmed images', function () {
    Storage::fake('public');
    $editor = User::factory()->create();
    $editor->assignRole('editor');
    $this->actingAs($editor);
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    $ready = AtlasImage::create(['title' => 'Ready', 'image_path' => 'atlas/r.png', 'license' => 'own', 'copyright_confirmed' => true]);
    $pending = AtlasImage::create(['title' => 'Pending', 'image_path' => 'atlas/p.png', 'license' => 'unknown']);

    $this->get('/admin/atlas-images')->assertOk()->assertSee('Ready');
    Livewire::test(ManageAtlasImages::class)->callTableBulkAction('publish', [$ready, $pending]);

    expect($ready->refresh()->is_published)->toBeTrue()->and($pending->refresh()->is_published)->toBeFalse();
    $student = User::factory()->create();
    $student->assignRole('student');
    $this->actingAs($student)->get('/admin/atlas-images')->assertForbidden();
});

it('shares the configured community links and hides missing ones', function () {
    config(['services.community.whatsapp_url' => 'https://chat.whatsapp.com/example', 'services.community.play_store_url' => null]);

    $this->get('/')->assertInertia(fn (Assert $page) => $page
        ->where('links.whatsapp', 'https://chat.whatsapp.com/example')
        ->where('links.play_store', null));
});
