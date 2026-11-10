<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Exam;
use App\Models\PastPaper;
use App\Models\PracticeItem;
use App\Models\PracticeSession;
use App\Models\Qbank;
use App\Models\Question;
use App\Models\QuestionComment;
use App\Models\Subscription;
use App\Models\TextbookChapter;
use App\Models\User;
use App\Services\CandidateStatsService;
use App\Services\FlashcardService;
use App\Services\LeaderboardService;
use App\Services\PracticeService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Sample content and activity so a local or demo site does not look empty:
 * subjects, MCQs (main bank and a sample paper), textbook chapters, demo
 * students with subscriptions, practice history, leaderboard entries,
 * comments and candidate statistics.
 *
 * Runs only in the local and testing environments. Safe to run again: it
 * adds what is missing and never overwrites content an admin has edited.
 * Every MCQ is coded SAMPLE-… and flagged for review, because sample
 * medical content must be checked by a clinician before real use.
 */
class DemoContentSeeder extends Seeder
{
    private const DEMO_STUDENTS = [
        ['Dr Amna Rashid', 0.86], ['Dr Bilal Khan', 0.78], ['Dr Chloe Evans', 0.72], ['Dr Daniyal Ahmed', 0.66],
        ['Dr Emma Walsh', 0.61], ['Dr Faisal Malik', 0.55], ['Dr Grace O\'Neill', 0.81], ['Dr Hamza Iqbal', 0.69],
        ['Dr Isha Patel', 0.74], ['Dr Jamal Hussain', 0.58],
    ];

    private const REVIEW_NOTE = 'Sample content written for the demo site. A clinician must check the facts and citations before real students rely on it.';

    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            $this->command->warn('DemoContentSeeder only runs in the local and testing environments.');

            return;
        }
        $this->call([ExamSeeder::class, PlanSeeder::class, RoleSeeder::class]);
        mt_srand(2026);

        /** @var array<string, array<string, mixed>> $content */
        $content = require __DIR__.'/data/demo_content.php';
        DB::transaction(function () use ($content) {
            foreach ($content as $slug => $data) {
                $exam = Exam::where('slug', $slug)->first();
                if ($exam !== null) {
                    $this->seedExam($exam, $data);
                }
            }
            $this->seedTextbook();
            // Flashcards made from the sample MCQs (only their own stem, answer and key points).
            $cards = app(FlashcardService::class);
            Question::where('code', 'like', 'SAMPLE-%')->each(fn (Question $question) => $cards->fromQuestion($question));
        });

        $this->seedActivity();
        app(CandidateStatsService::class)->refresh(full: true);
        foreach (['admin.dashboard.activity', 'admin.dashboard.answer-trend'] as $key) {
            Cache::forget($key);
        }
        $this->forgetLeaderboards();
        $this->command->info('Demo content ready: '.Question::where('code', 'like', 'SAMPLE-%')->count().' sample MCQs, '.User::where('email', 'like', 'demo.student%')->count().' demo students.');
    }

    /** @param array<string, mixed> $data */
    private function seedExam(Exam $exam, array $data): void
    {
        $categories = [];
        foreach ($data['subjects'] as $slug => $name) {
            $categories[$slug] = Category::firstOrCreate(['exam_id' => $exam->id, 'slug' => $slug], ['name' => $name]);
        }
        $paper = PastPaper::firstOrCreate(
            ['exam_id' => $exam->id, 'slug' => $data['paper']['slug']],
            ['title' => $data['paper']['title'], 'year' => $data['paper']['year'], 'is_published' => true],
        );
        // A fixed prefix, so the codes do not depend on the exam's (optional) tile code.
        $prefix = (string) $data['prefix'];
        foreach ($data['questions'] as $index => [$subject, $system, $repeats, $demo, , $stem, $options, $correct, $explanation, $keyPoints, $mnemonic, $references]) {
            $lead = $data['leads'][$index] ?? null;
            [$searchTerm, $searchSites] = $data['image_searches'][$index] ?? [null, null];
            $inPaper = in_array($index, $data['past_paper_codes'], true);
            Question::firstOrCreate(['exam_id' => $exam->id, 'code' => 'SAMPLE-'.$prefix.'-'.str_pad((string) ($index + 1), 3, '0', STR_PAD_LEFT)], [
                'category_id' => $categories[$subject]->id,
                'past_paper_id' => $inPaper ? $paper->id : null,
                'body' => $lead === null ? $stem : $stem."\n\n**".$lead.'**',
                'options' => $options, 'correct_option' => $correct, 'explanation' => $explanation,
                'key_points' => $keyPoints, 'mnemonics' => $mnemonic, 'references' => $references,
                'system' => $system, 'repeat_count' => $repeats, 'source_reference' => $inPaper ? $data['paper']['title'] : 'MRCPWorld sample bank',
                'image_search' => $searchTerm, 'image_search_sites' => $searchSites,
                'is_published' => true, 'is_demo' => $demo, 'is_sample' => true,
                'needs_review' => true, 'review_note' => self::REVIEW_NOTE,
            ]);
        }
    }

    private function seedTextbook(): void
    {
        $chapters = [
            ['mrcp-part-1', 'cardiology', 'Hypertension: diagnosis and stepwise treatment', 'How hypertension is confirmed and the NICE step-by-step drug plan.', 1,
                "## Confirming the diagnosis\n\nIf clinic blood pressure is 140/90 mmHg or higher, offer ambulatory blood pressure monitoring (or home monitoring if ABPM is unsuitable). Hypertension is confirmed by a daytime ABPM or HBPM average of 135/85 mmHg or higher.\n\n## Drug treatment\n\n| Step | Under 55, or type 2 diabetes (any age or origin) | 55 or over, or Black African / African–Caribbean origin without diabetes |\n| --- | --- | --- |\n| 1 | ACE inhibitor or ARB | Calcium-channel blocker |\n| 2 | Add CCB or thiazide-like diuretic | Add ACE inhibitor/ARB (ARB preferred for Black African/African–Caribbean origin) or thiazide-like diuretic |\n| 3 | ACE inhibitor/ARB + CCB + thiazide-like diuretic | Same |\n| 4 | Confirm resistant hypertension, check adherence, seek specialist advice; consider spironolactone if potassium ≤ 4.5 mmol/L, or an alpha- or beta-blocker if potassium > 4.5 mmol/L | Same |\n\nReference: NICE NG136, Hypertension in adults: diagnosis and management.",
                "- Confirm with ABPM or HBPM before treating\n- Age, ethnicity and diabetes decide step 1\n- Step 4 depends on the potassium level"],
            ['mrcp-part-1', 'endocrinology', 'Adrenal insufficiency', 'Recognising Addison disease and treating an adrenal crisis.', 1,
                "## Presentation\n\nFatigue, weight loss, postural hypotension, abdominal pain and hyperpigmentation (in primary disease, from raised ACTH). Typical results are hyponatraemia and hyperkalaemia.\n\n## Diagnosis\n\nThe short Synacthen test shows a poor cortisol rise. A raised ACTH confirms a primary adrenal cause; adrenal autoantibodies support autoimmune Addison disease, the commonest cause in the UK.\n\n## Adrenal crisis\n\nGive intravenous or intramuscular hydrocortisone immediately, with intravenous fluids; do not wait for test results.\n\n## Long-term treatment\n\nHydrocortisone in divided doses, with fludrocortisone in primary disease. Teach sick-day rules and provide an emergency injection kit and steroid card.",
                "- Low sodium + high potassium + hyperpigmentation\n- Treat a crisis first, test later\n- Sick-day rules: double the glucocorticoid dose during illness"],
            ['mrcp-part-2-written', 'endocrinology', 'Hypercalcaemia', 'Causes, urgent treatment and the PTH-led work-up.', 1,
                "## Causes\n\nAbout 90% of cases are primary hyperparathyroidism or malignancy. Measure PTH first: a raised or inappropriately normal PTH points to hyperparathyroidism; a suppressed PTH points to malignancy, vitamin D excess, granulomatous disease or other causes.\n\n## Urgent treatment\n\n1. Rehydrate with intravenous 0.9% sodium chloride.\n2. Then give an intravenous bisphosphonate such as zoledronic acid.\n3. Steroids are useful when calcitriol is raised (lymphoma, sarcoidosis).\n\n## Follow-up\n\nTreat the underlying cause; in primary hyperparathyroidism, assess for surgery.",
                "- PTH first: high/normal = parathyroid, suppressed = look elsewhere\n- Rehydrate before bisphosphonates"],
        ];
        foreach ($chapters as [$examSlug, $subject, $title, $summary, $order, $body, $keyPoints]) {
            $exam = Exam::where('slug', $examSlug)->first();
            $category = $exam === null ? null : Category::where('exam_id', $exam->id)->where('slug', $subject)->first();
            if ($exam === null) {
                continue;
            }
            TextbookChapter::firstOrCreate(['exam_id' => $exam->id, 'slug' => Str::slug($title)], [
                'category_id' => $category?->id, 'title' => $title, 'summary' => $summary, 'body' => $body,
                'key_points' => $keyPoints, 'sort_order' => $order, 'is_published' => true,
            ]);
        }
    }

    /** Demo students with subscriptions, practice history over two weeks, leaderboard entries and comments. */
    private function seedActivity(): void
    {
        $qbanks = Qbank::orderBy('sort_order')->get();
        $mrcp1 = Exam::where('slug', 'mrcp-part-1')->first();
        $mrcp2 = Exam::where('slug', 'mrcp-part-2-written')->first();
        if ($qbanks->isEmpty() || $mrcp1 === null) {
            return;
        }
        $weekStart = app(LeaderboardService::class)->weekStart();
        foreach (self::DEMO_STUDENTS as $number => [$name, $skill]) {
            $student = User::firstOrCreate(['email' => 'demo.student'.($number + 1).'@example.test'], [
                // Demo accounts are not meant for logging in, so the password is random and never shown.
                'name' => $name, 'password' => Str::random(40), 'email_verified_at' => now(),
            ]);
            $student->forceFill(['leaderboard_opt_in' => true, 'leaderboard_name' => $name])->save();
            $student->syncRoles(['student']);
            foreach ($qbanks as $qbank) {
                Subscription::firstOrCreate(['user_id' => $student->id, 'qbank_id' => $qbank->id], [
                    'plan_id' => $qbank->plans()->value('id'), 'status' => Subscription::STATUS_ACTIVE, 'amount' => 8, 'currency' => 'GBP',
                    'payment_reference' => 'DEMO-'.($number + 1), 'starts_at' => now()->subDays(20), 'ends_at' => now()->addDays(160), 'activated_at' => now()->subDays(20),
                ]);
            }
            if (PracticeSession::where('user_id', $student->id)->exists()) {
                continue;
            }
            // Half the students practised this week, so the weekly leaderboard has entries too.
            $from = $number % 2 === 0 ? $weekStart : now()->subDays(13);
            $this->practise($student, $mrcp1, (float) $skill, $from);
            if ($mrcp2 !== null && $number % 3 !== 2) {
                $this->practise($student, $mrcp2, (float) $skill - 0.05, now()->subDays(10));
            }
        }

        $local = User::where('email', 'student@example.test')->first();
        if ($local !== null && ! Subscription::where('user_id', $local->id)->exists()) {
            // The local student gets MRCPWorld 1 Qbank, so both the subscribed and the demo-only states can be tried.
            Subscription::create([
                'user_id' => $local->id, 'qbank_id' => $qbanks->first()->id, 'plan_id' => $qbanks->first()->plans()->value('id'),
                'status' => Subscription::STATUS_ACTIVE, 'amount' => 8, 'currency' => 'GBP', 'payment_reference' => 'DEMO-LOCAL',
                'starts_at' => now()->subDay(), 'ends_at' => now()->addDays(180), 'activated_at' => now()->subDay(),
            ]);
            if (! PracticeSession::where('user_id', $local->id)->exists()) {
                $this->practise($local, $mrcp1, 0.7, now()->subDays(6), 12);
            }
        }
        $this->seedComments();
    }

    private function practise(User $student, Exam $exam, float $skill, \DateTimeInterface $from, ?int $limit = null): void
    {
        $practice = app(PracticeService::class);
        $available = Question::published()->where('exam_id', $exam->id)->count();
        if ($available === 0) {
            return;
        }
        $session = $practice->start($student, $exam, min($limit ?? $available, PracticeService::MAX_QUESTIONS), null, null);
        $items = $session->items()->orderBy('position')->get();
        $span = max(60, now()->getTimestamp() - $from->getTimestamp() - 60);
        foreach ($items as $item) {
            $snapshot = $item->snapshot;
            $wrong = array_values(array_diff(array_keys($snapshot['options']), [$snapshot['correct_option']]));
            $option = mt_rand() / mt_getrandmax() < $skill || $wrong === [] ? $snapshot['correct_option'] : $wrong[mt_rand(0, count($wrong) - 1)];
            $practice->answer($session, $item->position, $option, mt_rand(35, 160));
        }
        // Spread the answers over the period so the trends and the weekly board look real.
        foreach ($items as $item) {
            $at = min(now()->getTimestamp(), $from->getTimestamp() + mt_rand(0, $span));
            PracticeItem::whereKey($item->id)->update(['answered_at' => Carbon::createFromTimestamp($at)]);
        }
        $session->update(['created_at' => $from, 'started_at' => $from]);
    }

    private function seedComments(): void
    {
        $comments = [
            ['SAMPLE-MP1-002', 'Remember that aspirin alone is never enough for AF stroke prevention — this comes up a lot.', 'Thanks! The ORBIT score part caught me out.'],
            ['SAMPLE-MP1-016', 'The 4.5 hour window is from when the patient was last known to be well, not when they were found.', null],
            ['SAMPLE-MP2-001', 'Magnesium even with a normal serum level was the key point for me.', 'Same, and stop the macrolide.'],
        ];
        $students = User::where('email', 'like', 'demo.student%')->orderBy('id')->get();
        if ($students->count() < 2) {
            return;
        }
        foreach ($comments as $index => [$code, $body, $reply]) {
            $question = Question::where('code', $code)->first();
            if ($question === null || QuestionComment::where('question_id', $question->id)->exists()) {
                continue;
            }
            $thread = QuestionComment::create(['question_id' => $question->id, 'user_id' => $students[$index]->id, 'body' => $body]);
            if ($reply !== null) {
                QuestionComment::create(['question_id' => $question->id, 'user_id' => $students[$index + 1]->id, 'parent_id' => $thread->id, 'body' => $reply]);
            }
        }
    }

    private function forgetLeaderboards(): void
    {
        $week = app(LeaderboardService::class)->weekStart()->format('YmdHi');
        foreach (Exam::pluck('id') as $examId) {
            Cache::forget('leaderboard:'.$examId.':'.LeaderboardService::ALL_TIME);
            Cache::forget('leaderboard:'.$examId.':'.LeaderboardService::WEEKLY.':'.$week);
        }
    }
}
