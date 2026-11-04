<?php

use App\Models\Category;
use App\Models\Exam;
use App\Models\PracticeSession;
use App\Models\Question;
use App\Models\User;
use App\Services\PracticeService;
use App\Services\StudyPlanService;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->exam = Exam::factory()->create();
    $this->student = User::factory()->create();
    $this->subjects = [];
    foreach (['Cardiology' => 15, 'Renal' => 15, 'Neurology' => 15, 'Endocrine' => 15, 'Haematology' => 4, 'Dermatology' => 0] as $name => $count) {
        $category = Category::factory()->create(['exam_id' => $this->exam->id, 'name' => $name]);
        foreach (range(1, max(1, $count)) as $index) {
            Question::create([
                'exam_id' => $this->exam->id, 'category_id' => $category->id, 'code' => substr($name, 0, 4).'-'.$index, 'body' => 'Stem',
                'options' => ['A' => 'Right', 'B' => 'Wrong'], 'correct_option' => 'A', 'is_published' => $count > 0,
            ]);
        }
        $this->subjects[$name] = $category;
    }
});

/** Answer $total questions of one subject, the first $correct of them correctly. */
function practise(User $user, Exam $exam, Category $category, int $total, int $correct): void
{
    $practice = app(PracticeService::class);
    $session = $practice->start($user, $exam, $total, $category->id, null);
    foreach (range(1, $total) as $position) {
        $practice->answer($session, $position, $position <= $correct ? 'A' : 'B');
    }
}

it('plans the unpractised subjects when there is no data yet', function () {
    $plan = app(StudyPlanService::class)->build($this->student->id, $this->exam->id);

    expect($plan['has_data'])->toBeFalse()->and($plan['weak'])->toBe([])
        ->and(array_column($plan['unpractised'], 'name'))->toBe(['Cardiology', 'Endocrine', 'Neurology', 'Renal', 'Haematology'])
        ->and($plan['unpractised'][0]['reason'])->toBe('Not practised yet · 15 questions available')
        ->and($plan['days'])->toHaveCount(7)
        ->and(array_column($plan['days'][0]['items'], 'kind'))->toBe(['new', 'new', 'new'])
        ->and($plan['days'][0]['total'])->toBe(30);
    // Dermatology has no published question, so it is never planned.
    expect(collect($plan['days'])->flatMap(fn (array $day) => array_column($day['items'], 'name'))->unique()->all())->not->toContain('Dermatology');
});

it('picks the three weakest subjects with at least 10 answers and explains why', function () {
    practise($this->student, $this->exam, $this->subjects['Cardiology'], 10, 6);
    practise($this->student, $this->exam, $this->subjects['Renal'], 12, 3);
    practise($this->student, $this->exam, $this->subjects['Neurology'], 10, 9);
    practise($this->student, $this->exam, $this->subjects['Endocrine'], 9, 0); // Below the minimum.

    $plan = app(StudyPlanService::class)->build($this->student->id, $this->exam->id);

    expect($plan['has_data'])->toBeTrue()
        ->and(array_column($plan['weak'], 'name'))->toBe(['Renal', 'Cardiology', 'Neurology'])
        ->and($plan['weak'][1]['reason'])->toBe('60% correct across 10 answers')
        ->and($plan['weak'][0]['reason'])->toBe('25% correct across 12 answers')
        ->and(array_column($plan['unpractised'], 'name'))->toBe(['Haematology']);
});

it('mixes weak and new subjects each day and never asks for more questions than exist', function () {
    practise($this->student, $this->exam, $this->subjects['Cardiology'], 10, 5);
    practise($this->student, $this->exam, $this->subjects['Renal'], 10, 4);

    $days = app(StudyPlanService::class)->build($this->student->id, $this->exam->id)['days'];

    foreach ($days as $day) {
        $kinds = array_count_values(array_column($day['items'], 'kind'));
        expect($day['items'])->toHaveCount(3)
            ->and($kinds['weak'] ?? 0)->toBe(2)
            ->and($kinds['new'] ?? 0)->toBe(1)
            ->and(count(array_unique(array_column($day['items'], 'category_id'))))->toBe(3);
    }
    $haematology = collect($days)->flatMap(fn (array $day) => $day['items'])->firstWhere('name', 'Haematology');
    expect($haematology['count'])->toBe(4);
});

it('renders the plan page and starts a planned subject in one click', function () {
    practise($this->student, $this->exam, $this->subjects['Renal'], 10, 2);
    $this->actingAs($this->student)->withSession(['exam_id' => $this->exam->id]);

    $this->get('/study-plan')->assertInertia(fn (Assert $page) => $page
        ->component('StudyPlan')
        ->where('plan.weak.0.name', 'Renal')
        ->has('plan.days', 7));

    $this->post('/questions/sessions', ['count' => 10, 'category_id' => $this->subjects['Renal']->id, 'source' => 'main'])->assertRedirect();
    $session = PracticeSession::where('user_id', $this->student->id)->latest('id')->firstOrFail();
    expect($session->category_id)->toBe($this->subjects['Renal']->id)->and($session->total_questions)->toBe(10);
});
