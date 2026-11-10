<?php

use App\Models\Category;
use App\Models\Exam;
use App\Models\PastPaper;
use App\Models\Question;
use App\Models\User;
use App\Support\StudentNavigation;
use Inertia\Testing\AssertableInertia as Assert;

function navQuestion(Exam $exam, Category $category, ?PastPaper $paper = null, array $overrides = []): Question
{
    return Question::create(array_merge([
        'exam_id' => $exam->id, 'category_id' => $category->id, 'past_paper_id' => $paper?->id,
        'code' => fake()->unique()->bothify('NAV-########'), 'body' => 'Navigation question',
        'options' => ['A' => 'First option', 'B' => 'Second option'], 'correct_option' => 'A',
        'explanation' => 'Explanation.', 'is_published' => true,
    ], $overrides));
}

function topLevelTitles(array $navigation): array
{
    return array_column($navigation, 'title');
}

it('lists the sidebar items in the agreed order', function () {
    expect(topLevelTitles(StudentNavigation::for(null)))->toBe([
        'Dashboard', 'My Notes', 'Search MCQs', 'Question Bank', 'Past Papers', 'Extended Textbook', 'Image Atlas', 'Flashcards', 'Review Questions',
        'More', 'Mock Tests', 'Performance', 'Leaderboard', 'AI Study Plan', 'Knowledge Tutor', 'Subscription', 'Feedback',
    ]);
    expect(collect(StudentNavigation::for(null))->firstWhere('title', 'More'))->toMatchArray(['section' => 'More']);
});

it('starts Question Bank with the main bank and keeps Past Papers as its own item', function () {
    $items = collect(StudentNavigation::for(null));
    $bank = $items->firstWhere('title', 'Question Bank');

    expect(array_column($bank['children'], 'title'))->toBe(['Main MCQ Bank'])
        ->and(array_column($bank['children'], 'href'))->toBe(['/questions/bank'])
        ->and($items->firstWhere('title', 'Past Papers')['href'])->toBe('/questions/past-papers');
});

it('adds only subjects with published questions, ordered by name', function () {
    $exam = Exam::factory()->create(['is_active' => true]);
    $other = Exam::factory()->create(['is_active' => true]);
    $zeta = Category::factory()->create(['exam_id' => $exam->id, 'name' => 'Zeta', 'slug' => 'zeta']);
    $alpha = Category::factory()->create(['exam_id' => $exam->id, 'name' => 'Alpha', 'slug' => 'alpha']);
    $hidden = Category::factory()->create(['exam_id' => $exam->id, 'name' => 'Hidden', 'slug' => 'hidden']);
    $draftOnly = Category::factory()->create(['exam_id' => $exam->id, 'name' => 'Draft only', 'slug' => 'draft-only']);
    $elsewhere = Category::factory()->create(['exam_id' => $other->id, 'name' => 'Elsewhere', 'slug' => 'elsewhere']);
    $draftPaper = PastPaper::create(['exam_id' => $exam->id, 'title' => 'Draft', 'slug' => 'draft', 'year' => 2025, 'is_published' => false]);

    navQuestion($exam, $zeta);
    navQuestion($exam, $alpha);
    navQuestion($exam, $hidden, null, ['is_published' => false]);
    navQuestion($exam, $draftOnly, $draftPaper);
    navQuestion($other, $elsewhere);

    $bank = collect(StudentNavigation::for($exam))->firstWhere('title', 'Question Bank');
    $subjects = array_slice($bank['children'], 1);

    expect(array_column($subjects, 'title'))->toBe(['Alpha', 'Zeta'])
        ->and($subjects[0]['children'])->toBe([
            ['title' => 'Main MCQ Bank', 'icon' => 'Library', 'href' => '/questions/subjects/alpha'],
            ['title' => 'Past Paper MCQs', 'icon' => 'FileClock', 'href' => '/questions/subjects/alpha/past-papers', 'locked' => false],
        ]);
});

it('shares the navigation with signed-in students', function () {
    $this->actingAs(User::factory()->create());
    $exam = Exam::factory()->create(['is_active' => true]);
    $this->withSession(['exam_id' => $exam->id])->get('/dashboard')
        ->assertInertia(fn (Assert $page) => $page->has('navigation', 17)->where('navigation.0.title', 'Dashboard'));
});

it('shares no navigation with guests', function () {
    $this->get('/login')->assertInertia(fn (Assert $page) => $page->has('navigation', 0));
});

it('keeps the removed pages out of the sidebar while their routes still work', function () {
    $hrefs = StudentNavigation::staticHrefs();

    expect($hrefs)->not->toContain('/questions/sessions')
        ->and($hrefs)->not->toContain('/questions/comments')
        ->and($hrefs)->not->toContain('/textbooks/high-yield')
        ->and($hrefs)->not->toContain('/tutor/flashcards');

    $this->actingAs(User::factory()->create());
    $exam = Exam::factory()->create(['is_active' => true]);
    $this->withSession(['exam_id' => $exam->id]);
    $this->get('/questions/sessions')->assertOk();
    $this->get('/questions/comments')->assertOk();
    $this->get('/textbooks/high-yield')->assertNotFound();
    $this->get('/tutor/flashcards')->assertNotFound();
});

it('renders the textbook instead of a placeholder', function () {
    $this->actingAs(User::factory()->create());
    $exam = Exam::factory()->create(['is_active' => true]);
    $this->withSession(['exam_id' => $exam->id])->get('/textbooks')
        ->assertInertia(fn (Assert $page) => $page->component('textbooks/Index')->has('subjects', 0));
});

it('locks the subscription pages in the sidebar for a student without one', function () {
    $items = collect(StudentNavigation::for(null, locked: true))->keyBy('title');

    expect($items['Mock Tests']['locked'])->toBeTrue()
        ->and($items['Dashboard']['locked'])->toBeFalse()
        ->and($items['Question Bank']['children'][0]['locked'])->toBeFalse()
        ->and($items['Past Papers']['locked'])->toBeTrue()
        ->and(collect(StudentNavigation::for(null))->firstWhere('title', 'Mock Tests')['locked'])->toBeFalse();
});

it('has every sidebar icon registered in the front end, so no item shows without one', function () {
    $collect = function (array $items) use (&$collect): array {
        $names = [];
        foreach ($items as $item) {
            if (isset($item['icon'])) {
                $names[] = $item['icon'];
            }
            $names = [...$names, ...$collect($item['children'] ?? [])];
        }

        return $names;
    };
    // Icons added by StudentNavigation for each subject.
    $used = array_unique([...$collect(config('student.navigation')), 'Folder', 'Library', 'FileClock']);
    $source = (string) file_get_contents(resource_path('js/lib/nav-icons.ts'));
    preg_match('/const icons: Record<string, LucideIcon> = \{(.*?)\};/s', $source, $match);
    $registered = array_map('trim', array_filter(explode(',', $match[1] ?? '')));

    expect(array_values(array_diff($used, $registered)))->toBe([]);
});
