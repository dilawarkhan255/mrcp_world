<?php

use App\Models\Category;
use App\Models\Exam;
use App\Models\Question;
use App\Models\QuestionComment;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Inertia\Testing\AssertableInertia as Assert;

function commentQuestion(Exam $exam, Category $category, array $overrides = []): Question
{
    return Question::create(array_merge([
        'exam_id' => $exam->id, 'category_id' => $category->id, 'code' => fake()->unique()->bothify('C-########'),
        'body' => 'Commented question text', 'options' => ['A' => 'First option', 'B' => 'Second option'],
        'correct_option' => 'A', 'explanation' => 'Commented explanation', 'is_published' => true,
    ], $overrides));
}

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    $this->exam = Exam::factory()->create();
    $this->category = Category::factory()->create(['exam_id' => $this->exam->id, 'slug' => 'general']);
    $this->question = commentQuestion($this->exam, $this->category);
    $this->student = User::factory()->create();
    $this->editor = User::factory()->create();
    $this->editor->assignRole('editor');
});

it('lets a student start a thread and reply to it', function () {
    $this->actingAs($this->student)->withSession(['exam_id' => $this->exam->id]);
    $this->post('/questions/comments', ['question_id' => $this->question->id, 'body' => 'Is this guideline current?'])
        ->assertRedirect();
    $comment = QuestionComment::firstOrFail();
    expect($comment->user_id)->toBe($this->student->id)->and($comment->parent_id)->toBeNull();
    $this->post('/questions/comments/'.$comment->id.'/reply', ['body' => 'The 2023 version applies.'])->assertRedirect();
    expect(QuestionComment::count())->toBe(2);
    $this->get('/questions/comments')->assertInertia(fn (Assert $page) => $page
        ->has('threads.data', 1)
        ->where('threads.data.0.body', 'Is this guideline current?')
        ->where('threads.data.0.author_role', 'Student')
        ->where('threads.data.0.question.code', $this->question->code)
        ->has('threads.data.0.replies', 1)
        ->where('canModerate', false)
    );
});

it('hides a thread from other students but keeps it for its author and for editors', function () {
    $this->actingAs($this->student)->withSession(['exam_id' => $this->exam->id]);
    $this->post('/questions/comments', ['question_id' => $this->question->id, 'body' => 'A reported statement'])->assertRedirect();
    $comment = QuestionComment::firstOrFail();
    $this->actingAs($this->editor)->withSession(['exam_id' => $this->exam->id]);
    $this->post('/questions/comments/'.$comment->id.'/moderate', ['action' => 'hide'])->assertRedirect();
    expect($comment->fresh()->is_hidden)->toBeTrue();
    $this->get('/questions/comments')->assertInertia(fn (Assert $page) => $page->has('threads.data', 1)->where('canModerate', true));
    $this->actingAs($this->student)->withSession(['exam_id' => $this->exam->id]);
    $this->get('/questions/comments')->assertInertia(fn (Assert $page) => $page->has('threads.data', 1)->where('threads.data.0.is_hidden', true));
    $other = User::factory()->create();
    $this->actingAs($other)->withSession(['exam_id' => $this->exam->id]);
    $this->get('/questions/comments')->assertInertia(fn (Assert $page) => $page->has('threads.data', 0));
});

it('refuses moderation and replies to students', function () {
    $this->actingAs($this->student)->withSession(['exam_id' => $this->exam->id]);
    $this->post('/questions/comments', ['question_id' => $this->question->id, 'body' => 'A student thread'])->assertRedirect();
    $comment = QuestionComment::firstOrFail();
    $this->post('/questions/comments/'.$comment->id.'/moderate', ['action' => 'hide'])->assertForbidden();
    expect($comment->fresh()->is_hidden)->toBeFalse();
});

it('rejects comments on unpublished questions and on another exam', function () {
    $unpublished = commentQuestion($this->exam, $this->category, ['is_published' => false]);
    $otherExam = Exam::factory()->create();
    $otherCategory = Category::factory()->create(['exam_id' => $otherExam->id, 'slug' => 'other']);
    $otherQuestion = commentQuestion($otherExam, $otherCategory);
    $this->actingAs($this->student)->withSession(['exam_id' => $this->exam->id]);
    $this->post('/questions/comments', ['question_id' => $otherQuestion->id, 'body' => 'Wrong exam thread'])->assertSessionHasErrors('question_id');
    $this->post('/questions/comments', ['question_id' => $unpublished->id, 'body' => 'Unpublished thread'])->assertSessionHasErrors('question_id');
    $this->post('/questions/comments', ['question_id' => $this->question->id, 'body' => 'x'])->assertSessionHasErrors('body');
    expect(QuestionComment::count())->toBe(0);
});

it('searches only published questions of the selected exam', function () {
    commentQuestion($this->exam, $this->category, ['code' => 'CARD-100', 'body' => 'Searchable question']);
    $otherExam = Exam::factory()->create();
    $otherCategory = Category::factory()->create(['exam_id' => $otherExam->id, 'slug' => 'other']);
    commentQuestion($otherExam, $otherCategory, ['code' => 'CARD-200', 'body' => 'Searchable question']);
    $this->actingAs($this->student)->withSession(['exam_id' => $this->exam->id]);
    $this->get('/questions/comments?q=CARD-100')->assertInertia(fn (Assert $page) => $page->has('questions', 1)->where('questions.0.code', 'CARD-100'));
    $this->get('/questions/comments?q=Searchable')->assertInertia(fn (Assert $page) => $page->has('questions', 1)->where('questions.0.code', 'CARD-100'));
});
