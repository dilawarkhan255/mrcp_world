<?php

use App\Models\Category;
use App\Models\Exam;
use App\Models\PracticeSession;
use App\Models\Question;
use App\Models\QuestionComment;
use App\Models\User;
use App\Support\QuestionContent;
use Database\Seeders\RoleSeeder;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    $this->exam = Exam::factory()->create();
    $this->category = Category::factory()->create(['exam_id' => $this->exam->id]);
    $this->question = Question::create([
        'exam_id' => $this->exam->id, 'category_id' => $this->category->id, 'code' => 'DISC-1', 'body' => 'Stem',
        'options' => ['A' => 'One', 'B' => 'Two'], 'correct_option' => 'A', 'is_published' => true,
        'image_search' => 'erythema nodosum', 'image_search_sites' => ['dermnet', 'radiopaedia'],
    ]);
    $this->student = User::factory()->create();
    $this->student->assignRole('student');
});

/** A one-question practice session of the student, answered or not. */
function discussionSession(User $student, ?string $answer = null): PracticeSession
{
    test()->actingAs($student)->post('/questions/sessions', ['count' => 1]);
    $session = PracticeSession::where('user_id', $student->id)->latest('id')->firstOrFail();
    if ($answer !== null) {
        test()->post('/questions/sessions/'.$session->id.'/answer/1', ['option' => $answer]);
    }

    return $session;
}

it('builds encoded image search links for the chosen sites in config order', function () {
    expect(QuestionContent::searchLinks('erythema nodosum', ['dermnet', 'radiopaedia']))->toBe([
        ['site' => 'radiopaedia', 'label' => 'Radiopaedia', 'url' => 'https://radiopaedia.org/search?q=erythema%20nodosum&scope=all'],
        ['site' => 'dermnet', 'label' => 'DermNet', 'url' => 'https://dermnetnz.org/search?q=erythema%20nodosum'],
    ]);
    expect(QuestionContent::searchLinks('a&b', null))->toHaveCount(4)
        ->and(QuestionContent::searchLinks('a&b', null)[2]['url'])->toBe('https://litfl.com/?s=a%26b')
        ->and(QuestionContent::searchLinks('  ', null))->toBe([])
        ->and(QuestionContent::cleanSearchSites(['radiopaedia', 'dermnet', 'litfl', 'eyewiki']))->toBeNull()
        ->and(QuestionContent::cleanSearchSites(['unknown']))->toBeNull();
});

it('shows the image search links only after the question is answered', function () {
    $session = discussionSession($this->student);
    $url = '/questions/sessions/'.$session->id;
    expect($this->get($url)->getContent())->not->toContain('dermnetnz.org');

    $this->post($url.'/answer/1', ['option' => 'B']);
    $this->get($url.'?position=1')->assertInertia(fn (Assert $page) => $page
        ->where('question.result.image_search', 'erythema nodosum')
        ->has('question.result.image_searches', 2)
        ->where('question.result.image_searches.1.label', 'DermNet'));
});

it('sends the discussion of a question only once it is answered', function () {
    $other = User::factory()->create();
    QuestionComment::create(['question_id' => $this->question->id, 'user_id' => $other->id, 'body' => 'The answer is A because of SIADH']);
    $session = discussionSession($this->student);
    $url = '/questions/sessions/'.$session->id;

    $this->get($url)->assertInertia(fn (Assert $page) => $page->where('comments', null));
    expect($this->get($url)->getContent())->not->toContain('because of SIADH');

    $this->post($url.'/answer/1', ['option' => 'A']);
    $this->get($url.'?position=1')->assertInertia(fn (Assert $page) => $page
        ->has('comments', 1)
        ->where('comments.0.body', 'The answer is A because of SIADH')
        ->where('canModerate', false));
});

it('posts a comment and a reply from the practice screen and returns to it', function () {
    $session = discussionSession($this->student, 'A');
    $url = '/questions/sessions/'.$session->id.'?position=1';

    $this->from($url)->post('/questions/comments', ['question_id' => $this->question->id, 'body' => 'Useful explanation'])->assertRedirect($url);
    $thread = QuestionComment::firstOrFail();
    $this->from($url)->post('/questions/comments/'.$thread->id.'/reply', ['body' => 'Agreed'])->assertRedirect($url);

    $this->get($url)->assertInertia(fn (Assert $page) => $page
        ->where('comments.0.body', 'Useful explanation')
        ->where('comments.0.is_mine', true)
        ->where('comments.0.replies.0.body', 'Agreed'));
});

it('applies hidden-comment visibility on the practice screen', function () {
    $author = User::factory()->create();
    QuestionComment::create(['question_id' => $this->question->id, 'user_id' => $author->id, 'body' => 'Hidden thread', 'is_hidden' => true]);
    $session = discussionSession($this->student, 'A');
    $this->get('/questions/sessions/'.$session->id.'?position=1')->assertInertia(fn (Assert $page) => $page->has('comments', 0));

    $editor = User::factory()->create();
    $editor->assignRole('editor');
    $editorSession = discussionSession($editor, 'A');
    $this->get('/questions/sessions/'.$editorSession->id.'?position=1')->assertInertia(fn (Assert $page) => $page
        ->has('comments', 1)->where('comments.0.is_hidden', true)->where('canModerate', true));
});

it('keeps the discussion closed during an open mock', function () {
    Question::create([
        'exam_id' => $this->exam->id, 'category_id' => $this->category->id, 'code' => 'DISC-2', 'body' => 'Second',
        'options' => ['A' => 'One', 'B' => 'Two'], 'correct_option' => 'B', 'is_published' => true,
    ]);
    QuestionComment::create(['question_id' => $this->question->id, 'user_id' => User::factory()->create()->id, 'body' => 'Spoiler']);
    $this->actingAs($this->student)->post('/questions/mocks', ['count' => 2, 'minutes' => 10]);
    $session = PracticeSession::where('user_id', $this->student->id)->firstOrFail();
    $url = '/questions/mocks/'.$session->id;
    $position = $session->items()->where('question_id', $this->question->id)->value('position');
    $this->post($url.'/answer/'.$position, ['option' => 'A']);

    $this->get($url.'?position='.$position)->assertInertia(fn (Assert $page) => $page->where('comments', null));
    $this->post($url.'/finish');
    $this->get($url.'?position='.$position)->assertInertia(fn (Assert $page) => $page->has('comments', 1));
});
