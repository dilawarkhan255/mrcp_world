<?php

use App\Models\Bookmark;
use App\Models\Category;
use App\Models\Exam;
use App\Models\Question;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

function notesQuestion(Exam $exam, Category $category, string $code): Question
{
    return Question::create([
        'exam_id' => $exam->id, 'category_id' => $category->id, 'code' => $code, 'body' => 'Notes stem',
        'options' => ['A' => 'First option', 'B' => 'Second option'], 'correct_option' => 'A',
        'explanation' => 'Explanation.', 'is_published' => true,
    ]);
}

beforeEach(function () {
    $this->exam = Exam::factory()->create(['is_active' => true]);
    $this->category = Category::factory()->create(['exam_id' => $this->exam->id, 'name' => 'Renal', 'slug' => 'renal']);
    $this->user = User::factory()->create();
    $this->actingAs($this->user)->withSession(['exam_id' => $this->exam->id]);
});

it('lists only the bookmarks that have a note on My notes', function () {
    $withNote = notesQuestion($this->exam, $this->category, 'N-1');
    $withoutNote = notesQuestion($this->exam, $this->category, 'N-2');
    Bookmark::create(['user_id' => $this->user->id, 'question_id' => $withNote->id, 'note' => 'Remember the threshold.']);
    Bookmark::create(['user_id' => $this->user->id, 'question_id' => $withoutNote->id, 'note' => null]);

    $this->get('/revision/notes')
        ->assertInertia(fn (Assert $page) => $page
            ->component('revision/Bookmarks')
            ->where('notesOnly', true)
            ->has('bookmarks.data', 1)
            ->where('bookmarks.data.0.code', 'N-1')
            ->where('stats.total', 2)
            ->where('stats.withNotes', 1));

    $this->get('/revision/bookmarks')
        ->assertInertia(fn (Assert $page) => $page->where('notesOnly', false)->has('bookmarks.data', 2));
});

it('shows an empty notes list when no bookmark has a note', function () {
    $question = notesQuestion($this->exam, $this->category, 'N-3');
    Bookmark::create(['user_id' => $this->user->id, 'question_id' => $question->id, 'note' => null]);

    $this->get('/revision/notes')
        ->assertInertia(fn (Assert $page) => $page->component('revision/Bookmarks')->has('bookmarks.data', 0)->where('notesOnly', true));
});

it('keeps the notes of one student out of another student list', function () {
    $question = notesQuestion($this->exam, $this->category, 'N-4');
    Bookmark::create(['user_id' => User::factory()->create()->id, 'question_id' => $question->id, 'note' => 'Not mine.']);

    $this->get('/revision/notes')
        ->assertInertia(fn (Assert $page) => $page->has('bookmarks.data', 0));
});
