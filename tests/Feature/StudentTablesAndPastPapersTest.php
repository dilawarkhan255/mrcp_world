<?php

use App\Filament\Resources\PastPaperResource\Pages\ManagePastPapers;
use App\Models\Exam;
use App\Models\PastPaper;
use App\Models\PracticeSession;
use App\Models\Qbank;
use App\Models\User;
use Database\Seeders\ExamSeeder;
use Database\Seeders\PlanSeeder;
use Database\Seeders\RoleSeeder;
use Filament\Facades\Filament;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;
use Livewire\Livewire;

function tableSession(User $user, Exam $exam, array $overrides = []): PracticeSession
{
    return PracticeSession::create(array_merge([
        'user_id' => $user->id, 'exam_id' => $exam->id, 'mode' => PracticeSession::MODE_PRACTICE,
        'total_questions' => 10, 'answered_count' => 4, 'correct_count' => 3,
    ], $overrides));
}

beforeEach(function () {
    $this->seed([ExamSeeder::class, PlanSeeder::class, RoleSeeder::class]);
    $this->mrcp1 = Exam::where('slug', 'mrcp-part-1')->firstOrFail();
    $this->mrcp2 = Exam::where('slug', 'mrcp-part-2-written')->firstOrFail();
    $this->student = User::factory()->create();
    $this->student->assignRole('student');
    $this->admin = User::factory()->create();
    $this->admin->assignRole('admin');
});

it('lists only the signed-in student sessions of the selected exam in the Yajra table', function () {
    grantAccess($this->student, $this->mrcp1->qbank_id);
    $mine = tableSession($this->student, $this->mrcp1);
    $mock = tableSession($this->student, $this->mrcp1, ['mode' => PracticeSession::MODE_MOCK, 'completed_at' => now()]);
    tableSession($this->student, $this->mrcp2);
    tableSession(User::factory()->create(), $this->mrcp1);

    $this->actingAs($this->student)->withSession(['exam_id' => $this->mrcp1->id])
        ->getJson('/questions/sessions/data?draw=1&start=0&length=10&order[0][column]=0&order[0][dir]=desc&columns[0][data]=id&columns[0][name]=id&columns[0][orderable]=true')
        ->assertOk()
        ->assertJsonPath('recordsTotal', 2)
        ->assertJsonPath('data.0.id', $mock->id)
        ->assertJsonPath('data.0.kind', 'Mock')
        ->assertJsonPath('data.0.status', 'Completed')
        ->assertJsonPath('data.1.id', $mine->id)
        ->assertJsonPath('data.1.accuracy', 75)
        ->assertJsonPath('data.1.status', 'In progress')
        ->assertJsonMissingPath('data.0.user_id');
});

it('searches the sessions table by session number', function () {
    grantAccess($this->student, $this->mrcp1->qbank_id);
    $wanted = tableSession($this->student, $this->mrcp1);
    tableSession($this->student, $this->mrcp1);

    $this->actingAs($this->student)->withSession(['exam_id' => $this->mrcp1->id])
        ->getJson('/questions/sessions/data?draw=1&start=0&length=10&search[value]=%23'.$wanted->id)
        ->assertOk()->assertJsonPath('recordsFiltered', 1)->assertJsonPath('data.0.id', $wanted->id);
});

it('shows mock attempts with time used and keeps them behind the subscription', function () {
    $this->actingAs($this->student)->withSession(['exam_id' => $this->mrcp1->id])
        ->getJson('/questions/mocks/data?draw=1&start=0&length=10')->assertForbidden();

    grantAccess($this->student, Qbank::where('slug', 'qbank-1')->firstOrFail());
    tableSession($this->student, $this->mrcp1, [
        'mode' => PracticeSession::MODE_MOCK, 'duration_seconds' => 1800,
        'started_at' => now()->subHour(), 'completed_at' => now()->subMinutes(10), 'correct_count' => 5,
    ]);
    tableSession($this->student, $this->mrcp1);

    $this->getJson('/questions/mocks/data?draw=1&start=0&length=10')->assertOk()
        ->assertJsonPath('recordsTotal', 1)
        ->assertJsonPath('data.0.minutes', 30)
        ->assertJsonPath('data.0.score', 50);
});

it('uploads a cover image and sitting for a past paper and shows them to students', function () {
    Storage::fake('public');
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    $this->actingAs($this->admin);

    Livewire::test(ManagePastPapers::class)
        ->callAction('create', [
            'exam_id' => $this->mrcp1->id, 'title' => 'January 2026 paper', 'slug' => 'january-2026-paper', 'year' => 2026,
            'sitting' => 'January', 'description' => 'Recalled questions from the January diet.',
            'image_path' => UploadedFile::fake()->image('cover.jpg', 800, 450),
            'is_published' => true,
        ])
        ->assertHasNoActionErrors();

    $paper = PastPaper::where('slug', 'january-2026-paper')->firstOrFail();
    expect($paper->sitting)->toBe('January')->and($paper->image_path)->toStartWith(PastPaper::IMAGE_DIRECTORY.'/');
    Storage::disk('public')->assertExists((string) $paper->image_path);

    grantAccess($this->student, Qbank::where('slug', 'qbank-1')->firstOrFail());
    $this->actingAs($this->student)->withSession(['exam_id' => $this->mrcp1->id])
        ->get('/questions/past-papers')
        ->assertInertia(fn (Assert $page) => $page
            ->where('papers.data.0.sitting', 'January')
            ->where('papers.data.0.description', 'Recalled questions from the January diet.')
            ->where('papers.data.0.image_url', $paper->imageUrl()));
});

it('refuses a past paper image path outside the cover folder', function () {
    expect(fn () => PastPaper::create(['exam_id' => $this->mrcp1->id, 'title' => 'Paper', 'slug' => 'paper', 'year' => 2025, 'image_path' => 'past-papers/../../.env']))
        ->toThrow(ValidationException::class);
    expect(PastPaper::where('slug', 'paper')->exists())->toBeFalse();
});

it('shows the sidebar toggle in the admin top bar on every screen size', function () {
    $this->actingAs($this->admin)->get('/admin')->assertOk()
        ->assertSee('mw-topbar-toggle', false)
        ->assertSee('.mw-topbar-btn.mw-topbar-toggle', false);
});
