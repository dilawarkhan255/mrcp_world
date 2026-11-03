<?php

use App\Filament\Resources\FeedbackResource\Pages\ListFeedback;
use App\Models\Exam;
use App\Models\Feedback;
use App\Models\Plan;
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

it('lists only active plans ordered by sort order and duration', function () {
    Plan::create(['name' => 'Annual', 'duration_days' => 365, 'price' => 15000, 'currency' => 'PKR', 'is_active' => true, 'sort_order' => 2]);
    Plan::create(['name' => 'Monthly', 'duration_days' => 30, 'price' => 1500, 'currency' => 'PKR', 'is_active' => true, 'sort_order' => 1]);
    Plan::create(['name' => 'Retired', 'duration_days' => 30, 'price' => 900, 'currency' => 'PKR', 'is_active' => false, 'sort_order' => 0]);

    $this->actingAs($this->student)->get('/subscription')
        ->assertInertia(fn (Assert $page) => $page
            ->component('Subscription')
            ->has('plans', 2)
            ->where('plans.0.name', 'Monthly')
            ->where('plans.0.price', 'PKR 1,500')
            ->where('plans.0.duration_days', 30)
            ->where('plans.1.name', 'Annual'));
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
