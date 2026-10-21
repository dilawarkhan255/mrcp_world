<?php

use App\Models\Bookmark;
use App\Models\Category;
use App\Models\Exam;
use App\Models\PracticeSession;
use App\Models\Question;
use App\Models\User;

function bookmarkQuestion(Exam $exam, Category $category, array $overrides = []): Question
{
    return Question::create(array_merge([
        'exam_id' => $exam->id, 'category_id' => $category->id, 'code' => fake()->unique()->bothify('B-########'),
        'body' => 'Bookmark question text', 'options' => ['A' => 'First option', 'B' => 'Second option'],
        'correct_option' => 'A', 'explanation' => 'Bookmark explanation', 'is_published' => true,
    ], $overrides));
}

beforeEach(function () {
    $this->exam = Exam::factory()->create();
    $this->category = Category::factory()->create(['exam_id' => $this->exam->id, 'slug' => 'general', 'name' => 'General']);
    $this->question = bookmarkQuestion($this->exam, $this->category);
    $this->user = User::factory()->create();
});

it('saves a question as a bookmark and lists it with its note', function () {
    $this->actingAs($this->user)->withSession(['exam_id' => $this->exam->id]);
    $this->post('/revision/bookmarks', ['question_id' => $this->question->id, 'note' => 'Read the guidelines again'])
        ->assertRedirect();
    expect(Bookmark::count())->toBe(1);
    $this->get('/revision/bookmarks')->assertInertia(fn ($page) => $page
        ->has('bookmarks.data', 1)
        ->where('bookmarks.data.0.question_id', $this->question->id)
        ->where('bookmarks.data.0.code', $this->question->code)
        ->where('bookmarks.data.0.category', 'General')
        ->where('bookmarks.data.0.note', 'Read the guidelines again')
        ->where('stats.total', 1)
        ->where('stats.withNotes', 1)
    );
});

it('keeps one bookmark per question and updates the note', function () {
    $this->actingAs($this->user)->withSession(['exam_id' => $this->exam->id]);
    $this->post('/revision/bookmarks', ['question_id' => $this->question->id, 'note' => 'First note'])->assertRedirect();
    $this->post('/revision/bookmarks', ['question_id' => $this->question->id, 'note' => 'Second note'])->assertRedirect();
    expect(Bookmark::count())->toBe(1);
    expect(Bookmark::firstOrFail()->note)->toBe('Second note');
    $bookmark = Bookmark::firstOrFail();
    $this->patch('/revision/bookmarks/'.$bookmark->id, ['note' => 'Third note'])->assertRedirect();
    expect($bookmark->fresh()->note)->toBe('Third note');
    $this->patch('/revision/bookmarks/'.$bookmark->id, ['note' => null])->assertRedirect();
    expect($bookmark->fresh()->note)->toBeNull();
});

it('removes a bookmark', function () {
    $this->actingAs($this->user)->withSession(['exam_id' => $this->exam->id]);
    $this->post('/revision/bookmarks', ['question_id' => $this->question->id])->assertRedirect();
    $bookmark = Bookmark::firstOrFail();
    $this->delete('/revision/bookmarks/'.$bookmark->id)->assertRedirect();
    expect(Bookmark::count())->toBe(0);
});

it('practises the bookmarked questions', function () {
    $second = bookmarkQuestion($this->exam, $this->category);
    $this->actingAs($this->user)->withSession(['exam_id' => $this->exam->id]);
    $this->post('/revision/bookmarks', ['question_id' => $this->question->id])->assertRedirect();
    $this->post('/revision/bookmarks', ['question_id' => $second->id])->assertRedirect();
    $this->post('/revision/bookmarks/practice')->assertRedirect();
    $session = PracticeSession::where('mode', PracticeSession::MODE_REVIEW)->firstOrFail();
    expect($session->total_questions)->toBe(2)->and($session->user_id)->toBe($this->user->id);
});

it('refuses to read or change the bookmarks of another student', function () {
    $owner = User::factory()->create();
    $bookmark = Bookmark::create(['user_id' => $owner->id, 'question_id' => $this->question->id]);
    $this->actingAs(User::factory()->create())->withSession(['exam_id' => $this->exam->id]);
    $this->patch('/revision/bookmarks/'.$bookmark->id, ['note' => 'Not yours'])->assertForbidden();
    $this->delete('/revision/bookmarks/'.$bookmark->id)->assertForbidden();
    expect($bookmark->fresh())->not->toBeNull();
});

it('rejects a bookmark of an unpublished question or a question from another exam', function () {
    $unpublished = bookmarkQuestion($this->exam, $this->category, ['is_published' => false]);
    $otherExam = Exam::factory()->create();
    $otherCategory = Category::factory()->create(['exam_id' => $otherExam->id, 'slug' => 'other']);
    $otherQuestion = bookmarkQuestion($otherExam, $otherCategory);
    $this->actingAs($this->user)->withSession(['exam_id' => $this->exam->id]);
    $this->post('/revision/bookmarks', ['question_id' => $unpublished->id])->assertSessionHasErrors('question_id');
    $this->post('/revision/bookmarks', ['question_id' => $otherQuestion->id])->assertSessionHasErrors('question_id');
    expect(Bookmark::count())->toBe(0);
});

it('reports an empty list instead of failing when there is nothing to practise', function () {
    $this->actingAs($this->user)->withSession(['exam_id' => $this->exam->id]);
    $this->post('/revision/bookmarks/practice')->assertSessionHasErrors('question_ids');
    $this->get('/revision/bookmarks')->assertInertia(fn ($page) => $page->has('bookmarks.data', 0)->where('stats.total', 0));
});

it('shows the bookmark state of the question in the practice view', function () {
    $this->actingAs($this->user)->withSession(['exam_id' => $this->exam->id]);
    $this->post('/questions/sessions', ['count' => 5])->assertRedirect();
    $session = PracticeSession::firstOrFail();
    $this->get('/questions/sessions/'.$session->id)->assertInertia(fn ($page) => $page
        ->where('question.question_id', $this->question->id)
        ->where('question.is_bookmarked', false)
    );
    $this->post('/revision/bookmarks', ['question_id' => $this->question->id])->assertRedirect();
    $this->get('/questions/sessions/'.$session->id)->assertInertia(fn ($page) => $page
        ->where('question.is_bookmarked', true)
        ->has('question.bookmark_id')
    );
});
