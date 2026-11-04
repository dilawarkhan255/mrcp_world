<?php

use App\Filament\Resources\FeedbackResource\Pages\ListFeedback;
use App\Models\Exam;
use App\Models\Feedback;
use App\Models\Plan;
use App\Models\Qbank;
use App\Models\User;
use Database\Seeders\ExamSeeder;
use Database\Seeders\RoleSeeder;
use Filament\Facades\Filament;
use Inertia\Testing\AssertableInertia as Assert;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed([ExamSeeder::class, RoleSeeder::class]);
    $this->exam = Exam::orderBy('id')->firstOrFail();
    $this->student = User::factory()->create();
});

it('lists each Qbank with its active plans', function () {
    $qbank = Qbank::where('slug', 'qbank-1')->firstOrFail();
    Plan::create(['qbank_id' => $qbank->id, 'name' => 'Retired', 'duration_days' => 30, 'price' => 900, 'currency' => 'PKR', 'is_active' => false]);
    Plan::create(['qbank_id' => $qbank->id, 'name' => '6 months', 'duration_days' => 180, 'price' => 8, 'currency' => 'GBP', 'is_active' => true]);

    $this->actingAs($this->student)->get('/subscription')
        ->assertInertia(fn (Assert $page) => $page
            ->component('Subscription')
            ->has('qbanks', 2)
            ->where('qbanks.0.name', 'MRCPWorld 1 Qbank')
            ->where('qbanks.0.exams', ['MRCP 1 UK/Irish', 'IMM'])
            ->where('qbanks.0.is_selected', true)
            ->where('qbanks.0.has_access', false)
            ->has('qbanks.0.plans', 1)
            ->where('qbanks.0.plans.0.price', '£8')
            ->where('qbanks.0.plans.0.duration', '6 months'));
});

it('validates feedback type and message length', function () {
    $this->actingAs($this->student)->withSession(['exam_id' => $this->exam->id])
        ->post('/feedback', ['type' => 'unknown', 'message' => 'short'])
        ->assertSessionHasErrors(['type', 'message']);

    expect(Feedback::count())->toBe(0);
});

it('saves feedback against the selected exam and shows its status', function () {
    $this->actingAs($this->student)->withSession(['exam_id' => $this->exam->id])
        ->post('/feedback', ['type' => 'content', 'message' => 'Option C of the renal question looks wrong.'])
        ->assertRedirect();

    $feedback = Feedback::firstOrFail();
    expect($feedback->user_id)->toBe($this->student->id)
        ->and($feedback->exam_id)->toBe($this->exam->id)
        ->and($feedback->type)->toBe('content')
        ->and($feedback->status)->toBe(Feedback::STATUS_NEW);

    $this->get('/feedback')
        ->assertInertia(fn (Assert $page) => $page
            ->component('Feedback')
            ->has('items', 1)
            ->where('items.0.status', 'new')
            ->where('items.0.type', 'MCQ or explanation error'));
});

it('shows a student only their own feedback', function () {
    Feedback::create(['user_id' => $this->student->id, 'exam_id' => $this->exam->id, 'type' => 'bug', 'message' => 'Mine to see here.', 'status' => 'new']);
    Feedback::create(['user_id' => User::factory()->create()->id, 'exam_id' => $this->exam->id, 'type' => 'bug', 'message' => 'Somebody else.', 'status' => 'new']);

    $this->actingAs($this->student)->get('/feedback')
        ->assertInertia(fn (Assert $page) => $page->has('items', 1)->where('items.0.message', 'Mine to see here.'));
});

it('keeps students out of the admin feedback list', function () {
    $this->actingAs($this->student)->get('/admin/feedback')->assertStatus(403);
});

it('lets an editor mark feedback reviewed but not delete it', function () {
    $editor = User::factory()->create();
    $editor->assignRole('editor');
    $feedback = Feedback::create(['user_id' => $this->student->id, 'exam_id' => $this->exam->id, 'type' => 'other', 'message' => 'Please review this.', 'status' => 'new']);

    $this->actingAs($editor);
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    Livewire::test(ListFeedback::class)
        ->assertTableActionHidden('delete', $feedback)
        ->callTableAction('markReviewed', $feedback)
        ->assertHasNoTableActionErrors();

    expect($feedback->fresh()->status)->toBe(Feedback::STATUS_REVIEWED);
});
